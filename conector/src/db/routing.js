const { Pool } = require('pg');
const logger = require('../logger');

if (!process.env.BOT74_DB_USER || !process.env.BOT74_DB_PASS) {
  throw new Error('BOT74_DB_USER e BOT74_DB_PASS são obrigatórios no .env');
}
const pool = new Pool({
  host:     process.env.BOT74_DB_HOST || 'localhost',
  port:     parseInt(process.env.BOT74_DB_PORT || '5432'),
  database: process.env.BOT74_DB_NAME || 'bot74_saas',
  user:     process.env.BOT74_DB_USER,
  password: process.env.BOT74_DB_PASS,
});

// Cria as tabelas se não existirem
async function init() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS connection_routing (
      id               SERIAL PRIMARY KEY,
      connection_id    VARCHAR(50)  NOT NULL,
      connection_nome  VARCHAR(100) NOT NULL DEFAULT '',
      conta            VARCHAR(20)  NOT NULL DEFAULT 'principal',
      vtiger_user_id   VARCHAR(20)  NOT NULL,
      vtiger_user_nome VARCHAR(100) NOT NULL DEFAULT '',
      ativo            BOOLEAN      NOT NULL DEFAULT true,
      criado_em        TIMESTAMP    DEFAULT NOW()
    )
  `);
  await pool.query(`
    ALTER TABLE connection_routing ADD COLUMN IF NOT EXISTS conta VARCHAR(20) NOT NULL DEFAULT 'principal'
  `);
  await pool.query(`
    CREATE TABLE IF NOT EXISTS agent_routing (
      id               SERIAL PRIMARY KEY,
      prime_user_id    INTEGER      NOT NULL UNIQUE,
      prime_user_nome  VARCHAR(100) NOT NULL DEFAULT '',
      vtiger_user_id   VARCHAR(20)  NOT NULL,
      vtiger_user_nome VARCHAR(100) NOT NULL DEFAULT '',
      ativo            BOOLEAN      NOT NULL DEFAULT true,
      criado_em        TIMESTAMP    DEFAULT NOW()
    )
  `);
  logger.info('DB routing: tabelas connection_routing e agent_routing prontas');
}

// Retorna todas as regras
async function listar() {
  const { rows } = await pool.query(
    'SELECT * FROM connection_routing ORDER BY id'
  );
  return rows;
}

// Insere nova regra
async function inserir({ connection_id, connection_nome, conta, vtiger_user_id, vtiger_user_nome }) {
  const { rows } = await pool.query(
    `INSERT INTO connection_routing (connection_id, connection_nome, conta, vtiger_user_id, vtiger_user_nome)
     VALUES ($1, $2, $3, $4, $5) RETURNING *`,
    [connection_id, connection_nome, conta || 'principal', vtiger_user_id, vtiger_user_nome]
  );
  _invalidarCache();
  return rows[0];
}

// Atualiza ativo/inativo
async function alternarAtivo(id) {
  await pool.query(
    'UPDATE connection_routing SET ativo = NOT ativo WHERE id = $1',
    [id]
  );
  _invalidarCache();
}

// Remove regra
async function remover(id) {
  await pool.query('DELETE FROM connection_routing WHERE id = $1', [id]);
  _invalidarCache();
}

// ─── Cache em memória (TTL 5 min) ─────────────────────────────────────────────
let _cache = null;
let _cacheAt = 0;
const CACHE_TTL = 5 * 60 * 1000;

function _invalidarCache() {
  _cache = null;
}

async function _carregarCache() {
  if (_cache && Date.now() - _cacheAt < CACHE_TTL) return _cache;
  const rows = await listar();
  _cache = {};
  for (const r of rows) {
    if (r.ativo) {
      _cache[String(r.connection_id)] = {
        vtiger_user_id: r.vtiger_user_id,
        conta: r.conta || 'principal',
      };
    }
  }
  _cacheAt = Date.now();
  return _cache;
}

/**
 * Resolve o assigned_user_id do vTiger para uma conexão Prime Atende.
 * @param {string} connectionId  - ID numérico da conexão WhatsApp (ex: "22", "23")
 * @param {string} [contaFallback] - "clinica" | "odonto" (fallback quando connectionId é vazio)
 * @returns {Promise<string>}    - ex: "19x5" — fallback "19x1" (admin)
 */
async function resolverUsuario(connectionId, contaFallback) {
  try {
    const mapa = await _carregarCache();
    if (connectionId && mapa[String(connectionId)]) {
      return mapa[String(connectionId)].vtiger_user_id;
    }
    // Fallback pela conta (ex: "odonto" cadastrado como connection_id especial)
    if (contaFallback && mapa[contaFallback]) {
      return mapa[contaFallback].vtiger_user_id;
    }
  } catch (err) {
    logger.warn('routing.resolverUsuario falhou — usando admin', { err: err.message });
  }
  return '19x1';
}

/**
 * Resolve a conta Prime Atende ('principal' | 'odonto') para uma conexão.
 * @param {string} connectionId
 * @returns {Promise<string>}
 */
async function resolverConta(connectionId) {
  try {
    const mapa = await _carregarCache();
    if (connectionId && mapa[String(connectionId)]) {
      return mapa[String(connectionId)].conta;
    }
  } catch (err) {
    logger.warn('routing.resolverConta falhou — usando principal', { err: err.message });
  }
  return 'principal';
}

/**
 * Resolve a conta Prime Atende pelo usuário vTiger responsável.
 * Usa a primeira regra ativa que contenha esse vtiger_user_id.
 */
async function resolverContaPorUsuario(vtigerUserId) {
  try {
    const mapa = await _carregarCache();
    const regra = Object.values(mapa).find(r => r.vtiger_user_id === vtigerUserId);
    if (regra) return regra.conta;
  } catch (err) {
    logger.warn('routing.resolverContaPorUsuario falhou — usando principal', { err: err.message });
  }
  return 'principal';
}

// ─── Agent routing (Prime userId → vTiger userId) ──────────────────────────────
async function listarAgentes() {
  const { rows } = await pool.query('SELECT * FROM agent_routing ORDER BY id');
  return rows;
}

async function inserirAgente({ prime_user_id, prime_user_nome, vtiger_user_id, vtiger_user_nome }) {
  const { rows } = await pool.query(
    `INSERT INTO agent_routing (prime_user_id, prime_user_nome, vtiger_user_id, vtiger_user_nome)
     VALUES ($1, $2, $3, $4)
     ON CONFLICT (prime_user_id) DO UPDATE
       SET prime_user_nome=$2, vtiger_user_id=$3, vtiger_user_nome=$4, ativo=true
     RETURNING *`,
    [prime_user_id, prime_user_nome, vtiger_user_id, vtiger_user_nome]
  );
  _invalidarCache();
  return rows[0];
}

async function removerAgente(id) {
  await pool.query('DELETE FROM agent_routing WHERE id = $1', [id]);
  _invalidarCache();
}

let _cacheAgentes = null;

async function resolverVtigerUser(primeUserId) {
  try {
    if (!_cacheAgentes || Date.now() - _cacheAt < CACHE_TTL) {
      const rows = await listarAgentes();
      _cacheAgentes = {};
      for (const r of rows) {
        if (r.ativo) _cacheAgentes[String(r.prime_user_id)] = r.vtiger_user_id;
      }
    }
    return _cacheAgentes[String(primeUserId)] || null;
  } catch (err) {
    logger.warn('routing.resolverVtigerUser falhou', { err: err.message });
    return null;
  }
}

module.exports = { init, listar, inserir, alternarAtivo, remover, resolverUsuario, resolverConta, resolverContaPorUsuario, listarAgentes, inserirAgente, removerAgente, resolverVtigerUser };

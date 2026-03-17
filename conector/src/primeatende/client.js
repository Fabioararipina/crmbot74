const axios = require('axios');
const { Pool } = require('pg');
const logger = require('../logger');
const { resolverConta } = require('../db/routing');

// Pool de conexão direta ao banco PostgreSQL do IsiChat
// (a API REST não suporta filtro por telefone — solução via DB direto)
if (!process.env.ISICHAT_DB_HOST || !process.env.ISICHAT_DB_USER || !process.env.ISICHAT_DB_PASS) {
  throw new Error('ISICHAT_DB_HOST, ISICHAT_DB_USER e ISICHAT_DB_PASS são obrigatórios no .env');
}
const _isichatPool = new Pool({
  host:     process.env.ISICHAT_DB_HOST,
  port:     parseInt(process.env.ISICHAT_DB_PORT || '5432'),
  database: process.env.ISICHAT_DB_NAME,
  user:     process.env.ISICHAT_DB_USER,
  password: process.env.ISICHAT_DB_PASS,
  connectionTimeoutMillis: 5000,
  idleTimeoutMillis: 30000,
  max: 3,
});

// Sessões JWT por conta (renovadas automaticamente ao expirar)
const _sessoes = {
  principal: { jwt: null, expiry: null },
  odonto:    { jwt: null, expiry: null },
};

async function autenticar(conta = 'principal') {
  const sessao = _sessoes[conta];
  if (sessao.jwt && sessao.expiry && Date.now() < sessao.expiry) return sessao.jwt;

  const email    = conta === 'odonto' ? process.env.PRIME_EMAIL_ODONTO    : process.env.PRIME_EMAIL;
  const password = conta === 'odonto' ? process.env.PRIME_PASSWORD_ODONTO : process.env.PRIME_PASSWORD;

  const { data } = await axios.post(`${process.env.PRIME_URL}/auth/login`, {
    email, password,
  }, { timeout: 10000 });

  sessao.jwt    = data.token;
  sessao.expiry = Date.now() + 6 * 60 * 60 * 1000; // 6 horas
  logger.debug(`Prime Atende: JWT renovado (${conta})`);
  return sessao.jwt;
}

function api(conta = 'principal') {
  return {
    get: async (path, params) => {
      const jwt = await autenticar(conta);
      return axios.get(`${process.env.PRIME_URL}${path}`, {
        headers: { Authorization: `Bearer ${jwt}` },
        params,
        timeout: 10000,
      });
    },
    post: async (path, body) => {
      const jwt = await autenticar(conta);
      return axios.post(`${process.env.PRIME_URL}${path}`, body, {
        headers: { Authorization: `Bearer ${jwt}`, 'Content-Type': 'application/json' },
        timeout: 10000,
      });
    },
  };
}

// Resolve connectionId numérico e conta Prime Atende via banco de dados.
// contaFallback: 'odonto' | 'clinica' (vindo do query param do webhook quando connectionId está vazio)
async function resolverConexao(connectionId, contaFallback) {
  const connId = Number(connectionId) || Number(process.env.PRIME_CONNECTION_DEFAULT) || 23;
  let conta = 'principal';
  if (connectionId) {
    conta = await resolverConta(String(connId));
  } else if (contaFallback === 'odonto') {
    conta = 'odonto';
  }
  return { connId, conta };
}

async function enviarMensagem({ numero, template, mensagem, connectionId, fecharTicket = false }) {
  const { connId, conta } = await resolverConexao(connectionId);
  const body = mensagem || template;

  const { data } = await api(conta).post('/messages/send', {
    number: numero,
    body,
    whatsappId: connId,
    queueId: process.env.PRIME_QUEUE_ID || '',
    sendSignature: false,
    closeTicket: fecharTicket,
  });

  logger.info('Prime Atende: mensagem enviada', { numero, template, conexao: connId, conta });
  return data;
}

async function criarContato({ nome, numero, connectionId, contaFallback }) {
  const { conta } = await resolverConexao(connectionId, contaFallback);
  try {
    const { data } = await api(conta).post('/contacts', { name: nome || numero, number: numero });
    logger.info('Prime Atende: contato criado', { numero, conta });
    return data;
  } catch (err) {
    const msg = err?.response?.data?.error || err?.response?.data?.message || '';
    if (err?.response?.status === 400 && msg.toLowerCase().includes('não está cadastrado')) {
      logger.warn('Prime Atende: número sem WhatsApp, contato ignorado', { numero });
      return null;
    }
    if (err?.response?.status === 400 && msg === 'ERR_DUPLICATED_CONTACT') {
      logger.info('Prime Atende: contato já existe', { numero, conta });
      return null;
    }
    throw err;
  }
}

async function buscarContatoPorTelefone(telefone, connectionId, contaFallback) {
  const { conta } = await resolverConexao(connectionId, contaFallback);
  const { data } = await api(conta).get('/contacts', { searchParam: telefone });
  return data?.contacts?.[0] || null;
}

// Retorna o ticket ativo mais recente para um número de telefone, enriquecido com:
// agente, fila, direção da última mensagem (fromMe) e última mensagem do cliente.
// Gera variantes do número para cobrir o 9º dígito brasileiro (12 ↔ 13 dígitos)
function variantesNumero(telefone) {
  const d = telefone.replace(/\D/g, '');
  const variantes = [d];
  // 13 dígitos: 55 + 2 dígitos área + 9 + 8 dígitos → tenta sem o 9
  if (d.length === 13 && d.startsWith('55')) {
    variantes.push(d.slice(0, 4) + d.slice(5)); // remove o 5º dígito (o 9)
  }
  // 12 dígitos: 55 + 2 dígitos área + 8 dígitos → tenta com o 9
  if (d.length === 12 && d.startsWith('55')) {
    variantes.push(d.slice(0, 4) + '9' + d.slice(4)); // insere 9 após o código de área
  }
  return variantes;
}

async function buscarTicketPorTelefone(telefone) {
  try {
    const numeros = variantesNumero(telefone);
    const { rows } = await _isichatPool.query(
      `SELECT
         t.id, t.status, t."userId", t."whatsappId", t."queueId",
         c.number AS contact_number,
         u.name   AS agent_name,
         q.name   AS queue_name,
         (SELECT "fromMe" FROM "Messages"
          WHERE "ticketId" = t.id
          ORDER BY "createdAt" DESC LIMIT 1)                         AS last_msg_from_me,
         (SELECT body FROM "Messages"
          WHERE "ticketId" = t.id AND "fromMe" = false
          ORDER BY "createdAt" DESC LIMIT 1)                         AS last_client_msg
       FROM "Tickets" t
       JOIN "Contacts" c ON c.id = t."contactId"
       LEFT JOIN "Users" u ON u.id = t."userId"
       LEFT JOIN "Queues" q ON q.id = t."queueId"
       WHERE c.number = ANY($1)
       ORDER BY
         CASE WHEN t.status IN ('open','pending') THEN 0 ELSE 1 END,
         t.id DESC
       LIMIT 1`,
      [numeros]
    );
    const ticket = rows[0] || null;
    logger.info('Prime DB: ticket buscado', {
      telefone,
      ticketId: ticket?.id,
      status: ticket?.status,
      userId: ticket?.userId || null,
      agente: ticket?.agent_name || null,
      lastMsgFromMe: ticket?.last_msg_from_me ?? null,
    });
    return ticket;
  } catch (err) {
    logger.warn('Prime DB: erro ao buscar ticket', { telefone, erro: err.message });
    return null;
  }
}

// Retorna todos os agentes/usuários do Prime Atende (ambas as contas)
async function listarUsuarios() {
  const [principal, odonto] = await Promise.allSettled([
    api('principal').get('/users', { pageSize: 100 }),
    api('odonto').get('/users', { pageSize: 100 }),
  ]);
  const listaPrincipal = principal.status === 'fulfilled' ? (principal.value.data?.users || []) : [];
  const listaOdonto    = odonto.status === 'fulfilled'    ? (odonto.value.data?.users    || []) : [];

  // Mescla sem duplicatas (mesmo userId pode aparecer nas duas contas)
  const mapa = {};
  for (const u of [...listaPrincipal, ...listaOdonto]) {
    mapa[u.id] = u;
  }
  return Object.values(mapa).sort((a, b) => a.name?.localeCompare(b.name));
}

module.exports = { enviarMensagem, criarContato, buscarContatoPorTelefone, buscarTicketPorTelefone, listarUsuarios };

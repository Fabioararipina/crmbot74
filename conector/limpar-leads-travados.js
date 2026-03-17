#!/usr/bin/env node
/**
 * limpar-leads-travados.js
 * Limpa leads travados em status antigos cujo ticket no Prime já foi fechado.
 *
 * Regra:
 *   ticket closed + cliente respondeu (msgs_cliente > 0) → "Contactado"
 *   ticket closed + cliente nunca respondeu               → "Tentativa de Contato"
 *   ticket open/pending                                   → ignora (poller cuida)
 *   sem ticket no Prime                                   → ignora
 *
 * Uso:
 *   node limpar-leads-travados.js          ← dry-run (só mostra, não altera)
 *   node limpar-leads-travados.js --aplicar ← aplica as mudanças
 */
require('dotenv').config();
const axios  = require('axios');
const crypto = require('crypto');
const { Pool } = require('pg');

const APLICAR = process.argv.includes('--aplicar');
const STATUS_LIMPAR = ['Novo', 'Aguardando Atendimento', 'Em Atendimento', 'Tentativa de Contato'];

const pool = new Pool({
  host:     process.env.ISICHAT_DB_HOST,
  port:     5432,
  database: process.env.ISICHAT_DB_NAME,
  user:     process.env.ISICHAT_DB_USER,
  password: process.env.ISICHAT_DB_PASS,
  connectionTimeoutMillis: 8000,
  max: 3,
});

// ─── vTiger ──────────────────────────────────────────────────────────────────
const VTIGER_URL  = process.env.VTIGER_URL  || 'http://localhost:8181';
const VTIGER_USER = process.env.VTIGER_USER || 'admin';
const VTIGER_KEY  = process.env.VTIGER_KEY ;

async function vtigerAuth() {
  const { data: ch } = await axios.get(`${VTIGER_URL}/webservice.php`, {
    params: { operation: 'getchallenge', username: VTIGER_USER }, timeout: 10000,
  });
  const token = ch.result.token;
  const accessKey = crypto.createHash('md5').update(token + VTIGER_KEY).digest('hex');
  const { data: login } = await axios.post(`${VTIGER_URL}/webservice.php`,
    new URLSearchParams({ operation: 'login', username: VTIGER_USER, accessKey })
  );
  return login.result.sessionName;
}

async function vtigerQuery(session, query) {
  const { data } = await axios.get(`${VTIGER_URL}/webservice.php`, {
    params: { operation: 'query', sessionName: session, query }, timeout: 15000,
  });
  return (data.success && data.result) ? data.result : [];
}

async function vtigerRetrieve(session, id) {
  const { data } = await axios.get(`${VTIGER_URL}/webservice.php`, {
    params: { operation: 'retrieve', sessionName: session, id }, timeout: 10000,
  });
  if (!data.success) throw new Error('retrieve falhou: ' + JSON.stringify(data.error));
  return data.result;
}

async function vtigerUpdate(session, leadAtual, campos) {
  const { data } = await axios.post(`${VTIGER_URL}/webservice.php`,
    new URLSearchParams({
      operation: 'update',
      sessionName: session,
      elementType: 'Leads',
      element: JSON.stringify({ ...leadAtual, ...campos }),
    })
  );
  if (!data.success) throw new Error('update falhou: ' + JSON.stringify(data.error));
  return data.result;
}

// ─── Prime DB ─────────────────────────────────────────────────────────────────
function variantesNumero(telefone) {
  const d = telefone.replace(/\D/g, '');
  const v = [d];
  if (d.length === 13 && d.startsWith('55')) v.push(d.slice(0, 4) + d.slice(5));
  if (d.length === 12 && d.startsWith('55')) v.push(d.slice(0, 4) + '9' + d.slice(4));
  return v;
}

async function buscarTicketFechado(telefone) {
  const numeros = variantesNumero(telefone);
  const { rows } = await pool.query(`
    SELECT
      t.id, t.status, t."userId",
      u.name AS agent_name,
      COUNT(CASE WHEN m."fromMe" = false THEN 1 END) AS msgs_cliente
    FROM "Tickets" t
    JOIN "Contacts" c ON c.id = t."contactId"
    LEFT JOIN "Users" u ON u.id = t."userId"
    LEFT JOIN "Messages" m ON m."ticketId" = t.id
    WHERE c.number = ANY($1) AND t.status = 'closed'
    GROUP BY t.id, t.status, t."userId", u.name
    ORDER BY t.id DESC
    LIMIT 1
  `, [numeros]);
  return rows[0] || null;
}

// ─── Main ─────────────────────────────────────────────────────────────────────
async function main() {
  console.log('\n' + '═'.repeat(65));
  console.log(APLICAR
    ? '  LIMPEZA DE LEADS TRAVADOS — APLICANDO MUDANÇAS'
    : '  LIMPEZA DE LEADS TRAVADOS — DRY-RUN (sem alterações)');
  console.log('═'.repeat(65));

  const session = await vtigerAuth();
  console.log('  vTiger: autenticado\n');

  // Busca todos os leads nos status a limpar (com paginação)
  const leads = [];
  for (const status of STATUS_LIMPAR) {
    const PAGE = 100;
    let offset = 0;
    while (true) {
      const pagina = await vtigerQuery(session,
        `SELECT id, phone, mobile, leadstatus, firstname, lastname FROM Leads WHERE leadstatus='${status}' LIMIT ${offset}, ${PAGE};`
      );
      leads.push(...pagina);
      if (pagina.length < PAGE) break;
      offset += PAGE;
    }
  }

  console.log(`  Total leads encontrados: ${leads.length}\n`);
  console.log('  ' + '-'.repeat(63));

  const contadores = { contactado: 0, tentativa: 0, ignorado: 0, semTicket: 0, erro: 0 };

  for (const lead of leads) {
    const telefoneRaw = lead.phone || lead.mobile;
    if (!telefoneRaw) { contadores.ignorado++; continue; }

    const digits   = telefoneRaw.replace(/\D/g, '');
    const telefone = digits.startsWith('55') ? digits : `55${digits}`;
    const nome     = `${lead.firstname || ''} ${lead.lastname || ''}`.trim() || telefone;

    try {
      const ticket = await buscarTicketFechado(telefone);

      if (!ticket) {
        contadores.semTicket++;
        continue;
      }

      const msgsCliente = parseInt(ticket.msgs_cliente) || 0;
      const novoStatus  = msgsCliente > 0 ? 'Contactado' : 'Tentativa de Contato';

      // Só altera se o novo status for diferente do atual
      if (novoStatus === lead.leadstatus) {
        contadores.ignorado++;
        continue;
      }

      console.log(`  ${lead.id.padEnd(9)} ${nome.substring(0, 25).padEnd(26)} ${lead.leadstatus.padEnd(24)} → ${novoStatus} (${msgsCliente} msgs cliente, agente: ${ticket.agent_name || '-'})`);

      if (APLICAR) {
        const leadCompleto = await vtigerRetrieve(session, lead.id);
        await vtigerUpdate(session, leadCompleto, { leadstatus: novoStatus });
        process.stdout.write('    ✓ atualizado\n');
      }

      novoStatus === 'Contactado' ? contadores.contactado++ : contadores.tentativa++;

    } catch (err) {
      console.log(`  ${lead.id} ERRO: ${err.message}`);
      contadores.erro++;
    }
  }

  console.log('\n' + '═'.repeat(65));
  console.log('  RESUMO');
  console.log('─'.repeat(65));
  console.log(`  Seriam/foram movidos para "Contactado"          : ${contadores.contactado}`);
  console.log(`  Seriam/foram movidos para "Tentativa de Contato": ${contadores.tentativa}`);
  console.log(`  Sem ticket fechado no Prime (ignorados)         : ${contadores.semTicket}`);
  console.log(`  Sem alteração necessária / sem telefone         : ${contadores.ignorado}`);
  console.log(`  Erros                                           : ${contadores.erro}`);
  if (!APLICAR) {
    console.log('\n  Para aplicar, rode com --aplicar:');
    console.log('  node limpar-leads-travados.js --aplicar');
  }
  console.log('═'.repeat(65) + '\n');

  await pool.end();
}

main().catch(e => { console.error('ERRO FATAL:', e.message); pool.end(); process.exit(1); });

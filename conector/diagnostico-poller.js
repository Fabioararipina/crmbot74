#!/usr/bin/env node
/**
 * diagnostico-poller.js
 * Simula cada passo do poller e mostra o que está acontecendo com cada lead.
 *
 * Uso:
 *   node diagnostico-poller.js
 *   node diagnostico-poller.js 558799478492      ← analisa número específico
 *   node diagnostico-poller.js --lead 10x2479    ← analisa lead específico por ID
 */

require('dotenv').config({ path: require('path').join(__dirname, '.env') });
const axios  = require('axios');
const crypto = require('crypto');
const { Pool } = require('pg');

// ─── Config (herda do .env) ───────────────────────────────────────────────────
const VTIGER_URL  = process.env.VTIGER_URL  || 'http://localhost:8181';
const VTIGER_USER = process.env.VTIGER_USER || 'admin';
const VTIGER_KEY  = process.env.VTIGER_KEY ;

const PRIME_DB = {
  host:     process.env.ISICHAT_DB_HOST,
  port:     parseInt(process.env.ISICHAT_DB_PORT || '5432'),
  database: process.env.ISICHAT_DB_NAME,
  user:     process.env.ISICHAT_DB_USER,
  password: process.env.ISICHAT_DB_PASS,
  connectionTimeoutMillis: 8000,
};

const STATUS_VERIFICAR = ['Novo', 'Aguardando Atendimento', 'Em Atendimento', 'Tentativa de Contato'];
const STATUS_ORDEM = {
  'Novo': 1, 'Aguardando Atendimento': 2, 'Em Atendimento': 3,
  'Tentativa de Contato': 3, 'Contactado': 4, 'Confirmado': 5,
  'Cancelado': 6, 'Perdido': 8, 'Descartado': 9, 'Erro': 0,
};
const STATUS_DORMENTES = ['Descartado', 'Perdido'];

// ─── Helpers ─────────────────────────────────────────────────────────────────
function hr(char = '─', n = 70) { return char.repeat(n); }
function ok(msg)   { console.log(`  \x1b[32m✓\x1b[0m ${msg}`); }
function warn(msg) { console.log(`  \x1b[33m⚠\x1b[0m ${msg}`); }
function err(msg)  { console.log(`  \x1b[31m✗\x1b[0m ${msg}`); }
function info(msg) { console.log(`  \x1b[36mℹ\x1b[0m ${msg}`); }

// ─── vTiger ──────────────────────────────────────────────────────────────────
async function vtigerAuth() {
  const { data: ch } = await axios.get(`${VTIGER_URL}/webservice.php`, {
    params: { operation: 'getchallenge', username: VTIGER_USER },
    timeout: 10000,
  });
  if (!ch.success) throw new Error('getchallenge falhou: ' + JSON.stringify(ch));
  const token = ch.result.token;
  const accessKey = crypto.createHash('md5').update(token + VTIGER_KEY).digest('hex');
  const { data: login } = await axios.post(`${VTIGER_URL}/webservice.php`,
    new URLSearchParams({ operation: 'login', username: VTIGER_USER, accessKey })
  );
  if (!login.success) throw new Error('login falhou: ' + JSON.stringify(login));
  return login.result.sessionName;
}

async function vtigerQuery(sessionName, query) {
  const { data } = await axios.get(`${VTIGER_URL}/webservice.php`, {
    params: { operation: 'query', sessionName, query },
    timeout: 15000,
  });
  if (!data.success) {
    warn(`Query falhou: ${JSON.stringify(data.error)}`);
    return [];
  }
  return data.result || [];
}

async function vtigerRetrieve(sessionName, id) {
  const { data } = await axios.get(`${VTIGER_URL}/webservice.php`, {
    params: { operation: 'retrieve', sessionName, id },
    timeout: 10000,
  });
  if (!data.success) throw new Error('retrieve falhou: ' + JSON.stringify(data.error));
  return data.result;
}

// ─── Prime DB ─────────────────────────────────────────────────────────────────
function variantesNumero(telefone) {
  const d = telefone.replace(/\D/g, '');
  const variantes = [d];
  if (d.length === 13 && d.startsWith('55')) variantes.push(d.slice(0, 4) + d.slice(5));
  if (d.length === 12 && d.startsWith('55')) variantes.push(d.slice(0, 4) + '9' + d.slice(4));
  return variantes;
}

async function buscarTicket(pool, telefone) {
  const numeros = variantesNumero(telefone);
  const { rows } = await pool.query(
    `SELECT
       t.id, t.status, t."userId", t."whatsappId", t."queueId",
       c.number AS contact_number,
       u.name   AS agent_name,
       q.name   AS queue_name,
       (SELECT "fromMe" FROM "Messages" WHERE "ticketId" = t.id ORDER BY "createdAt" DESC LIMIT 1) AS last_msg_from_me,
       (SELECT body FROM "Messages" WHERE "ticketId" = t.id AND "fromMe" = false ORDER BY "createdAt" DESC LIMIT 1) AS last_client_msg
     FROM "Tickets" t
     JOIN "Contacts" c ON c.id = t."contactId"
     LEFT JOIN "Users" u ON u.id = t."userId"
     LEFT JOIN "Queues" q ON q.id = t."queueId"
     WHERE c.number = ANY($1)
     ORDER BY CASE WHEN t.status IN ('open','pending') THEN 0 ELSE 1 END, t.id DESC
     LIMIT 1`,
    [numeros]
  );
  return { ticket: rows[0] || null, numeros };
}

// ─── derivarStatus ────────────────────────────────────────────────────────────
function derivarStatus(ticket, statusAtual) {
  if (!ticket) return null;
  if (ticket.status === 'closed') return null;
  if (ticket.status === 'pending' || !ticket.userId) return 'Aguardando Atendimento';
  if (ticket.last_msg_from_me === null || ticket.last_msg_from_me === undefined) return 'Tentativa de Contato';
  if (ticket.last_msg_from_me === false) return 'Contactado';
  if (statusAtual === 'Aguardando Atendimento' || STATUS_DORMENTES.includes(statusAtual)) return 'Em Atendimento';
  return 'Tentativa de Contato';
}

// ─── Main ─────────────────────────────────────────────────────────────────────
async function main() {
  const arg = process.argv[2];
  const argLead = process.argv[3];

  console.log('\n' + hr('═'));
  console.log('  DIAGNÓSTICO DO POLLER — CRM BOT74');
  console.log(hr('═'));
  console.log(`  vTiger: ${VTIGER_URL}`);
  console.log(`  Prime DB: ${PRIME_DB.host}:${PRIME_DB.port}/${PRIME_DB.database}`);
  console.log(hr());

  // Conecta ao Prime DB
  const pool = new Pool(PRIME_DB);
  try {
    await pool.query('SELECT 1');
    ok('Conexão com Prime DB estabelecida');
  } catch (e) {
    err(`Não conseguiu conectar ao Prime DB: ${e.message}`);
    process.exit(1);
  }

  // Autentica no vTiger
  let sessionName;
  try {
    sessionName = await vtigerAuth();
    ok('Autenticação vTiger OK');
  } catch (e) {
    err(`vTiger auth falhou: ${e.message}`);
    await pool.end();
    process.exit(1);
  }
  console.log('');

  // ─── Modo: lead específico por ID ─────────────────────────────────────────
  if (arg === '--lead' && argLead) {
    await analisarLeadPorId(sessionName, pool, argLead);
    await pool.end();
    return;
  }

  // ─── Modo: número específico ──────────────────────────────────────────────
  if (arg && !arg.startsWith('-')) {
    await analisarNumero(pool, arg, sessionName);
    await pool.end();
    return;
  }

  // ─── Modo: varredura completa ─────────────────────────────────────────────
  console.log(`  Buscando leads em: ${STATUS_VERIFICAR.join(', ')}`);
  console.log(hr());

  const leads = [];
  for (const s of STATUS_VERIFICAR) {
    const resultado = await vtigerQuery(
      sessionName,
      `SELECT id, phone, mobile, leadstatus, firstname, lastname, assigned_user_id FROM Leads WHERE leadstatus='${s}' LIMIT 50;`
    );
    if (resultado.length) info(`${resultado.length} lead(s) em "${s}"`);
    leads.push(...resultado);
  }

  if (!leads.length) {
    warn('Nenhum lead encontrado nos status verificados.');
    await pool.end();
    return;
  }

  console.log(`\n  Total: ${leads.length} lead(s)\n`);
  console.log(hr());

  let atualizariam = 0, semTelefone = 0, semTicket = 0, ticketFechado = 0, jaOk = 0;

  for (const lead of leads) {
    const nome = `${lead.firstname || ''} ${lead.lastname || ''}`.trim() || '(sem nome)';
    console.log(`\n  Lead ${lead.id} — ${nome} — Status: "${lead.leadstatus}"`);

    // Verificar phone e mobile
    const telefoneRaw = lead.phone || lead.mobile;
    if (!telefoneRaw) {
      err(`phone="${lead.phone}" mobile="${lead.mobile}" — AMBOS VAZIOS → poller pula este lead`);
      semTelefone++;
      continue;
    }

    const digits  = telefoneRaw.replace(/\D/g, '');
    const telefone = digits.startsWith('55') ? digits : `55${digits}`;
    info(`phone="${lead.phone}" mobile="${lead.mobile}" → normalizado: ${telefone}`);

    // Buscar ticket no Prime
    const { ticket, numeros } = await buscarTicket(pool, telefone);
    info(`Variantes buscadas: ${numeros.join(', ')}`);

    if (!ticket) {
      warn(`Nenhum ticket encontrado no Prime para este número → poller pula`);
      semTicket++;
      continue;
    }

    const ticketInfo = `Ticket #${ticket.id} status=${ticket.status} userId=${ticket.userId || 'null'} agente="${ticket.agent_name || '-'}" fila="${ticket.queue_name || '-'}"`;
    info(ticketInfo);
    info(`last_msg_from_me=${ticket.last_msg_from_me} | last_client_msg="${ticket.last_client_msg?.substring(0, 50) || '-'}"`);

    // derivarStatus
    const novoStatus = derivarStatus(ticket, lead.leadstatus);

    if (!novoStatus) {
      warn(`derivarStatus → null (ticket fechado?) → poller não atualiza status`);
      ticketFechado++;
    } else {
      const ordemAtual = STATUS_ORDEM[lead.leadstatus] || 0;
      const ordemNovo  = STATUS_ORDEM[novoStatus] || 0;
      const isDormente = STATUS_DORMENTES.includes(lead.leadstatus);
      if (isDormente || ordemNovo > ordemAtual) {
        ok(`ATUALIZARIA: leadstatus "${lead.leadstatus}" → "${novoStatus}"`);
        atualizariam++;
      } else {
        info(`Status não avança: atual="${lead.leadstatus}"(${ordemAtual}) novo="${novoStatus}"(${ordemNovo}) → sem mudança`);
        jaOk++;
      }
    }
    console.log('  ' + hr('-', 60));
  }

  // Resumo
  console.log('\n' + hr('═'));
  console.log('  RESUMO');
  console.log(hr());
  console.log(`  Total leads analisados : ${leads.length}`);
  console.log(`  Sem telefone (pulados)  : ${semTelefone}`);
  console.log(`  Sem ticket no Prime     : ${semTicket}`);
  console.log(`  Ticket fechado (null)   : ${ticketFechado}`);
  console.log(`  Status já correto       : ${jaOk}`);
  console.log(`  ATUALIZARIAM agora      : ${atualizariam}`);
  console.log(hr('═') + '\n');

  await pool.end();
}

async function analisarLeadPorId(sessionName, pool, leadId) {
  console.log(`  Analisando lead ID: ${leadId}`);
  console.log(hr());
  try {
    const lead = await vtigerRetrieve(sessionName, leadId);
    console.log('\n  Campos completos do lead (vTiger API):');
    const campos = ['id','firstname','lastname','phone','mobile','leadstatus','assigned_user_id','email','description'];
    for (const c of campos) {
      console.log(`    ${c.padEnd(20)}: ${JSON.stringify(lead[c] ?? null)}`);
    }

    const telefoneRaw = lead.phone || lead.mobile;
    if (!telefoneRaw) {
      err('\n  PROBLEMA: phone E mobile estão vazios! O poller vai pular este lead.');
      return;
    }

    const digits   = telefoneRaw.replace(/\D/g, '');
    const telefone = digits.startsWith('55') ? digits : `55${digits}`;
    info(`\n  Telefone normalizado: ${telefone}`);

    const { ticket, numeros } = await buscarTicket(pool, telefone);
    info(`  Variantes buscadas: ${numeros.join(', ')}`);

    if (!ticket) {
      warn('  Nenhum ticket encontrado no Prime para este número.');
      return;
    }

    console.log('\n  Ticket encontrado:');
    for (const [k, v] of Object.entries(ticket)) {
      if (v !== undefined) console.log(`    ${String(k).padEnd(20)}: ${JSON.stringify(v)}`);
    }

    const novoStatus = derivarStatus(ticket, lead.leadstatus);
    console.log(`\n  derivarStatus → ${novoStatus === null ? 'null (não atualiza)' : '"' + novoStatus + '"'}`);

    if (novoStatus) {
      const ordemAtual = STATUS_ORDEM[lead.leadstatus] || 0;
      const ordemNovo  = STATUS_ORDEM[novoStatus] || 0;
      if (ordemNovo > ordemAtual) ok(`Atualizaria: "${lead.leadstatus}" → "${novoStatus}"`);
      else info(`Não avança: "${lead.leadstatus}"(${ordemAtual}) → "${novoStatus}"(${ordemNovo})`);
    }
  } catch (e) {
    err(`Erro: ${e.message}`);
  }
  console.log('');
}

async function analisarNumero(pool, numero, sessionName) {
  const digits   = numero.replace(/\D/g, '');
  const telefone = digits.startsWith('55') ? digits : `55${digits}`;

  console.log(`  Analisando número: ${numero} → normalizado: ${telefone}`);
  console.log(hr());

  // Buscar ticket no Prime
  const { ticket, numeros } = await buscarTicket(pool, telefone);
  info(`Variantes buscadas: ${numeros.join(', ')}`);

  if (!ticket) {
    warn('Nenhum ticket encontrado no Prime para este número.');
  } else {
    console.log('\n  Ticket encontrado:');
    for (const [k, v] of Object.entries(ticket)) {
      if (v !== undefined) console.log(`    ${String(k).padEnd(20)}: ${JSON.stringify(v)}`);
    }
  }

  // Buscar lead no vTiger
  console.log('\n  Buscando lead no vTiger...');
  const resultados = await vtigerQuery(
    sessionName,
    `SELECT id, phone, mobile, leadstatus, firstname, lastname, assigned_user_id FROM Leads WHERE phone='${telefone}' OR mobile='${telefone}' LIMIT 5;`
  );
  // Também tenta sem o 55
  const semCodigo = digits.startsWith('55') ? digits.slice(2) : digits;
  const resultados2 = await vtigerQuery(
    sessionName,
    `SELECT id, phone, mobile, leadstatus, firstname, lastname, assigned_user_id FROM Leads WHERE phone='${semCodigo}' OR mobile='${semCodigo}' LIMIT 5;`
  );
  const todos = [...resultados, ...resultados2];

  if (!todos.length) {
    warn('Nenhum lead encontrado no vTiger para este número (com ou sem 55).');
    warn('Verifique se o lead foi criado com o campo phone preenchido.');
  } else {
    for (const lead of todos) {
      const nome = `${lead.firstname || ''} ${lead.lastname || ''}`.trim() || '(sem nome)';
      console.log(`\n  Lead ${lead.id} — ${nome}`);
      console.log(`    phone          : ${JSON.stringify(lead.phone)}`);
      console.log(`    mobile         : ${JSON.stringify(lead.mobile)}`);
      console.log(`    leadstatus     : ${lead.leadstatus}`);
      console.log(`    assigned_user  : ${lead.assigned_user_id}`);

      if (ticket) {
        const novoStatus = derivarStatus(ticket, lead.leadstatus);
        console.log(`    derivarStatus → ${novoStatus === null ? 'null (não atualiza status)' : '"' + novoStatus + '"'}`);
      }
    }
  }
  console.log('');
}

main().catch(e => {
  console.error('\nERRO FATAL:', e.message);
  process.exit(1);
});

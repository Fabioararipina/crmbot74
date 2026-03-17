/**
 * Script one-time: move leads "Aguardando Atendimento" com ticket closed para "Perdido"
 * Exclui leads criados hoje.
 *
 * Uso: node -r dotenv/config scripts/mover-aguardando-perdido.js
 */

const axios  = require('axios');
const crypto = require('crypto');
const vtigerClient = require('../src/vtiger/client');
const primeClient  = require('../src/primeatende/client');

const BASE_URL = () => `${process.env.VTIGER_URL}/webservice.php`;
const DELAY_MS = 300;

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// Data de hoje no formato vTiger (YYYY-MM-DD)
const hoje = new Date().toISOString().slice(0, 10);

async function autenticarVtiger() {
  const { data: ch } = await axios.get(BASE_URL(), {
    params: { operation: 'getchallenge', username: process.env.VTIGER_USER },
  });
  const key = crypto.createHash('md5').update(ch.result.token + process.env.VTIGER_KEY).digest('hex');
  const { data: login } = await axios.post(
    BASE_URL(),
    new URLSearchParams({ operation: 'login', username: process.env.VTIGER_USER, accessKey: key }).toString(),
    { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
  );
  return login.result.sessionName;
}

async function main() {
  console.log(`=== Movendo "Aguardando Atendimento" (ticket closed) → "Perdido" ===`);
  console.log(`Excluindo leads criados em: ${hoje}\n`);

  const sessionName = await autenticarVtiger();

  let offset = 0;
  const limit = 100;
  let todos = [];

  while (true) {
    const { data } = await axios.get(BASE_URL(), {
      params: {
        operation: 'query',
        sessionName,
        query: `SELECT id, firstname, lastname, phone, mobile, createdtime FROM Leads WHERE leadstatus='Aguardando Atendimento' AND createdtime < '${hoje} 00:00:00' LIMIT ${offset}, ${limit};`,
      },
    });
    const lote = data.result || [];
    todos = todos.concat(lote);
    if (lote.length < limit) break;
    offset += limit;
  }

  console.log(`Leads candidatos (antes de hoje): ${todos.length}\n`);

  let movidos = 0, semTicketClosed = 0, erros = 0;

  for (const lead of todos) {
    const telefone = (lead.phone || lead.mobile || '').replace(/\D/g, '');
    if (!telefone) { semTicketClosed++; continue; }

    try {
      await sleep(DELAY_MS);
      const ticket = await primeClient.buscarTicketPorTelefone(telefone);

      if (!ticket || ticket.status !== 'closed') {
        semTicketClosed++;
        continue;
      }

      await vtigerClient.atualizarLead(lead.id, { leadstatus: 'Perdido' });
      movidos++;
      console.log(`  OK  ${lead.firstname} ${lead.lastname} (criado ${lead.createdtime?.slice(0,10)}) → Perdido`);
    } catch (e) {
      erros++;
      console.log(`  ERRO  ${lead.firstname} ${lead.lastname}: ${e.message}`);
    }
  }

  console.log('\n=== RESULTADO ===');
  console.log(`Candidatos antes de hoje : ${todos.length}`);
  console.log(`Movidos para Perdido     : ${movidos}`);
  console.log(`Ticket não closed/ausente: ${semTicketClosed}`);
  console.log(`Erros                    : ${erros}`);
}

main().then(() => process.exit(0)).catch(e => { console.error('FATAL:', e.message); process.exit(1); });

/**
 * Script diagnóstico: verifica o status dos tickets no Prime
 * para leads presos em "Aguardando Atendimento"
 *
 * Uso: node -r dotenv/config scripts/diagnostico-aguardando.js
 */

const axios  = require('axios');
const crypto = require('crypto');
const primeClient = require('../src/primeatende/client');

const BASE_URL = () => `${process.env.VTIGER_URL}/webservice.php`;

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
  const sessionName = await autenticarVtiger();

  let offset = 0;
  const limit = 100;
  let todos = [];

  while (true) {
    const { data } = await axios.get(BASE_URL(), {
      params: {
        operation: 'query',
        sessionName,
        query: `SELECT id, firstname, lastname, phone, mobile, createdtime FROM Leads WHERE leadstatus='Aguardando Atendimento' LIMIT ${offset}, ${limit};`,
      },
    });
    const lote = data.result || [];
    todos = todos.concat(lote);
    if (lote.length < limit) break;
    offset += limit;
  }

  console.log(`Total "Aguardando Atendimento": ${todos.length}\n`);

  const contagem = { closed: 0, pending: 0, open: 0, semTicket: 0, outro: 0 };

  for (const lead of todos) {
    const telefone = (lead.phone || lead.mobile || '').replace(/\D/g, '');
    if (!telefone) { contagem.semTicket++; continue; }

    const ticket = await primeClient.buscarTicketPorTelefone(telefone);
    if (!ticket) { contagem.semTicket++; continue; }

    const s = ticket.status;
    if (s === 'closed')       contagem.closed++;
    else if (s === 'pending') contagem.pending++;
    else if (s === 'open')    contagem.open++;
    else                      contagem.outro++;
  }

  console.log('Status dos tickets no Prime Atende:');
  console.log(`  closed    (ticket encerrado, lead preso): ${contagem.closed}`);
  console.log(`  pending   (aguardando agente — correto) : ${contagem.pending}`);
  console.log(`  open      (agente atribuído)             : ${contagem.open}`);
  console.log(`  sem ticket no Prime                      : ${contagem.semTicket}`);
  console.log(`  outro status                             : ${contagem.outro}`);
}

main().then(() => process.exit(0)).catch(e => { console.error('FATAL:', e.message); process.exit(1); });

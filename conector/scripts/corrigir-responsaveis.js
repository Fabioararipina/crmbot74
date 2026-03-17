/**
 * Script one-time: corrige assigned_user_id de leads que ficaram como Admin (19x1)
 * porque o mapeamento agent_routing não estava configurado na época da criação.
 *
 * Uso: node -r dotenv/config scripts/corrigir-responsaveis.js
 */

const axios  = require('axios');
const crypto = require('crypto');

const vtigerClient   = require('../src/vtiger/client');
const primeClient    = require('../src/primeatende/client');
const { resolverVtigerUser } = require('../src/db/routing');

const BASE_URL = () => `${process.env.VTIGER_URL}/webservice.php`;
const DELAY_MS = 300; // intervalo entre chamadas para não sobrecarregar as APIs

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function queryVtiger(sessionName, query) {
  const { data } = await axios.get(BASE_URL(), {
    params: { operation: 'query', sessionName, query },
  });
  if (!data.success) throw new Error('query falhou: ' + data.error?.message);
  return data.result || [];
}

async function autenticarVtiger() {
  const { data: ch } = await axios.get(BASE_URL(), {
    params: { operation: 'getchallenge', username: process.env.VTIGER_USER },
  });
  const token     = ch.result.token;
  const accessKey = crypto.createHash('md5').update(token + process.env.VTIGER_KEY).digest('hex');
  const { data: login } = await axios.post(
    BASE_URL(),
    new URLSearchParams({ operation: 'login', username: process.env.VTIGER_USER, accessKey }).toString(),
    { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
  );
  if (!login.success) throw new Error('login falhou: ' + login.error?.message);
  return login.result.sessionName;
}

async function main() {
  console.log('=== Corrigindo responsáveis (Admin → agente real) ===\n');

  const sessionName = await autenticarVtiger();

  let offset = 0;
  const limit = 100;
  let total = 0, corrigidos = 0, semTicket = 0, semMapeamento = 0, erros = 0;

  while (true) {
    const leads = await queryVtiger(
      sessionName,
      `SELECT id, firstname, lastname, phone, mobile, leadstatus FROM Leads WHERE assigned_user_id='19x1' LIMIT ${offset}, ${limit};`
    );

    if (leads.length === 0) break;

    for (const lead of leads) {
      total++;
      const telefone = (lead.phone || lead.mobile || '').replace(/\D/g, '');

      if (!telefone) {
        console.log(`  SKIP  ${lead.firstname} ${lead.lastname} — sem telefone`);
        continue;
      }

      try {
        await sleep(DELAY_MS);
        const ticket = await primeClient.buscarTicketPorTelefone(telefone);

        if (!ticket || !ticket.userId) {
          semTicket++;
          continue;
        }

        const vtigerUserId = await resolverVtigerUser(ticket.userId);
        if (!vtigerUserId) {
          semMapeamento++;
          console.log(`  MAP?  ${lead.firstname} ${lead.lastname} — agente Prime ID ${ticket.userId} não mapeado`);
          continue;
        }

        await vtigerClient.atualizarLead(lead.id, { assigned_user_id: vtigerUserId });
        corrigidos++;
        console.log(`  OK    ${lead.firstname} ${lead.lastname} (${lead.leadstatus}) → ${vtigerUserId}`);
      } catch (e) {
        erros++;
        console.log(`  ERRO  ${lead.firstname} ${lead.lastname}: ${e.message}`);
      }
    }

    if (leads.length < limit) break;
    offset += limit;
  }

  console.log('\n=== RESULTADO ===');
  console.log(`Leads com Admin encontrados : ${total}`);
  console.log(`Corrigidos                  : ${corrigidos}`);
  console.log(`Sem ticket no Prime         : ${semTicket}`);
  console.log(`Agente não mapeado          : ${semMapeamento}`);
  console.log(`Erros                       : ${erros}`);
}

main()
  .then(() => process.exit(0))
  .catch(e => { console.error('\nFATAL:', e.message); process.exit(1); });

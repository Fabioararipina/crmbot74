#!/usr/bin/env node
/**
 * analise-isabela.js
 * Analisa os tickets fechados pela Isabela no Prime Atende:
 * - Quantas mensagens tinham
 * - Se o cliente chegou a responder
 * - Quanto tempo ficaram abertos
 */
require('dotenv').config();
const { Pool } = require('pg');

const pool = new Pool({
  host:     process.env.ISICHAT_DB_HOST,
  port:     5432,
  database: process.env.ISICHAT_DB_NAME,
  user:     process.env.ISICHAT_DB_USER,
  password: process.env.ISICHAT_DB_PASS,
  connectionTimeoutMillis: 8000,
});

async function main() {
  // ── Resumo geral dos tickets da Isabela ──────────────────────────────────
  const resumo = await pool.query(`
    SELECT
      COUNT(*)                                                      AS total_tickets,
      COUNT(CASE WHEN msgs_cliente = 0 THEN 1 END)                 AS sem_resposta_cliente,
      COUNT(CASE WHEN msgs_cliente > 0 THEN 1 END)                 AS com_resposta_cliente,
      ROUND(AVG(horas_aberto)::numeric, 1)                         AS media_horas_aberto,
      COUNT(CASE WHEN horas_aberto < 1 THEN 1 END)                 AS fechados_em_menos_1h,
      COUNT(CASE WHEN horas_aberto BETWEEN 1 AND 24 THEN 1 END)   AS fechados_1h_a_24h,
      COUNT(CASE WHEN horas_aberto > 24 THEN 1 END)                AS fechados_apos_24h
    FROM (
      SELECT
        t.id,
        DATE_PART('hour', t."updatedAt" - t."createdAt") AS horas_aberto,
        COUNT(CASE WHEN m."fromMe" = false THEN 1 END)   AS msgs_cliente
      FROM "Tickets" t
      LEFT JOIN "Messages" m ON m."ticketId" = t.id
      WHERE t."userId" = 47 AND t.status = 'closed'
      GROUP BY t.id, t."createdAt", t."updatedAt"
    ) sub
  `);

  // ── Últimos 30 tickets individuais ───────────────────────────────────────
  const tickets = await pool.query(`
    SELECT
      t.id,
      t."createdAt"::date                                          AS aberto_em,
      FLOOR(DATE_PART('hour', t."updatedAt" - t."createdAt"))     AS horas_aberto,
      COUNT(m.id)                                                  AS total_msgs,
      COUNT(CASE WHEN m."fromMe" = false THEN 1 END)              AS msgs_cliente,
      COUNT(CASE WHEN m."fromMe" = true  THEN 1 END)              AS msgs_agente,
      (SELECT body FROM "Messages"
       WHERE "ticketId" = t.id AND "fromMe" = false
       ORDER BY "createdAt" ASC LIMIT 1)                          AS primeira_msg_cliente
    FROM "Tickets" t
    LEFT JOIN "Messages" m ON m."ticketId" = t.id
    WHERE t."userId" = 47 AND t.status = 'closed'
    GROUP BY t.id, t."createdAt", t."updatedAt"
    ORDER BY t.id DESC
    LIMIT 30
  `);

  // ── Exibir ───────────────────────────────────────────────────────────────
  const r = resumo.rows[0];
  console.log('\n═══════════════════════════════════════════════════════');
  console.log('  ANÁLISE TICKETS ISABELA (userId=47) — fechados');
  console.log('═══════════════════════════════════════════════════════');
  console.log(`  Total tickets fechados     : ${r.total_tickets}`);
  console.log(`  Sem nenhuma resposta do cliente : ${r.sem_resposta_cliente}`);
  console.log(`  Com resposta do cliente    : ${r.com_resposta_cliente}`);
  console.log(`  Média de horas aberto      : ${r.media_horas_aberto}h`);
  console.log(`  Fechados em < 1h           : ${r.fechados_em_menos_1h}`);
  console.log(`  Fechados entre 1h e 24h    : ${r.fechados_1h_a_24h}`);
  console.log(`  Fechados após 24h          : ${r.fechados_apos_24h}`);
  console.log('───────────────────────────────────────────────────────');

  console.log('\n  Últimos 30 tickets (mais recentes primeiro):');
  console.log('  ID      | Data       | Horas | Msgs | Cliente | Agente | 1ª msg cliente');
  console.log('  ' + '-'.repeat(90));

  for (const t of tickets.rows) {
    const primeiraMsg = t.primeira_msg_cliente
      ? t.primeira_msg_cliente.substring(0, 30).replace(/\n/g, ' ')
      : '(sem resposta)';
    console.log(
      `  ${String(t.id).padEnd(7)} | ${t.aberto_em} | ${String(t.horas_aberto + 'h').padEnd(5)} | ${String(t.total_msgs).padEnd(4)} | ${String(t.msgs_cliente).padEnd(7)} | ${String(t.msgs_agente).padEnd(6)} | ${primeiraMsg}`
    );
  }

  console.log('\n═══════════════════════════════════════════════════════\n');
  await pool.end();
}

main().catch(e => { console.error('ERRO:', e.message); pool.end(); });

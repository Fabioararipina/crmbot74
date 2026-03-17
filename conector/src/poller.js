const vtigerClient = require('./vtiger/client');
const primeClient  = require('./primeatende/client');
const { resolverVtigerUser } = require('./db/routing');
const logger = require('./logger');

// Hierarquia de status — status só avança, nunca regride (exceto dormentes)
const STATUS_ORDEM = {
  'Novo':                   1,
  'Aguardando Atendimento': 2,
  'Em Atendimento':         3,  // inbound: agente respondeu ao cliente
  'Tentativa de Contato':   3,  // outbound: clínica tomou iniciativa, aguardando cliente
  'Contactado':             4,
  'Confirmado':             5,
  'Cancelado':              6,
  'Perdido':                8,  // cliente não quis avançar (dormente — pode voltar ao funil)
  'Descartado':             9,  // sem resposta após várias tentativas (dormente)
  'Erro':                   0,
};

// Status dormentes: podem "acordar" se houver nova atividade
const STATUS_DORMENTES = ['Descartado', 'Perdido'];

// Deriva o status vTiger a partir dos dados do ticket do Prime Atende.
// Lógica baseada em direção de mensagem (fromMe) + status atual do lead:
//   - sem agente (pending ou open sem userId)                         → Aguardando Atendimento
//   - agente assumiu, sem msgs ainda                                  → Tentativa de Contato
//   - cliente respondeu (lastMsgFromMe=false)                         → Contactado
//   - agente falou por último, lead vinha de inbound (ou dormente)   → Em Atendimento
//   - agente falou por último, lead vinha de outbound                → Tentativa de Contato
function derivarStatus(ticket, statusAtual) {
  if (!ticket) return null;
  // Ticket encerrado — consultor avança manualmente
  if (ticket.status === 'closed') return null;
  if (ticket.status === 'pending' || !ticket.userId) return 'Aguardando Atendimento';
  // Agente assumiu mas ainda sem mensagens trocadas
  if (ticket.last_msg_from_me === null || ticket.last_msg_from_me === undefined) return 'Tentativa de Contato';
  // Cliente respondeu
  if (ticket.last_msg_from_me === false) return 'Contactado';
  // Agente falou por último: diferencia inbound de outbound pelo status atual
  if (statusAtual === 'Aguardando Atendimento' || STATUS_DORMENTES.includes(statusAtual)) {
    return 'Em Atendimento';
  }
  return 'Tentativa de Contato';
}

const INTERVAL_MS = 3 * 60 * 1000; // 3 minutos

// Verifica leads nos status que ainda podem avançar automaticamente
const STATUS_VERIFICAR = ['Novo', 'Aguardando Atendimento', 'Em Atendimento', 'Tentativa de Contato'];

async function sincronizarLeadsPendentes() {
  try {
    // Busca leads em todos os status sequencialmente (evita conflito de autenticação vTiger)
    const leads = [];
    for (const s of STATUS_VERIFICAR) {
      const resultado = await vtigerClient.buscarLeadsPorStatus(s);
      leads.push(...resultado);
    }

    if (!leads.length) return;

    logger.info(`Poller: verificando ${leads.length} leads (${STATUS_VERIFICAR.join(', ')})`);

    for (const lead of leads) {
      try {
        const telefoneRaw = lead.phone || lead.mobile;
        if (!telefoneRaw) continue;
        // Normaliza: garante código do Brasil (55) e apenas dígitos
        const digits = telefoneRaw.replace(/\D/g, '');
        const telefone = digits.startsWith('55') ? digits : `55${digits}`;

        const ticket = await primeClient.buscarTicketPorTelefone(telefone);
        if (!ticket) continue;

        const novoStatus  = derivarStatus(ticket, lead.leadstatus);
        const updates = {};

        // Avança status — dormentes podem retornar ao funil
        if (novoStatus) {
          const ordemAtual = STATUS_ORDEM[lead.leadstatus] || 0;
          const ordemNovo  = STATUS_ORDEM[novoStatus] || 0;
          const isDormente = STATUS_DORMENTES.includes(lead.leadstatus);
          if (isDormente || ordemNovo > ordemAtual) updates.leadstatus = novoStatus;
        }

        // Atualiza responsável se agente mudou (só em tickets ativos)
        if (ticket.userId && ticket.status !== 'closed') {
          const vtigerUserId = await resolverVtigerUser(ticket.userId);
          if (vtigerUserId && vtigerUserId !== lead.assigned_user_id) {
            updates.assigned_user_id = vtigerUserId;
          }
        }

        // Atualiza descrição com última mensagem e agente (só se ticket ativo)
        if (ticket && ticket.status !== 'closed') {
          updates.description = `Canal: WhatsApp | Fila: ${ticket.queue_name || '-'} | Agente: ${ticket.agent_name || 'sem agente'} | Ticket #${ticket.id}${ticket.last_client_msg ? `\nÚltima msg: "${ticket.last_client_msg}"` : ''}`;
        }

        if (Object.keys(updates).length > 0) {
          await vtigerClient.atualizarLead(lead.id, updates);
          logger.info(`Poller: lead atualizado`, {
            leadId: lead.id,
            telefone,
            updates: {
              ...updates,
              description: updates.description ? '(atualizado)' : undefined,
            },
          });
        }
      } catch (err) {
        logger.warn(`Poller: erro ao processar lead`, { leadId: lead.id, erro: err.message });
      }
    }
  } catch (err) {
    logger.error('Poller: erro geral', { erro: err.message });
  }
}

function iniciarPoller() {
  logger.info(`Poller iniciado — intervalo: ${INTERVAL_MS / 1000}s`);
  setTimeout(() => {
    sincronizarLeadsPendentes();
    setInterval(sincronizarLeadsPendentes, INTERVAL_MS);
  }, 30000);
}

module.exports = { iniciarPoller, derivarStatus };

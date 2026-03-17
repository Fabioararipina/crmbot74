const { filaDisparo, filaSyncLead, filaHistorico } = require('./index');
const vtigerClient = require('../vtiger/client');
const primeClient  = require('../primeatende/client');
const bot74Client  = require('../bot74/client');
const { resolverUsuario, resolverVtigerUser, resolverContaPorUsuario } = require('../db/routing');
const { derivarStatus } = require('../poller');
const logger = require('../logger');

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

const RETRY_OPTS = {
  attempts: 3,
  backoff: { type: 'exponential', delay: 1000 }, // 1s → 5s → 30s
};

// ─── Worker: disparo (alta prioridade) ────────────────────────────────────────
filaDisparo.process(async job => {
  const { leadId, telefone, template, sistema, connectionId } = job.data;
  logger.info(`Disparando mensagem`, { leadId, sistema, template, connectionId });

  // Se lead está dormente (Descartado/Perdido), reativa para Tentativa de Contato antes do disparo
  if (telefone) {
    const leadAtual = await vtigerClient.buscarLeadPorTelefone(telefone);
    if (leadAtual && STATUS_DORMENTES.includes(leadAtual.leadstatus)) {
      await vtigerClient.atualizarLead(leadAtual.id, { leadstatus: 'Tentativa de Contato' });
      logger.info(`Disparo: lead dormente reativado`, { leadId: leadAtual.id, statusAnterior: leadAtual.leadstatus });
    }
  }

  let resultado;
  if (sistema === 'prime') {
    // connectionId garante que a resposta sai pelo mesmo número que o lead usou
    resultado = await primeClient.enviarMensagem({ numero: telefone, template, connectionId });
  } else if (sistema === 'bot74') {
    try {
      resultado = await bot74Client.disparar({ numero: telefone, template, nome: undefined });
    } catch (err) {
      // Bot74 ainda não integrado para este template — registra e ignora sem retry
      if (err?.response?.status === 404) {
        logger.warn('Bot74: endpoint não encontrado — disparo ignorado', { leadId, template });
        return { status: 'ignorado', motivo: 'bot74_404' };
      }
      throw err;
    }
  } else {
    throw new Error(`Sistema desconhecido: ${sistema}`);
  }

  // Registra resultado no histórico do lead
  await filaHistorico.add({ tipo: 'disparo', leadId, sistema, template, resultado }, RETRY_OPTS);

  return resultado;
});

// ─── Worker: sync-lead (média prioridade) ─────────────────────────────────────
filaSyncLead.process(async job => {
  const { acao, dados } = job.data;
  logger.info(`Sync lead`, { acao, leadId: dados.leadId || dados.telefone });

  if (acao === 'primeatende-para-vtiger') {
    // Busca ticket via banco PostgreSQL do IsiChat (API não filtra por telefone)
    const [ticket, leadExistente] = await Promise.all([
      primeClient.buscarTicketPorTelefone(dados.telefone),
      vtigerClient.buscarLeadPorTelefone(dados.telefone),
    ]);
    const novoStatus = derivarStatus(ticket, leadExistente?.leadstatus);
    logger.info('Ticket Prime buscado', { telefone: dados.telefone, ticketId: ticket?.id, ticketStatus: ticket?.status, agente: ticket?.agent_name || null, lastMsgFromMe: ticket?.last_msg_from_me ?? null, novoStatus: novoStatus || '(sem alteração)' });

    const descricaoTicket = ticket
      ? `Canal: WhatsApp | Fila: ${ticket.queue_name || '-'} | Agente: ${ticket.agent_name || 'sem agente'} | Ticket #${ticket.id}${ticket.last_client_msg ? `\nÚltima msg: "${ticket.last_client_msg}"` : ''}`
      : `Canal: WhatsApp | Conexão: ${dados.connectionId || 'padrão'}`;

    if (leadExistente) {
      const updates = {};

      // Avança status — dormentes podem retornar ao funil
      if (novoStatus) {
        const ordemAtual = STATUS_ORDEM[leadExistente.leadstatus] || 0;
        const ordemNovo  = STATUS_ORDEM[novoStatus] || 0;
        const isDormente = STATUS_DORMENTES.includes(leadExistente.leadstatus);
        if (isDormente || ordemNovo > ordemAtual) updates.leadstatus = novoStatus;
      }

      // Atualiza responsável conforme agente do ticket (só em tickets ativos)
      if (ticket?.userId && ticket.status !== 'closed') {
        const vtigerUserId = await resolverVtigerUser(ticket.userId);
        if (vtigerUserId && vtigerUserId !== leadExistente.assigned_user_id) {
          updates.assigned_user_id = vtigerUserId;
        }
      }

      // Atualiza descrição com dados do ticket
      if (ticket) updates.description = descricaoTicket;

      if (Object.keys(updates).length > 0) {
        await vtigerClient.atualizarLead(leadExistente.id, updates);
      }

      await filaHistorico.add({
        tipo: 'atividade',
        leadId: leadExistente.id,
        descricao: `Interação via Prime Atende — Ticket #${ticket?.id || dados.ticketId || '-'} — ${dados.connectionNome || dados.connectionId || 'padrão'}`,
      }, RETRY_OPTS);
    } else {
      const assignedUserId = await resolverUsuario(dados.connectionId, dados.conta);
      await vtigerClient.criarLead({
        firstname: dados.nome || 'Contato',
        lastname: dados.telefone,
        phone: dados.telefone,
        leadsource: 'Chat',
        leadstatus: novoStatus || 'Aguardando Atendimento',
        description: descricaoTicket,
        assigned_user_id: assignedUserId,
      });
    }
  }

  if (acao === 'vtiger-para-primeatende') {
    // Determina a conta Prime pelo usuário vTiger responsável pelo lead
    const conta = await resolverContaPorUsuario(dados.assignedUserId);
    const contato = await primeClient.buscarContatoPorTelefone(dados.telefone, null, conta);
    if (!contato) {
      const criado = await primeClient.criarContato({ nome: dados.nome, numero: dados.telefone, contaFallback: conta });
      if (!criado) return; // número sem WhatsApp — ignora silenciosamente
    }
  }
});

// ─── Worker: historico (baixa prioridade) ─────────────────────────────────────
filaHistorico.process(async job => {
  const { tipo, leadId, descricao, sistema, template, resultado } = job.data;

  if (tipo === 'atividade' && leadId) {
    await vtigerClient.criarAtividade({
      leadId,
      descricao: descricao || `Disparo via ${sistema}: ${template} — ${resultado?.status || 'ok'}`,
    });
  }

  if (tipo === 'disparo' && leadId) {
    // sem campos cf_* — registrar apenas via comentário (criarAtividade já feito acima)
  }
});

logger.info('Workers das filas iniciados (disparo, sync-lead, historico)');

const router = require('express').Router();
const { filaSyncLead, filaHistorico } = require('../queues');
const logger = require('../logger');

const RETRY_OPTS = {
  attempts: 3,
  backoff: { type: 'exponential', delay: 1000 },
};

// POST /webhook/primeatende
// Recebe eventos do Prime Atende (novo ticket, nova mensagem, encerrado)
router.post('/primeatende', async (req, res) => {
  try {
    const payload = req.body;

    // Log completo para diagnóstico
    logger.info('=== PAYLOAD Prime Atende ===');
    logger.info(JSON.stringify(payload, null, 2));
    logger.info('============================');

    // Formato 1 — Baileys/Prime Atende raw message:
    // { key: { remoteJid, fromMe, sender_pn }, pushName, message: { conversation } }
    const isBaileysFormat = payload.key && payload.message;

    // Formato 2 — Whaticket ticket event:
    // { acao, sender, chamadoId, ticketData: { id, status, contact, whatsappId } }
    const acao = payload.acao || payload.event;
    const ticketData = payload.ticketData || payload.ticket || {};

    let dados;

    if (isBaileysFormat) {
      // Ignora mensagens enviadas por nós
      if (payload.key.fromMe) {
        return res.json({ ok: true, aviso: 'mensagem própria ignorada' });
      }
      const senderPn = payload.key.sender_pn || payload.key.remoteJid || '';
      const telefone = senderPn.replace('@s.whatsapp.net', '').replace('@lid', '').replace(/\D/g, '');
      dados = {
        telefone,
        nome: payload.pushName || telefone,
        ticketId: null,
        contatoId: null,
        connectionId: '',
        conta: ['clinica', 'odonto'].includes(req.query.conta) ? req.query.conta : '', // whitelist
        // Baileys = mensagem raw, sem status de ticket — assume aguardando atendimento
        ticketStatus: 'pending',
        ticketUserId: null,
      };
    } else {
      dados = {
        telefone: payload.sender || ticketData.contact?.number,
        nome: ticketData.contact?.name,
        ticketId: payload.chamadoId || ticketData.id,
        contatoId: ticketData.contact?.id,
        fila: ticketData.queue?.name,
        connectionId: String(ticketData.whatsappId || payload.defaultWhatsapp_x || ''),
        // Status do ticket já vem no payload — sem necessidade de buscar na API
        ticketStatus: ticketData.status || null,
        ticketUserId: ticketData.userId || ticketData.user?.id || null,
      };
    }

    if (!dados.telefone) {
      logger.warn('Webhook sem telefone — ignorado', { payload });
      return res.json({ ok: true, aviso: 'sem telefone' });
    }

    // Processa: formato Baileys trata qualquer mensagem como interação
    if (isBaileysFormat || acao === 'start' || acao === 'ticket:created') {
      await filaSyncLead.add({ acao: 'primeatende-para-vtiger', dados }, { ...RETRY_OPTS, priority: 2 });
      logger.info('Lead enfileirado para sync', { telefone: dados.telefone });
    }

    if (acao === 'closed' || acao === 'ticket:closed') {
      await filaHistorico.add({
        tipo: 'atividade',
        telefone: dados.telefone,
        descricao: `Ticket Prime Atende #${dados.ticketId} encerrado`,
      }, RETRY_OPTS);
    }

    res.json({ ok: true, acao: acao || 'message' });
  } catch (err) {
    logger.error('Erro webhook Prime Atende', { erro: err.message });
    res.status(500).json({ ok: false, erro: err.message });
  }
});

// POST /webhook/bot74
// Recebe resultado de disparos do Bot74
router.post('/bot74', async (req, res) => {
  try {
    const { disparoId, numero, campanha, status, leadId } = req.body;
    logger.info('Webhook Bot74 recebido', { disparoId, status, campanha });

    if (leadId) {
      await filaHistorico.add({
        tipo: 'disparo',
        leadId,
        sistema: 'bot74',
        template: campanha,
        resultado: { status, disparoId },
      }, RETRY_OPTS);
    }

    res.json({ ok: true });
  } catch (err) {
    logger.error('Erro webhook Bot74', { erro: err.message });
    res.status(500).json({ ok: false, erro: err.message });
  }
});

module.exports = router;

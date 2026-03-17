const router = require('express').Router();
const axios = require('axios');
const { filaDisparo, filaSyncLead } = require('../queues');
const primeClient = require('../primeatende/client');
const vtigerClient = require('../vtiger/client');
const logger = require('../logger');

const RETRY_OPTS = {
  attempts: 3,
  backoff: { type: 'exponential', delay: 1000 },
};

// POST /vtiger/disparo
// Chamado por Workflow do vTiger para disparar mensagem via Prime Atende ou Bot74
router.post('/disparo', async (req, res) => {
  try {
    const { leadId, telefone, template, sistema } = req.body;

    if (!telefone || !template || !sistema) {
      return res.status(400).json({ ok: false, erro: 'telefone, template e sistema são obrigatórios' });
    }

    logger.info('Disparo solicitado pelo vTiger', { leadId, sistema, template });

    const job = await filaDisparo.add(
      { leadId, telefone, template, sistema },
      { ...RETRY_OPTS, priority: 1 }
    );

    res.json({ ok: true, jobId: job.id });
  } catch (err) {
    logger.error('Erro /vtiger/disparo', { erro: err.message });
    res.status(500).json({ ok: false, erro: err.message });
  }
});

// POST /vtiger/lead-criado
// Chamado por Workflow do vTiger quando um lead é criado manualmente
router.post('/lead-criado', async (req, res) => {
  try {
    const { leadId, nome, telefone } = req.body;

    if (!telefone) {
      return res.status(400).json({ ok: false, erro: 'telefone é obrigatório' });
    }

    // Busca o assigned_user_id diretamente do vTiger pelo leadId
    let assignedUserId = null;
    if (leadId) {
      try {
        const lead = await vtigerClient.buscarPorId(leadId);
        assignedUserId = lead?.assigned_user_id || null;
      } catch (_) {}
    }

    logger.info('Lead criado no vTiger — sincronizando para Prime Atende', { leadId, telefone, assignedUserId });

    const job = await filaSyncLead.add({
      acao: 'vtiger-para-primeatende',
      dados: { leadId, nome, telefone, assignedUserId },
    }, { ...RETRY_OPTS, priority: 2 });

    res.json({ ok: true, jobId: job.id });
  } catch (err) {
    logger.error('Erro /vtiger/lead-criado', { erro: err.message });
    res.status(500).json({ ok: false, erro: err.message });
  }
});

// GET /vtiger/status-lead?telefone=X
// Chamado pelo painel PHP do vTiger para exibir dados do Prime Atende e Bot74
router.get('/status-lead', async (req, res) => {
  const { telefone } = req.query;
  if (!telefone) {
    return res.status(400).json({ ok: false, erro: 'telefone é obrigatório' });
  }

  const result = { primeAtende: null, bot74: null };

  // Consulta Prime Atende (conta principal)
  try {
    const contato = await primeClient.buscarContatoPorTelefone(telefone, 23); // conta principal
    if (!contato) {
      // tenta conta odontologia
      const contatoOdonto = await primeClient.buscarContatoPorTelefone(telefone, 22);
      result.primeAtende = { contato: contatoOdonto };
    } else {
      result.primeAtende = { contato };
    }
  } catch (err) {
    logger.warn('status-lead: erro Prime Atende', { err: err.message });
    result.primeAtende = { contato: null, erro: err.message };
  }

  // Consulta Bot74
  try {
    const bot74Res = await axios.get(
      `${process.env.BOT74_URL}/internal/lead-status`,
      {
        params: { numero: telefone },
        headers: { 'x-bot74-key': process.env.BOT74_INTERNAL_KEY },
        timeout: 5000,
      }
    );
    result.bot74 = bot74Res.data;
  } catch (err) {
    logger.warn('status-lead: erro Bot74', { err: err.message });
    result.bot74 = { leads: [], erro: err.message };
  }

  res.json(result);
});

module.exports = router;

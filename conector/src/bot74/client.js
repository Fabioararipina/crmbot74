const axios = require('axios');
const logger = require('../logger');

// Mapa template → UUID da campanha no Bot74
// Preencher após criar as campanhas no painel Bot74 e copiar os IDs aqui no .env
const CAMPANHAS = {
  reativacao_7dias: process.env.BOT74_CAMPANHA_REATIVACAO,
  followup_3dias:   process.env.BOT74_CAMPANHA_FOLLOWUP,
  envio_proposta:   process.env.BOT74_CAMPANHA_PROPOSTA,
};

async function disparar({ numero, template, campanhaId: campanhaIdOverride, nome }) {
  const campanhaId = campanhaIdOverride || CAMPANHAS[template];

  if (!campanhaId) {
    throw new Error(
      `Bot74: campanha não mapeada para template "${template}". ` +
      `Configure BOT74_CAMPANHA_${(template || '').toUpperCase()} no .env do Conector.`
    );
  }

  const { data } = await axios.post(
    `${process.env.BOT74_URL}/internal/disparar`,
    { numero, nome: nome || numero, campanhaId },
    {
      headers: { 'x-bot74-key': process.env.BOT74_INTERNAL_KEY },
      timeout: 10000,
    }
  );

  logger.info('Bot74: lead adicionado à campanha', { numero, template, campanhaId });
  return data;
}

module.exports = { disparar };

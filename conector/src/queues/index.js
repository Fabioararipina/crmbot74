const Bull = require('bull');
const logger = require('../logger');

const redisOpts = { redis: process.env.REDIS_URL || 'redis://localhost:6379' };

const filaDisparo   = new Bull('disparo',   redisOpts);
const filaSyncLead  = new Bull('sync-lead', redisOpts);
const filaHistorico = new Bull('historico', redisOpts);

// Log de eventos de fila
[filaDisparo, filaSyncLead, filaHistorico].forEach(fila => {
  fila.on('completed', job => logger.debug(`Fila [${fila.name}] job #${job.id} concluído`));
  fila.on('failed', (job, err) => logger.error(`Fila [${fila.name}] job #${job.id} falhou`, { erro: err.message }));
  fila.on('stalled', job => logger.warn(`Fila [${fila.name}] job #${job.id} travado`));
});

module.exports = { filaDisparo, filaSyncLead, filaHistorico };

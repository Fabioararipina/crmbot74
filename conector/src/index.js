require('dotenv').config();
const express = require('express');
const rateLimit = require('express-rate-limit');
const logger = require('./logger');
const { init: initRouting } = require('./db/routing');

const app = express();
app.set('trust proxy', 1); // necessário para rate limit funcionar atrás de tunnel/proxy
app.use(express.json());
app.use(express.urlencoded({ extended: false }));

// Rate limiting: max 10 req/s por IP
app.use(rateLimit({
  windowMs: 1000,
  max: 10,
  message: { ok: false, erro: 'Rate limit excedido' },
}));

// Autenticação por API Key
// /webhook/* → autenticado por secret na URL (?secret=xxx) — Prime Atende não suporta headers customizados
// /vtiger/*  → autenticado por header x-conector-key (chamado internamente pelo vTiger)
// /health    → público
app.use((req, res, next) => {
  // Rotas sem API key global (têm auth própria)
  if (req.path === '/health') return next();
  if (req.path.startsWith('/admin/')) return next();

  if (req.path.startsWith('/webhook/')) {
    const secret = req.query.secret;
    if (!secret || secret !== process.env.CONECTOR_API_KEY) {
      return res.status(401).json({ ok: false, erro: 'Secret inválido' });
    }
    return next();
  }

  const key = req.headers['x-conector-key'];
  if (!key || key !== process.env.CONECTOR_API_KEY) {
    return res.status(401).json({ ok: false, erro: 'Chave de API inválida' });
  }
  next();
});

// Rotas
app.use('/webhook', require('./routes/webhook'));
app.use('/vtiger',  require('./routes/vtiger'));
app.use('/admin',   require('./routes/admin'));

// Health check
app.get('/health', (req, res) => res.json({ ok: true }));

// Inicia DB, workers e poller
initRouting().catch(err => logger.error('Falha ao inicializar DB routing', { err: err.message }));
require('./queues/workers');
require('./poller').iniciarPoller();

const PORT = process.env.PORT || 3010;
app.listen(PORT, () => {
  logger.info(`Conector crmbot74 rodando na porta ${PORT}`);
});

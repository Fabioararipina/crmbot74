const router = require('express').Router();
const crypto = require('crypto');
const routing = require('../db/routing');
const vtigerClient = require('../vtiger/client');
const primeClient  = require('../primeatende/client');
const logger = require('../logger');

// ─── Helpers de segurança ──────────────────────────────────────────────────────

// Escapa caracteres HTML para prevenir XSS
function esc(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;');
}

// CSRF stateless: HMAC(senha_admin, minuto_atual) — válido por 2 minutos
function gerarCsrf() {
  const minuto = Math.floor(Date.now() / 60000);
  return crypto.createHmac('sha256', process.env.ADMIN_PASSWORD || '')
    .update(String(minuto))
    .digest('hex');
}

function verificarCsrf(token) {
  const minuto = Math.floor(Date.now() / 60000);
  // Aceita token do minuto atual ou do anterior (janela de 2 min)
  const tokenAtual   = crypto.createHmac('sha256', process.env.ADMIN_PASSWORD || '').update(String(minuto)).digest('hex');
  const tokenAnterior = crypto.createHmac('sha256', process.env.ADMIN_PASSWORD || '').update(String(minuto - 1)).digest('hex');
  return token === tokenAtual || token === tokenAnterior;
}

// ─── Autenticação Basic ────────────────────────────────────────────────────────
router.use((req, res, next) => {
  const auth = req.headers.authorization || '';
  const b64  = auth.replace('Basic ', '');
  const [, senha] = Buffer.from(b64, 'base64').toString().split(':');
  if (senha && senha === process.env.ADMIN_PASSWORD) return next();

  res.set('WWW-Authenticate', 'Basic realm="CRM Admin"');
  res.status(401).send('Acesso restrito');
});

// ─── Helpers HTML ──────────────────────────────────────────────────────────────
function layout(titulo, corpo) {
  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>${titulo} — CRM Bot74</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,sans-serif;background:#f5f5f5;color:#333;padding:24px}
    h1{font-size:1.4rem;margin-bottom:20px;color:#1a1a2e}
    h2{font-size:1rem;margin:28px 0 12px;color:#444}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px #0001}
    th{background:#1a1a2e;color:#fff;padding:10px 14px;text-align:left;font-size:.85rem}
    td{padding:10px 14px;border-bottom:1px solid #eee;font-size:.9rem;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    .badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:600}
    .on{background:#d4edda;color:#155724}.off{background:#f8d7da;color:#721c24}
    form.add-form{background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 4px #0001;display:grid;gap:12px;max-width:600px}
    label{font-size:.85rem;color:#555;display:block;margin-bottom:4px}
    input,select{width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:6px;font-size:.9rem}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .actions{display:flex;gap:8px}
    button,a.btn{display:inline-block;padding:8px 16px;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:600;text-decoration:none}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-danger{background:#dc3545;color:#fff}
    .btn-toggle-on{background:#28a745;color:#fff}.btn-toggle-off{background:#6c757d;color:#fff}
    .msg{padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:.9rem}
    .msg-ok{background:#d4edda;color:#155724}.msg-err{background:#f8d7da;color:#721c24}
    .hint{font-size:.78rem;color:#888;margin-top:4px}
  </style>
</head>
<body>
${corpo}
</body></html>`;
}

// ─── GET /admin/roteamento ─────────────────────────────────────────────────────
router.get('/roteamento', async (req, res) => {
  try {
    const [regras, usuarios] = await Promise.all([
      routing.listar(),
      vtigerClient.listarUsuarios(),
    ]);

    const msg = req.query.ok
      ? `<div class="msg msg-ok">${esc(req.query.ok)}</div>`
      : req.query.erro
      ? `<div class="msg msg-err">${esc(req.query.erro)}</div>`
      : '';

    const csrf = gerarCsrf();

    const opcoesUsuario = usuarios
      .map(u => `<option value="${esc(u.id)}">${esc(u.first_name || '')} ${esc(u.last_name || '')} (${esc(u.user_name)}) — ${esc(u.id)}</option>`)
      .join('');

    const linhasTabela = regras.map(r => `
      <tr>
        <td>${esc(r.id)}</td>
        <td><code>${esc(r.connection_id)}</code></td>
        <td>${esc(r.connection_nome)}</td>
        <td><span class="badge ${r.conta === 'odonto' ? 'off' : 'on'}">${r.conta === 'odonto' ? 'Odonto' : 'Principal'}</span></td>
        <td><code>${esc(r.vtiger_user_id)}</code></td>
        <td>${esc(r.vtiger_user_nome)}</td>
        <td><span class="badge ${r.ativo ? 'on' : 'off'}">${r.ativo ? 'Ativo' : 'Inativo'}</span></td>
        <td>
          <div class="actions">
            <form method="POST" action="/admin/roteamento/${esc(r.id)}/toggle">
              <input type="hidden" name="_csrf" value="${csrf}">
              <button class="btn ${r.ativo ? 'btn-toggle-on' : 'btn-toggle-off'}" type="submit">
                ${r.ativo ? 'Desativar' : 'Ativar'}
              </button>
            </form>
            <form method="POST" action="/admin/roteamento/${esc(r.id)}/remover" onsubmit="return confirm('Remover esta regra?')">
              <input type="hidden" name="_csrf" value="${csrf}">
              <button class="btn btn-danger" type="submit">Remover</button>
            </form>
          </div>
        </td>
      </tr>`).join('');

    const corpo = `
<h1>Roteamento WhatsApp → vTiger</h1>
${msg}
<p style="font-size:.85rem;color:#666;margin-bottom:20px">
  Cada regra define: quando um lead chega pela conexão WhatsApp indicada, ele é atribuído ao usuário vTiger correspondente.
</p>

<h2>Regras configuradas</h2>
${regras.length === 0
  ? '<p style="color:#999;font-size:.9rem">Nenhuma regra cadastrada. Adicione abaixo.</p>'
  : `<table>
      <thead>
        <tr><th>#</th><th>Connection ID</th><th>Nome da Conexão</th><th>Conta Prime</th><th>User vTiger ID</th><th>Nome no vTiger</th><th>Status</th><th>Ações</th></tr>
      </thead>
      <tbody>${linhasTabela}</tbody>
    </table>`}

<h2>Adicionar nova regra</h2>
<form class="add-form" method="POST" action="/admin/roteamento">
  <input type="hidden" name="_csrf" value="${csrf}">
  <div class="row">
    <div>
      <label>Connection ID (Prime Atende)</label>
      <input name="connection_id" placeholder="ex: 22" required>
      <p class="hint">ID numérico da conexão WhatsApp no Prime Atende</p>
    </div>
    <div>
      <label>Nome da Conexão</label>
      <input name="connection_nome" placeholder="ex: ODONTOLOGIA" required>
    </div>
  </div>
  <div>
    <label>Conta Prime Atende</label>
    <select name="conta" required>
      <option value="principal">Principal (Clínica / Cardio / Cartão)</option>
      <option value="odonto">Odontologia</option>
    </select>
    <p class="hint">Conta do Prime Atende que gerencia esta conexão WhatsApp</p>
  </div>
  <div>
    <label>Usuário vTiger Responsável</label>
    <select name="vtiger_user_id" required>
      <option value="">— selecione —</option>
      ${opcoesUsuario}
    </select>
    <p class="hint">Este usuário receberá os novos leads desta conexão</p>
  </div>
  <div>
    <button class="btn btn-primary" type="submit">Salvar regra</button>
  </div>
</form>

<p style="margin-top:32px;font-size:.78rem;color:#aaa">
  CRM Bot74 — Painel Admin — As regras entram em vigor em até 5 minutos (cache)
</p>`;

    res.send(layout('Roteamento WhatsApp', corpo));
  } catch (err) {
    logger.error('Admin roteamento GET', { erro: err.message });
    res.status(500).send('Erro ao carregar painel: ' + err.message);
  }
});

// ─── POST /admin/roteamento — adicionar regra ──────────────────────────────────
router.post('/roteamento', async (req, res) => {
  if (!verificarCsrf(req.body._csrf)) {
    return res.redirect('/admin/roteamento?erro=Token+CSRF+inv%C3%A1lido.+Recarregue+a+p%C3%A1gina.');
  }
  try {
    const { connection_id, connection_nome, conta, vtiger_user_id } = req.body;
    if (!connection_id || !vtiger_user_id) {
      return res.redirect('/admin/roteamento?erro=Preencha+Connection+ID+e+usuário+vTiger');
    }
    // Busca o nome do usuário diretamente no vTiger
    let vtiger_user_nome = '';
    try {
      const usuarios = await vtigerClient.listarUsuarios();
      const u = usuarios.find(x => x.id === vtiger_user_id);
      if (u) vtiger_user_nome = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.user_name;
    } catch (_) {}
    await routing.inserir({ connection_id: connection_id.trim(), connection_nome, conta, vtiger_user_id, vtiger_user_nome });
    res.redirect('/admin/roteamento?ok=Regra+adicionada+com+sucesso');
  } catch (err) {
    logger.error('Admin roteamento POST', { erro: err.message });
    res.redirect('/admin/roteamento?erro=' + encodeURIComponent(err.message));
  }
});

// ─── POST /admin/roteamento/:id/toggle ────────────────────────────────────────
router.post('/roteamento/:id/toggle', async (req, res) => {
  if (!verificarCsrf(req.body._csrf)) {
    return res.redirect('/admin/roteamento?erro=Token+CSRF+inv%C3%A1lido.+Recarregue+a+p%C3%A1gina.');
  }
  try {
    await routing.alternarAtivo(req.params.id);
    res.redirect('/admin/roteamento?ok=Status+alterado');
  } catch (err) {
    res.redirect('/admin/roteamento?erro=' + encodeURIComponent(err.message));
  }
});

// ─── POST /admin/roteamento/:id/remover ───────────────────────────────────────
router.post('/roteamento/:id/remover', async (req, res) => {
  if (!verificarCsrf(req.body._csrf)) {
    return res.redirect('/admin/roteamento?erro=Token+CSRF+inv%C3%A1lido.+Recarregue+a+p%C3%A1gina.');
  }
  try {
    await routing.remover(req.params.id);
    res.redirect('/admin/roteamento?ok=Regra+removida');
  } catch (err) {
    res.redirect('/admin/roteamento?erro=' + encodeURIComponent(err.message));
  }
});

// ─── GET /admin/agentes ────────────────────────────────────────────────────────
router.get('/agentes', async (req, res) => {
  try {
    const [agentes, usuariosVtiger, usuariosPrime] = await Promise.all([
      routing.listarAgentes(),
      vtigerClient.listarUsuarios(),
      primeClient.listarUsuarios().catch(() => []),
    ]);

    const msg = req.query.ok
      ? `<div class="msg msg-ok">${esc(req.query.ok)}</div>`
      : req.query.erro
      ? `<div class="msg msg-err">${esc(req.query.erro)}</div>`
      : '';

    const csrf = gerarCsrf();

    // IDs já mapeados para não mostrar no dropdown
    const idsMapeados = new Set(agentes.map(a => String(a.prime_user_id)));

    const opcoesPrime = usuariosPrime
      .filter(u => !idsMapeados.has(String(u.id)))
      .map(u => `<option value="${esc(u.id)}" data-nome="${esc(u.name)}">${esc(u.name)} (ID: ${esc(u.id)})</option>`)
      .join('');

    const opcoesVtiger = usuariosVtiger
      .map(u => `<option value="${esc(u.id)}">${esc(u.first_name || '')} ${esc(u.last_name || '')} (${esc(u.user_name)})</option>`)
      .join('');

    const linhasTabela = agentes.map(a => `
      <tr>
        <td>${esc(a.prime_user_nome)}</td>
        <td>→</td>
        <td>${esc(a.vtiger_user_nome)}</td>
        <td>
          <form method="POST" action="/admin/agentes/${esc(a.id)}/remover" onsubmit="return confirm('Remover?')">
            <input type="hidden" name="_csrf" value="${csrf}">
            <button class="btn btn-danger" type="submit">Remover</button>
          </form>
        </td>
      </tr>`).join('');

    const corpo = `
<h1>Mapeamento de Agentes Prime ↔ vTiger</h1>
${msg}
<p style="font-size:.85rem;color:#666;margin-bottom:20px">
  Quando um agente assumir um ticket no Prime Atende, o lead no vTiger será automaticamente atribuído ao usuário correspondente. Também é atualizado ao transferir.
</p>

<h2>Mapeamentos configurados</h2>
${agentes.length === 0
  ? '<p style="color:#999;font-size:.9rem">Nenhum agente mapeado ainda.</p>'
  : `<table>
      <thead><tr><th>Agente Prime</th><th></th><th>Usuário vTiger</th><th>Ações</th></tr></thead>
      <tbody>${linhasTabela}</tbody>
    </table>`}

<h2>Adicionar mapeamento</h2>
${opcoesPrime === ''
  ? '<p style="color:#999;font-size:.9rem">Todos os agentes já estão mapeados.</p>'
  : `<form class="add-form" method="POST" action="/admin/agentes">
  <input type="hidden" name="_csrf" value="${csrf}">
  <div class="row">
    <div>
      <label>Agente no Prime Atende</label>
      <select name="prime_user_id" id="sel-prime" required>
        <option value="">— selecione —</option>
        ${opcoesPrime}
      </select>
    </div>
    <div>
      <label>Usuário no vTiger</label>
      <select name="vtiger_user_id" required>
        <option value="">— selecione —</option>
        ${opcoesVtiger}
      </select>
    </div>
  </div>
  <input type="hidden" name="prime_user_nome" id="inp-prime-nome">
  <div>
    <button class="btn btn-primary" type="submit" onclick="document.getElementById('inp-prime-nome').value=document.getElementById('sel-prime').selectedOptions[0]?.dataset.nome||''">Salvar</button>
  </div>
</form>`}

<p style="margin-top:32px;font-size:.78rem;color:#aaa">
  CRM Bot74 — Painel Admin — <a href="/admin/roteamento" style="color:#aaa">← Roteamento WhatsApp</a>
</p>`;

    res.send(layout('Agentes', corpo));
  } catch (err) {
    logger.error('Admin agentes GET', { erro: err.message });
    res.status(500).send('Erro: ' + err.message);
  }
});

// ─── POST /admin/agentes ───────────────────────────────────────────────────────
router.post('/agentes', async (req, res) => {
  if (!verificarCsrf(req.body._csrf)) {
    return res.redirect('/admin/agentes?erro=Token+CSRF+inv%C3%A1lido.+Recarregue+a+p%C3%A1gina.');
  }
  try {
    const { prime_user_id, prime_user_nome, vtiger_user_id } = req.body;
    if (!prime_user_id || !vtiger_user_id) {
      return res.redirect('/admin/agentes?erro=Preencha+todos+os+campos');
    }
    const usuarios = await vtigerClient.listarUsuarios();
    const u = usuarios.find(x => x.id === vtiger_user_id);
    const vtiger_user_nome = u ? (`${u.first_name || ''} ${u.last_name || ''}`.trim() || u.user_name) : '';
    await routing.inserirAgente({ prime_user_id: parseInt(prime_user_id), prime_user_nome, vtiger_user_id, vtiger_user_nome });
    res.redirect('/admin/agentes?ok=Agente+mapeado+com+sucesso');
  } catch (err) {
    res.redirect('/admin/agentes?erro=' + encodeURIComponent(err.message));
  }
});

// ─── POST /admin/agentes/:id/remover ──────────────────────────────────────────
router.post('/agentes/:id/remover', async (req, res) => {
  if (!verificarCsrf(req.body._csrf)) {
    return res.redirect('/admin/agentes?erro=Token+CSRF+inv%C3%A1lido.+Recarregue+a+p%C3%A1gina.');
  }
  try {
    await routing.removerAgente(req.params.id);
    res.redirect('/admin/agentes?ok=Agente+removido');
  } catch (err) {
    res.redirect('/admin/agentes?erro=' + encodeURIComponent(err.message));
  }
});

module.exports = router;

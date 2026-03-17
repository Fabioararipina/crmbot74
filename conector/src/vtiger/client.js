const axios = require('axios');
const crypto = require('crypto');
const logger = require('../logger');

const BASE_URL = () => `${process.env.VTIGER_URL}/webservice.php`;

// Valida e sanitiza número de telefone — apenas dígitos, máx 15 chars
function sanitizarTelefone(telefone) {
  const limpo = String(telefone || '').replace(/\D/g, '').slice(0, 15);
  if (!limpo) throw new Error('Telefone inválido: vazio após sanitização');
  return limpo;
}

// Escapa aspas simples para interpolação em queries vTiger (não suporta prepared statements)
function escapeVtigerString(valor) {
  return String(valor || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

let _sessionId = null;
let _sessionExpiry = null;

// Todas as operações de escrita no vTiger devem ser enviadas como form-data
function formData(params) {
  return new URLSearchParams(params);
}

async function autenticar() {
  if (_sessionId && _sessionExpiry && Date.now() < _sessionExpiry) {
    return _sessionId;
  }

  const { data: ch } = await axios.get(BASE_URL(), {
    params: { operation: 'getchallenge', username: process.env.VTIGER_USER },
  });
  if (!ch.success) throw new Error('vTiger getchallenge falhou: ' + ch.error?.message);

  const token = ch.result.token;
  const accessKey = crypto.createHash('md5').update(token + process.env.VTIGER_KEY).digest('hex');

  const { data: login } = await axios.post(BASE_URL(),
    formData({ operation: 'login', username: process.env.VTIGER_USER, accessKey })
  );
  if (!login.success) throw new Error('vTiger login falhou: ' + login.error?.message);

  _sessionId = login.result.sessionName;
  _sessionExpiry = Date.now() + 25 * 60 * 1000;
  logger.debug('vTiger: sessão renovada');
  return _sessionId;
}

async function criarLead(dados) {
  const sessionName = await autenticar();
  const { data } = await axios.post(BASE_URL(), formData({
    operation: 'create',
    sessionName,
    elementType: 'Leads',
    element: JSON.stringify({ assigned_user_id: '19x1', ...dados }),
  }));
  if (!data.success) throw new Error('vTiger criarLead falhou: ' + data.error?.message);
  logger.info('vTiger: lead criado', { id: data.result.id, telefone: dados.phone });
  return data.result;
}

async function buscarLeadPorTelefone(telefone) {
  const sessionName = await autenticar();
  const tel = escapeVtigerString(sanitizarTelefone(telefone));
  const query = `SELECT * FROM Leads WHERE phone='${tel}' OR mobile='${tel}' LIMIT 1;`;
  const { data } = await axios.get(BASE_URL(), {
    params: { operation: 'query', sessionName, query },
  });
  if (!data.success) throw new Error('vTiger buscarLead falhou: ' + data.error?.message);
  return data.result?.[0] || null;
}

async function atualizarLead(id, campos) {
  const sessionName = await autenticar();
  const leadAtual = await buscarPorId(id);
  const { data } = await axios.post(BASE_URL(), formData({
    operation: 'update',
    sessionName,
    elementType: 'Leads',
    element: JSON.stringify({ ...leadAtual, ...campos, id }),
  }));
  if (!data.success) throw new Error('vTiger atualizarLead falhou: ' + data.error?.message);
  return data.result;
}

async function buscarPorId(id) {
  const sessionName = await autenticar();
  const { data } = await axios.get(BASE_URL(), {
    params: { operation: 'retrieve', sessionName, id },
  });
  if (!data.success) throw new Error('vTiger retrieve falhou: ' + data.error?.message);
  return data.result;
}

async function criarAtividade({ leadId, descricao }) {
  const sessionName = await autenticar();
  const { data } = await axios.post(BASE_URL(), formData({
    operation: 'create',
    sessionName,
    elementType: 'ModComments',
    element: JSON.stringify({ commentcontent: descricao, related_to: leadId, assigned_user_id: '19x1' }),
  }));
  if (!data.success) throw new Error('vTiger criarAtividade falhou: ' + data.error?.message);
  return data.result;
}

// vTiger API limita 100 registros por query — pagina até buscar todos
async function buscarLeadsPorStatus(status) {
  const sessionName = await autenticar();
  const PAGE = 100;
  const todos = [];
  let offset = 0;
  while (true) {
    const { data } = await axios.get(BASE_URL(), {
      params: {
        operation: 'query',
        sessionName,
        query: `SELECT id, phone, mobile, leadstatus FROM Leads WHERE leadstatus='${escapeVtigerString(status)}' LIMIT ${offset}, ${PAGE};`,
      },
    });
    const pagina = (data.success && data.result) ? data.result : [];
    todos.push(...pagina);
    if (pagina.length < PAGE) break;
    offset += PAGE;
  }
  return todos;
}

async function listarUsuarios() {
  const sessionName = await autenticar();
  const { data } = await axios.get(BASE_URL(), {
    params: {
      operation: 'query',
      sessionName,
      query: 'SELECT id, user_name, first_name, last_name FROM Users LIMIT 50;',
    },
  });
  if (!data.success) return [];
  return data.result || [];
}

module.exports = { criarLead, buscarLeadPorTelefone, atualizarLead, buscarPorId, criarAtividade, listarUsuarios, buscarLeadsPorStatus };

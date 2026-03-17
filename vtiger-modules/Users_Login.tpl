{*
 * crmbot74 — Login Page Customizada
 * Montado em: /var/www/html/layouts/v7/modules/Users/Login.tpl
 *}
{strip}
<style>
  html, body {
    margin: 0 !important;
    padding: 0 !important;
    height: 100% !important;
    overflow: hidden !important;
    background: #0a0f1e !important;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
  }

  #bot74-login {
    position: fixed;
    inset: 0;
    display: flex;
    z-index: 9999;
  }

  /* ── Painel Esquerdo ─────────────────────────────────────── */
  .b74-left {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px 48px;
    background: linear-gradient(145deg, #0a0f1e 0%, #0d1f12 60%, #0a1a10 100%);
    position: relative;
    overflow: hidden;
  }

  .b74-left::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,201,94,0.13) 0%, transparent 70%);
    top: -100px; right: -100px;
    pointer-events: none;
  }

  .b74-left::after {
    content: '';
    position: absolute;
    width: 380px; height: 380px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,201,94,0.08) 0%, transparent 70%);
    bottom: -80px; left: -80px;
    pointer-events: none;
  }

  .b74-logo { margin-bottom: 48px; text-align: center; position: relative; z-index: 1; }
  .b74-logo img { width: 200px; filter: drop-shadow(0 0 24px rgba(37,201,94,0.4)); }
  .b74-tagline { color: #25c95e; font-size: 0.82rem; font-weight: 600; letter-spacing: 3px; text-transform: uppercase; margin-top: 10px; }

  .b74-features { list-style: none; width: 100%; max-width: 380px; position: relative; z-index: 1; }
  .b74-features li {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.06); color: #c8d8c8;
  }
  .b74-features li:last-child { border-bottom: none; }

  .b74-fi {
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    background: rgba(37,201,94,0.12); border: 1px solid rgba(37,201,94,0.25);
    display: flex; align-items: center; justify-content: center; font-size: 18px;
  }

  .b74-ft strong { display: block; color: #e8f5e8; font-size: 1.08rem; margin-bottom: 2px; }
  .b74-ft span { font-size: 0.92rem; color: #8aab8a; line-height: 1.4; }

  /* ── Painel Direito ──────────────────────────────────────── */
  .b74-right {
    width: 460px; flex-shrink: 0;
    display: flex; flex-direction: column; justify-content: center; align-items: center;
    padding: 60px 48px; background: #f7faf7; position: relative;
  }

  .b74-card { width: 100%; max-width: 360px; }
  .b74-card h2 { font-size: 1.75rem; font-weight: 700; color: #0a1a10; margin-bottom: 6px; }
  .b74-card .b74-sub { color: #6b8f6b; font-size: 0.88rem; margin-bottom: 32px; }

  .b74-err {
    background: #fef0f0; border: 1px solid #fca5a5; color: #c0392b;
    padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px;
    display: {if $ERROR}block{else}none{/if};
  }

  .b74-field { margin-bottom: 18px; }
  .b74-field label {
    display: block; font-size: 0.78rem; font-weight: 600; color: #2d5a2d;
    margin-bottom: 6px; letter-spacing: 0.5px; text-transform: uppercase;
  }
  .b74-field input {
    width: 100%; padding: 12px 16px; border: 1.5px solid #d1e8d1; border-radius: 10px;
    font-size: 0.95rem; color: #1a2e1a; background: #fff;
    transition: border-color .2s, box-shadow .2s; outline: none;
  }
  .b74-field input:focus { border-color: #25c95e; box-shadow: 0 0 0 3px rgba(37,201,94,0.15); }

  .b74-btn {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, #25c95e 0%, #1ba84e 100%);
    color: #fff; font-size: 1rem; font-weight: 700; letter-spacing: 0.5px;
    border: none; border-radius: 10px; cursor: pointer;
    transition: opacity .2s, transform .1s; margin-top: 6px;
  }
  .b74-btn:hover { opacity: 0.91; }
  .b74-btn:active { transform: scale(0.99); }

  .b74-forgot { display: block; text-align: center; margin-top: 16px; color: #25c95e; font-size: 0.83rem; text-decoration: none; cursor: pointer; }
  .b74-forgot:hover { text-decoration: underline; }

  #b74-recover { display: none; width: 100%; max-width: 360px; }
  #b74-recover h2 { font-size: 1.4rem; color: #0a1a10; margin-bottom: 6px; }
  #b74-recover .b74-sub { color: #6b8f6b; font-size: 0.88rem; margin-bottom: 28px; }

  .b74-back { display: inline-flex; align-items: center; gap: 6px; color: #25c95e; font-size: 0.85rem; text-decoration: none; margin-top: 14px; cursor: pointer; }
  .b74-back:hover { text-decoration: underline; }

  .b74-footer { position: absolute; bottom: 20px; font-size: 0.72rem; color: #aac4aa; }

  @media (max-width: 768px) {
    #bot74-login { flex-direction: column; overflow-y: auto; position: fixed; }
    .b74-left { flex: none; padding: 36px 24px 28px; }
    .b74-left::before, .b74-left::after { display: none; }
    .b74-features { display: none; }
    .b74-logo img { width: 140px; }
    .b74-right { width: 100%; min-height: 100vh; }
  }
</style>

<div id="bot74-login">

  <!-- Painel Esquerdo -->
  <div class="b74-left">
    <div class="b74-logo">
      <img src="assets/logo-bot74.png" alt="Bot74 CRM">
      <div class="b74-tagline">CRM Inteligente</div>
    </div>

    <ul class="b74-features">
      <li>
        <div class="b74-fi">📲</div>
        <div class="b74-ft">
          <strong>Leads do WhatsApp em tempo real</strong>
          <span>Cada mensagem recebida cria ou atualiza um lead automaticamente no CRM.</span>
        </div>
      </li>
      <li>
        <div class="b74-fi">🤖</div>
        <div class="b74-ft">
          <strong>Campanhas automáticas com Bot74</strong>
          <span>Reativação, follow-up e envio de propostas disparados por workflows.</span>
        </div>
      </li>
      <li>
        <div class="b74-fi">🔀</div>
        <div class="b74-ft">
          <strong>Roteamento inteligente por equipe</strong>
          <span>Leads da Clínica e Odontologia atribuídos ao responsável correto.</span>
        </div>
      </li>
      <li>
        <div class="b74-fi">📊</div>
        <div class="b74-ft">
          <strong>Funil de vendas completo</strong>
          <span>Do primeiro contato até a confirmação, tudo rastreado e histórico.</span>
        </div>
      </li>
    </ul>
  </div>

  <!-- Painel Direito -->
  <div class="b74-right">

    <div class="b74-card" id="b74-login-form">
      <h2>Bem-vindo</h2>
      <p class="b74-sub">Acesse o painel de gestão de leads</p>

      <div class="b74-err" id="b74-err-msg">
        {if $ERROR}{$MESSAGE|default:'Usuário ou senha incorretos.'}{/if}
      </div>

      <form method="POST" action="index.php" id="b74-form">
        <input type="hidden" name="module" value="Users">
        <input type="hidden" name="action" value="Login">

        <div class="b74-field">
          <label for="b74-user">Usuário</label>
          <input type="text" id="b74-user" name="username" placeholder="Digite seu usuário" autocomplete="username">
        </div>
        <div class="b74-field">
          <label for="b74-pass">Senha</label>
          <input type="password" id="b74-pass" name="password" placeholder="••••••••" autocomplete="current-password">
        </div>

        <button type="submit" class="b74-btn">Entrar</button>
      </form>

      <a class="b74-forgot" id="b74-show-forgot">Esqueceu a senha?</a>
    </div>

    <div id="b74-recover">
      <h2>Recuperar Senha</h2>
      <p class="b74-sub">Informe seu usuário e e-mail cadastrado</p>

      <form method="POST" action="forgotPassword.php">
        <div class="b74-field">
          <label for="b74-fuser">Usuário</label>
          <input type="text" id="b74-fuser" name="username" placeholder="Digite seu usuário">
        </div>
        <div class="b74-field">
          <label for="b74-email">E-mail</label>
          <input type="email" id="b74-email" name="emailId" placeholder="seuemail@exemplo.com">
        </div>
        <button type="submit" class="b74-btn">Enviar</button>
      </form>

      <a class="b74-back" id="b74-back">&#8592; Voltar ao login</a>
    </div>

    <span class="b74-footer">Bot74 CRM &copy; {$smarty.now|date_format:'%Y'} Essencial Saúde</span>
  </div>

</div>

<script>
  document.getElementById('b74-show-forgot').onclick = function(e) {
    e.preventDefault();
    document.getElementById('b74-login-form').style.display = 'none';
    document.getElementById('b74-recover').style.display = 'block';
  };
  document.getElementById('b74-back').onclick = function(e) {
    e.preventDefault();
    document.getElementById('b74-recover').style.display = 'none';
    document.getElementById('b74-login-form').style.display = 'block';
  };
  document.getElementById('b74-form').onsubmit = function(e) {
    var u = document.getElementById('b74-user').value.trim();
    var p = document.getElementById('b74-pass').value;
    var err = document.getElementById('b74-err-msg');
    if (!u || !p) {
      e.preventDefault();
      err.style.display = 'block';
      err.textContent = !u ? 'Informe o usuário.' : 'Informe a senha.';
    }
  };
  document.getElementById('b74-user').focus();
</script>
{/strip}

<?php require_once '../includes/session_init.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<?php session_idle_script(); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Entre em contato com o suporte do App Controle Pro e acompanhe sua solicitação por protocolo.">
  <meta name="theme-color" content="#075dd8">
  <title>Suporte | App Controle Pro</title>
  <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
  <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/universal-theme.css">
  <link rel="stylesheet" href="../assets/css/accessibility-widget.css?v=20260805-icons">
  <script src="../assets/js/global-theme.js?v=20260805-icons"></script>
  <script src="../assets/js/accessibility-widget.js?v=20260805-icons" defer></script>
  <style>
    :root{--support-brand:#075dd8;--support-brand-dark:#0647a6;--support-text:#172033;--support-muted:#617086;--support-bg:#f4f8fd;--support-card:#fff;--support-line:#d9e4f0;--support-soft:#eaf3ff}
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{min-height:100vh;margin:0;display:flex;flex-direction:column;background:var(--support-bg);color:var(--support-text);font-family:Inter,"Segoe UI",Arial,sans-serif}
    .skip-link{position:fixed;left:12px;top:10px;z-index:20000;transform:translateY(-160%);background:var(--support-brand);color:#fff;padding:10px 15px;border-radius:8px;font-weight:800;text-decoration:none}.skip-link:focus{transform:translateY(0)}
    .site-header{position:sticky;top:0;z-index:1040;background:rgba(255,255,255,.96);border-bottom:1px solid var(--support-line);backdrop-filter:blur(12px)}
    .header-inner{width:min(1180px,calc(100% - 36px));min-height:74px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:22px}
    .brand{display:inline-flex;align-items:center;gap:10px;color:var(--support-brand);font-size:1.18rem;font-weight:850;text-decoration:none}.brand:hover{color:var(--support-brand-dark)}
    .header-actions{display:flex;align-items:center;gap:10px}
    .theme-button,.back-button{height:44px;border-radius:10px;border:1px solid #b9c9dc;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:750;text-decoration:none;cursor:pointer}
    .theme-button{width:44px;background:#fff;color:var(--support-text);padding:0}.back-button{background:var(--support-brand);border-color:var(--support-brand);color:#fff;padding:0 17px}.back-button:hover{background:var(--support-brand-dark);color:#fff}
    main{flex:1;padding:58px 18px 66px}
    .support-shell{width:min(1080px,100%);margin:auto}
    .support-heading{text-align:center;margin-bottom:34px}.support-kicker{display:inline-flex;align-items:center;gap:7px;color:var(--support-brand);font-size:.78rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.support-heading h1{margin:9px 0 10px;font-size:clamp(2rem,5vw,3.15rem);line-height:1.08;letter-spacing:-.04em}.support-heading p{max-width:680px;margin:auto;color:var(--support-muted);font-size:1.05rem;line-height:1.65}
    .support-grid{display:grid;grid-template-columns:minmax(0,.82fr) minmax(0,1.35fr);gap:24px;align-items:stretch}
    .support-info,.support-form-card{border:1px solid var(--support-line);border-radius:20px;background:var(--support-card);box-shadow:0 18px 45px rgba(25,55,90,.09)}
    .support-info{padding:30px;background:linear-gradient(150deg,#075dd8,#0647a6);color:#fff;display:flex;flex-direction:column}.support-info h2{font-size:1.35rem;margin:0 0 10px}.support-info>p{margin:0 0 25px;color:#dbeaff;line-height:1.6}
    .info-list{display:grid;gap:14px}.info-item{display:flex;gap:13px;align-items:flex-start;padding:15px;border:1px solid rgba(255,255,255,.2);border-radius:13px;background:rgba(255,255,255,.09)}.info-icon{width:38px;height:38px;flex:0 0 38px;display:grid;place-items:center;border-radius:10px;background:rgba(255,255,255,.14)}.info-item strong,.info-item span{display:block}.info-item strong{font-size:.92rem}.info-item span{margin-top:3px;color:#dbeaff;font-size:.83rem;line-height:1.4}
    .privacy-note{margin-top:auto;padding-top:24px;color:#dbeaff;font-size:.8rem;line-height:1.5}.privacy-note i{margin-right:6px}
    .support-form-card{padding:30px}.form-title{display:flex;align-items:center;gap:11px;margin-bottom:24px}.form-title-icon{width:44px;height:44px;border-radius:12px;background:var(--support-soft);color:var(--support-brand);display:grid;place-items:center;font-size:1.1rem}.form-title h2{margin:0;font-size:1.3rem}.form-title p{margin:3px 0 0;color:var(--support-muted);font-size:.86rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:17px}.field{display:flex;flex-direction:column;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:.88rem;font-weight:750}.optional{color:var(--support-muted);font-weight:500}.field input,.field textarea{width:100%;border:1px solid #cbd8e6;border-radius:10px;background:#fff;color:var(--support-text);font:inherit;padding:12px 13px;outline:none;transition:.2s}.field textarea{min-height:132px;resize:vertical;line-height:1.5}.field input:focus,.field textarea:focus{border-color:var(--support-brand);box-shadow:0 0 0 3px rgba(7,93,216,.13)}
    .form-help{color:var(--support-muted);font-size:.77rem}.submit-button{width:100%;min-height:48px;margin-top:19px;border:0;border-radius:10px;background:linear-gradient(135deg,#075dd8,#00aee9);color:#fff;font-weight:800;font-size:1rem;cursor:pointer;box-shadow:0 10px 24px rgba(7,93,216,.2)}.submit-button:hover{background:linear-gradient(135deg,#0647a6,#0697cb);transform:translateY(-1px)}.submit-button:disabled{opacity:.7;cursor:wait;transform:none}
    .form-status{display:none;margin-bottom:18px;padding:12px 14px;border-radius:10px;font-weight:700;font-size:.9rem;line-height:1.45}.form-status.success{display:block;background:#e7f7ee;border:1px solid #a5d8bb;color:#126d39}.form-status.error{display:block;background:#fff0f0;border:1px solid #f0b5b5;color:#a12626}
    .site-footer{background:#fff;border-top:1px solid var(--support-line);padding:30px 0}.footer-inner{width:min(1180px,calc(100% - 36px));margin:auto;display:grid;grid-template-columns:1.3fr 1fr 1fr;align-items:center;gap:25px}.footer-brand{font-weight:800}.footer-brand i{color:var(--support-brand);margin-right:7px}.footer-copy,.footer-description{color:var(--support-muted);font-size:.8rem;line-height:1.5}.footer-links{display:flex;justify-content:center;gap:18px;flex-wrap:wrap}.footer-links a{color:#465a72;text-decoration:none;font-weight:650;font-size:.86rem}.footer-links a:hover{color:var(--support-brand)}.footer-copy{text-align:right}
    body.dark-mode{--support-text:#eef4fb;--support-muted:#aab8ca;--support-bg:#0c111b;--support-card:#152032;--support-line:#2c3a4e;--support-soft:#1d3450}.dark-mode .site-header{background:rgba(12,17,27,.96)}.dark-mode .theme-button{background:#152032;color:#eef4fb;border-color:#33445b}.dark-mode .support-form-card{box-shadow:0 18px 45px rgba(0,0,0,.25)}.dark-mode .field input,.dark-mode .field textarea{background:#202c3d;border-color:#3c4c61;color:#fff}.dark-mode .site-footer{background:#111821}.dark-mode .footer-links a{color:#bdc9d8}.dark-mode .footer-links a:hover{color:#66d2ff}
    @media(max-width:820px){main{padding-top:40px}.support-grid{grid-template-columns:1fr}.support-info{min-height:auto}.privacy-note{margin-top:10px}.footer-inner{grid-template-columns:1fr;text-align:center}.footer-links{order:2}.footer-copy{text-align:center;order:3}}
    @media(max-width:560px){.header-inner{width:calc(100% - 24px)}.brand span{display:none}.back-button span{display:none}.back-button{width:44px;padding:0}.support-form-card,.support-info{padding:22px;border-radius:16px}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.site-footer{padding-bottom:90px}}
  </style>
</head>
<body data-accessibility-widget>
  <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>

  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="../index.php" aria-label="App Controle Pro - página inicial"><i class="fa-regular fa-folder"></i><span>App Controle Pro</span></a>
      <div class="header-actions">
        <a class="back-button" href="../index.php"><i class="fa-solid fa-arrow-left"></i><span>Voltar para a index</span></a>
        <button type="button" class="theme-button" id="darkModeToggle" aria-label="Alternar tema" title="Alternar modo claro e escuro"><i class="fa-solid fa-moon"></i></button>
      </div>
    </div>
  </header>

  <main id="conteudo-principal" tabindex="-1">
    <div class="support-shell">
      <div class="support-heading">
        <span class="support-kicker"><i class="fa-solid fa-headset"></i> Atendimento</span>
        <h1>Como podemos ajudar?</h1>
        <p>Envie sua dúvida ou solicitação. Você receberá um protocolo para acompanhar o atendimento com mais segurança.</p>
      </div>

      <div class="support-grid">
        <aside class="support-info" aria-labelledby="informacoesSuporte">
          <h2 id="informacoesSuporte">Atendimento organizado</h2>
          <p>As informações abaixo ajudam nossa equipe a compreender sua solicitação e responder pelo canal informado.</p>
          <div class="info-list">
            <div class="info-item"><div class="info-icon"><i class="fa-solid fa-ticket"></i></div><div><strong>Protocolo automático</strong><span>Guarde o número apresentado depois do envio.</span></div></div>
            <div class="info-item"><div class="info-icon"><i class="fa-solid fa-envelope"></i></div><div><strong>Resposta por e-mail</strong><span>Use um endereço que você consulte com frequência.</span></div></div>
            <div class="info-item"><div class="info-icon"><i class="fa-solid fa-user-shield"></i></div><div><strong>Dados protegidos</strong><span>Envie somente as informações necessárias para o atendimento.</span></div></div>
          </div>
          <div class="privacy-note"><i class="fa-solid fa-lock"></i>Não informe senhas, códigos de acesso ou dados bancários na mensagem.</div>
        </aside>

        <section class="support-form-card" aria-labelledby="tituloFormulario">
          <div class="form-title"><div class="form-title-icon"><i class="fa-solid fa-paper-plane"></i></div><div><h2 id="tituloFormulario">Abrir solicitação</h2><p>Campos marcados como opcionais podem ficar em branco.</p></div></div>
          <div id="formStatus" class="form-status" role="status" aria-live="polite"></div>
          <form id="suporteForm" action="../actions/enviar_suporte.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-grid">
              <div class="field"><label for="nome">Nome <span class="optional">(opcional)</span></label><input type="text" id="nome" name="nome" maxlength="100" autocomplete="name"></div>
              <div class="field"><label for="whatsapp">WhatsApp <span class="optional">(opcional)</span></label><input type="tel" id="whatsapp" name="whatsapp" maxlength="30" autocomplete="tel" placeholder="(27) 99999-9999"></div>
              <div class="field full"><label for="email">E-mail</label><input type="email" id="email" name="email" maxlength="190" autocomplete="email" required><span class="form-help">A resposta sobre o chamado será encaminhada para este endereço.</span></div>
              <div class="field full"><label for="mensagem">Mensagem</label><textarea id="mensagem" name="mensagem" minlength="10" maxlength="3000" required placeholder="Descreva sua dúvida ou o que aconteceu..."></textarea><span class="form-help">Entre 10 e 3.000 caracteres. Não inclua informações confidenciais.</span></div>
            </div>
            <button type="submit" class="submit-button" id="enviarSuporte"><i class="fa-solid fa-paper-plane"></i> Enviar solicitação</button>
          </form>
        </section>
      </div>
    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-inner">
      <div><div class="footer-brand"><i class="fa-regular fa-folder"></i>App Controle Pro</div><div class="footer-description">Gestão integrada para pessoas físicas, autônomos, MEIs e empresas.</div></div>
      <nav class="footer-links" aria-label="Links do rodapé"><a href="../index.php">Início</a><a href="protecao_de_dados.php">Proteção de dados</a><a href="login.php">Área do cliente</a></nav>
      <div class="footer-copy">&copy; <?= date('Y') ?> App Controle Pro.<br>Todos os direitos reservados.</div>
    </div>
  </footer>

  <script>
    const suporteForm = document.getElementById('suporteForm');
    const formStatus = document.getElementById('formStatus');
    const enviarSuporte = document.getElementById('enviarSuporte');

    suporteForm.addEventListener('submit', async function(event) {
      event.preventDefault();
      if (!suporteForm.reportValidity()) return;
      const textoOriginal = enviarSuporte.innerHTML;
      enviarSuporte.disabled = true;
      enviarSuporte.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';
      formStatus.className = 'form-status';
      formStatus.textContent = '';
      try {
        const response = await fetch(suporteForm.action, {method:'POST', body:new FormData(suporteForm), credentials:'same-origin', headers:{'Accept':'application/json'}});
        const data = await response.json();
        if (!response.ok || data.status !== 'success') throw new Error(data.msg || 'Não foi possível enviar a mensagem.');
        formStatus.className = 'form-status success';
        formStatus.textContent = `Chamado registrado! Seu protocolo é ${data.protocolo}. Guarde este número.`;
        suporteForm.reset();
        formStatus.scrollIntoView({behavior:'smooth', block:'center'});
      } catch (error) {
        formStatus.className = 'form-status error';
        formStatus.textContent = error.message || 'Não foi possível enviar a mensagem.';
      } finally {
        enviarSuporte.disabled = false;
        enviarSuporte.innerHTML = textoOriginal;
      }
    });
  </script>
</body>
</html>

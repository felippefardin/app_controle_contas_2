<?php 
// pages/registro.php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../database.php'; // Flash messages

// --- RECUPERA DADOS ANTIGOS EM CASO DE ERRO ---
$old = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); 

// --- AJUSTE: SE VIER PELA URL (DA INDEX) ---
if (isset($_GET['plano'])) {
    $old['plano'] = $_GET['plano'];
}

display_flash_message();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro - App Controle de Contas</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<style>
  body { background-color: #121212; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
  
  .form-container { 
      display: flex; 
      justify-content: center; 
      align-items: center; 
      min-height: calc(100vh - 120px); 
      padding: 20px; 
      box-sizing: border-box; 
      width: 100%;
  }
  
  form { 
      background-color: #1f1f1f; 
      padding: 30px 40px; 
      border-radius: 12px; 
      max-width: 850px; 
      width: 100%; 
      box-sizing: border-box; 
      border: 1px solid rgba(0, 191, 255, 0.2); 
      box-shadow: 0 0 25px rgba(0, 191, 255, 0.08); 
  }

  h2 { text-align: center; margin-bottom: 20px; color: #00bfff; border-bottom: 2px solid #00bfff; padding-bottom: 10px; }
  label { display: block; margin-top: 15px; font-weight: bold; font-size: 0.95rem; color: #ccc; }
  
  select, input:not([type="radio"]):not([type="checkbox"]) { 
      width: 100%; 
      padding: 12px; 
      margin-top: 6px; 
      border-radius: 6px; 
      border: 1px solid #333; 
      background-color: #222; 
      color: #eee; 
      font-size: 1rem; 
      box-sizing: border-box; 
  }
  
  input:focus, select:focus { outline: 2px solid #00bfff; background-color: #333; color: #fff; }
  
  .input-group { position: relative; width: 100%; }
  .input-group input { padding-right: 40px; }
  .toggle-password { position: absolute; top: 50%; right: 12px; transform: translateY(-50%); color: #aaa; cursor: pointer; padding: 5px; }
  
  .rules, .error-message { font-size: 0.85rem; margin-top: 5px; }
  .error-message { color: #ff4d4d; }
  
  .btn-submit { width: 100%; margin-top: 25px; padding: 12px 16px; border: none; border-radius: 8px; background-color: #28a745; color: #fff; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; }
  .btn-submit:hover { background-color: #218838; }
  
  .login-link { text-align: center; margin-top: 15px; }
  .login-link a { color: #00bfff; text-decoration: none; }

  /* CSS DOS PLANOS */
  .planos-container { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
  .plano-card { flex: 1; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; padding: 15px; cursor: pointer; transition: all 0.3s; position: relative; min-width: 200px; }
  .plano-card:hover { border-color: #00bfff; background: #333; }
  .plano-card input[type="radio"] { display: none; }
  .plano-card.selected { border-color: #00bfff; background: #2c3e50; box-shadow: 0 0 10px rgba(0, 191, 255, 0.2); }
  .plano-titulo { color: #00bfff; font-weight: bold; font-size: 1.1rem; display: block; margin-bottom: 5px; }
  .plano-preco { font-size: 1.2rem; color: #fff; font-weight: bold; display: block; margin-bottom: 5px; }
  .plano-desc { font-size: 0.85rem; color: #bbb; line-height: 1.4; }
  .trial-badge { background: #ffc107; color: #000; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: bold; position: absolute; top: 10px; right: 10px; }

  /* CSS BENEFICIOS */
  .benefits-section { margin-top: 25px; border-top: 1px solid #333; padding-top: 20px; }
  .benefit-toggle { cursor: pointer; color: #ff9f43; font-weight: bold; display: flex; align-items: center; gap: 8px; }
  .benefit-content { display: none; background: #252525; padding: 15px; border-radius: 8px; margin-top: 10px; border: 1px solid #444; }
  .benefit-content.open { display: block; }
  
  .btn-check { background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-top: 6px; font-size: 0.9rem; height: 46px; }
  .btn-check:hover { filter: brightness(1.1); }
  
  .valid-msg { color: #2ecc71; font-size: 0.85rem; margin-top: 5px; display: block; }
  .invalid-msg { color: #e74c3c; font-size: 0.85rem; margin-top: 5px; display: block; }
  .input-valid { border-color: #2ecc71 !important; }
  .input-invalid { border-color: #e74c3c !important; }

  .text-danger { color: #e74c3c !important; }
  .text-success { color: #2ecc71 !important; }
  .fw-bold { font-weight: bold; }
  .d-none { display: none !important; }
  .btn-primary { background-color: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; height: 46px; margin-top: 6px; }
  .btn-success { background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }

  /* MODAL DARK THEME */
  .modal-content { background-color: #1f1f1f; color: #eee; border: 1px solid #444; }
  .modal-header { border-bottom: 1px solid #333; }
  .modal-footer { border-top: 1px solid #333; }
  .modal-title { color: #00bfff; }

  /* FLASH CARD FLUTUANTE (VERMELHO) */
  #custom-flash-card {
      position: fixed;
      top: 20px;
      right: 20px;
      background-color: #dc3545; /* Vermelho Bootstrap */
      color: white;
      padding: 15px 25px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      z-index: 10000;
      opacity: 0;
      transform: translateY(-20px);
      transition: all 0.4s ease-in-out;
      pointer-events: none;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 10px;
  }
  #custom-flash-card.show {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
  }

  /* UTILS PARA RESPONSIVIDADE */
  .flex-group { display: flex; gap: 10px; align-items: flex-end; }
  
  @media (max-width: 768px) {
      .form-container { padding: 10px; align-items: flex-start; }
      form { padding: 20px 15px; }
      .flex-group { flex-direction: column; align-items: stretch; gap: 0; }
      .flex-group > div, .flex-group > input, .flex-group > button { width: 100%; margin-top: 5px; }
      .plano-card { min-width: 100%; margin-bottom: 5px; }
      .btn-check, .btn-primary { margin-top: 10px; width: 100%; }
      #div-doc-type { flex: 1; }
      #div-doc-number { flex: 1; margin-top: 10px; }
      #custom-flash-card { top: 10px; right: 10px; left: 10px; text-align: center; justify-content: center; }
  }
</style>
</head>
<body>

<div id="custom-flash-card">
    <i class="fas fa-exclamation-circle"></i> <span id="flash-card-msg">Mensagem de Erro</span>
</div>

<div class="form-container">
  <form id="cadastroForm" action="registro_processa.php" method="post" novalidate>
    <h2>Crie sua Conta</h2>

    <label>Escolha seu Plano (Teste Grátis):</label>
    <div class="planos-container">
      <label class="plano-card <?php echo (!isset($old['plano']) || $old['plano'] == 'basico') ? 'selected' : ''; ?>" id="card-basico">
        <input type="radio" name="plano" value="basico" <?php echo (!isset($old['plano']) || $old['plano'] == 'basico') ? 'checked' : ''; ?> onchange="selectPlan(this)">
        <span class="trial-badge">15 Dias Grátis</span>
        <span class="plano-titulo">Básico</span>
        <span class="plano-preco">R$ 19,00/mês</span>
        <div class="plano-desc">3 Usuários • Gestão Simples</div>
      </label>

      <label class="plano-card <?php echo (isset($old['plano']) && $old['plano'] == 'plus') ? 'selected' : ''; ?>" id="card-plus">
        <input type="radio" name="plano" value="plus" <?php echo (isset($old['plano']) && $old['plano'] == 'plus') ? 'checked' : ''; ?> onchange="selectPlan(this)">
        <span class="trial-badge">15 Dias Grátis</span>
        <span class="plano-titulo">Plus</span>
        <span class="plano-preco">R$ 39,00/mês</span>
        <div class="plano-desc">6 Usuários • Intermediário</div>
      </label>

      <label class="plano-card <?php echo (isset($old['plano']) && $old['plano'] == 'essencial') ? 'selected' : ''; ?>" id="card-essencial">
        <input type="radio" name="plano" value="essencial" <?php echo (isset($old['plano']) && $old['plano'] == 'essencial') ? 'checked' : ''; ?> onchange="selectPlan(this)">
        <span class="trial-badge">30 Dias Grátis</span>
        <span class="plano-titulo">Essencial</span>
        <span class="plano-preco">R$ 59,00/mês</span>
        <div class="plano-desc">16 Usuários • Completo</div>
      </label>
    </div>

    <label for="tipo_pessoa">Tipo de Pessoa:</label>
    <select id="tipo_pessoa" name="tipo_pessoa" required onchange="saveLocal('tipo_pessoa', this.value)">
      <option value="fisica" <?php echo (isset($old['tipo_pessoa']) && $old['tipo_pessoa'] == 'fisica') ? 'selected' : ''; ?>>Pessoa Física</option>
      <option value="juridica" <?php echo (isset($old['tipo_pessoa']) && $old['tipo_pessoa'] == 'juridica') ? 'selected' : ''; ?>>Pessoa Jurídica</option>
    </select>

    <label id="labelNome" for="nome">Nome Completo:</label>
    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($old['nome'] ?? ''); ?>" required oninput="saveLocal('nome', this.value)">

    <div class="flex-group">
        <div style="flex: 1;" id="div-doc-type">
            <label for="tipo_doc">Doc:</label>
            <select id="tipo_doc" name="tipo_doc" required onchange="saveLocal('tipo_doc', this.value)">
              <option value="cpf" <?php echo (isset($old['tipo_doc']) && $old['tipo_doc'] == 'cpf') ? 'selected' : ''; ?>>CPF</option>
              <option value="cnpj" <?php echo (isset($old['tipo_doc']) && $old['tipo_doc'] == 'cnpj') ? 'selected' : ''; ?>>CNPJ</option>
            </select>
        </div>
        <div style="flex: 2;" id="div-doc-number">
            <label id="labelDocumento" for="documento">Número:</label>
            <input type="text" id="documento" name="documento" value="<?php echo htmlspecialchars($old['documento'] ?? ''); ?>" required oninput="saveLocal('documento', this.value)">
        </div>
    </div>

    <label for="telefone">Telefone:</label>
    <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($old['telefone'] ?? ''); ?>" required oninput="saveLocal('telefone', this.value)">

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required oninput="saveLocal('email', this.value)">

    <label for="email2">Repetir Email:</label>
    <input type="email" id="email2" name="email2" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required>
    <div id="emailError" class="error-message"></div>

    <label for="senha">Senha:</label>
    <div class="input-group">
      <input type="password" id="senha" name="senha" required
             pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$"
             title="Mínimo 6 caracteres, incluindo letra maiúscula, minúscula, número e caractere especial">
      <i class="fas fa-eye toggle-password" id="toggleSenha"></i>
    </div>
    <div class="rules">Mínimo 6 caracteres, maiúscula, minúscula, número e especial</div>

    <label for="senha2">Repetir Senha:</label>
    <input type="password" id="senha2" name="senha2" required>
    <div id="senhaError" class="error-message"></div>

    <div class="benefits-section">
        <div class="benefit-toggle" onclick="toggleBenefits()">
            <i class="fas fa-ticket-alt"></i> Possui Cupom ou Indicação? (Opcional) <i class="fas fa-chevron-down"></i>
        </div>
        
        <div id="benefitsContent" class="benefit-content">
            <div style="margin-bottom: 20px;">
                <label style="margin-top: 0; color: #00bfff;">Cupom de Desconto:</label>
                <div class="input-group mt-2">
                    <input type="text" id="cupom" name="cupom" 
                           value="<?php echo htmlspecialchars($old['cupom'] ?? ''); ?>" 
                           placeholder="DIGITE SEU CUPOM" 
                           style="text-transform:uppercase; border-top-right-radius: 0; border-bottom-right-radius: 0;" 
                           oninput="saveLocal('cupom', this.value)">
                    <button type="button" class="btn btn-secondary" 
                            style="border-top-left-radius: 0; border-bottom-left-radius: 0; background: #444; color: white; border: 1px solid #333;" 
                            onclick="checkCupom()">
                        <i class="fas fa-check"></i> Validar
                    </button>
                </div>
                <span id="msgCupom" style="display:block; margin-top:5px;"></span>
            </div>
            <hr style="border-color: #444; margin: 15px 0;">
            <div id="formIndicacao">
                <label for="inputCodigoIndicacao" style="color: #00bfff;">Código de Indicação:</label>
                <div class="input-group mt-2">
                    <input type="text" id="inputCodigoIndicacao" name="codigo_indicacao" 
                           value="<?php echo htmlspecialchars($old['codigo_indicacao'] ?? ''); ?>" 
                           placeholder="CÓDIGO DO PARCEIRO" 
                           style="text-transform: uppercase; border-top-right-radius: 0; border-bottom-right-radius: 0;" 
                           oninput="saveLocal('codigo_indicacao', this.value)">
                    <button type="button" class="btn btn-primary" 
                            style="border-top-left-radius: 0; border-bottom-left-radius: 0;" 
                            onclick="validarCodigoIndicacao()">
                        <i class="fas fa-user-check"></i> Validar
                    </button>
                </div>
                <div id="feedbackIndicacao" class="form-text mt-2"></div>
                <input type="hidden" id="id_indicador_validado" name="id_indicador">
                <button type="button" class="btn btn-success d-none" id="btnConfirmarIndicacao" style="width:100%; margin-top:10px; cursor:default;">
                    Indicação Confirmada <i class="fas fa-check"></i>
                </button>
            </div>
        </div>
    </div>

    <div style="margin-top: 20px; display:flex; justify-content:center;">
        <div class="cf-turnstile" data-sitekey="0x4AAAAAACDq-KxXYje-dbsU" data-theme="dark"></div>
    </div>

    <button class="btn-submit" type="submit">Finalizar Cadastro e Testar Grátis</button>
    <div class="login-link">Já tem uma conta? <a href="login.php">Faça Login</a></div>
  </form>
</div>

<div class="modal fade" id="modalLGPD" tabindex="-1" aria-labelledby="modalLGPDLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLGPDLabel">Termos de Uso e Política de Privacidade (LGPD)</h5>
      </div>
      <div class="modal-body">
        <h4>Seus Direitos e o Tratamento de Dados</h4>
        <p>Em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), informamos que:</p>
        <ul>
            <li>Seus dados (nome, e-mail, telefone, CPF/CNPJ) são coletados apenas para o funcionamento deste sistema de controle financeiro.</li>
            <li>Nós não compartilhamos seus dados com terceiros sem seu consentimento explícito.</li>
            <li>Você tem o direito de solicitar, a qualquer momento via suporte, a exportação ou exclusão dos seus dados.</li>
        </ul>
        <hr>
        <p>Ao clicar em "Aceitar e Finalizar", você concorda expressamente com o tratamento dos seus dados conforme descrito acima.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Recusar</button>
        <button type="button" class="btn btn-success" id="btnAceitarLGPD">Li, Aceito e Finalizar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Funções Utilitárias e LocalStorage
  function saveLocal(key, value) { localStorage.setItem('reg_' + key, value); }
  function loadLocal() {
      const fields = ['tipo_pessoa', 'nome', 'tipo_doc', 'documento', 'telefone', 'email', 'cupom', 'inputCodigoIndicacao'];
      fields.forEach(id => {
          const el = document.getElementById(id);
          if(el && el.value === '') { 
              const val = localStorage.getItem('reg_' + id);
              if (val) {
                  el.value = val;
                  if(id === 'tipo_pessoa' || id === 'tipo_doc') $(el).trigger('change');
              }
          }
      });
  }

  // --- NOVA FUNÇÃO PARA EXIBIR FLASH CARD FLUTUANTE ---
  function showFloatingError(msg) {
      const card = document.getElementById('custom-flash-card');
      const msgSpan = document.getElementById('flash-card-msg');
      
      msgSpan.innerText = msg;
      card.classList.add('show');
      
      // Some automaticamente após 5 segundos
      setTimeout(() => {
          card.classList.remove('show');
      }, 5000);
  }

  function selectPlan(radio) {
    document.querySelectorAll('.plano-card').forEach(c => c.classList.remove('selected'));
    radio.closest('.plano-card').classList.add('selected');
  }

  function toggleBenefits() {
      document.getElementById('benefitsContent').classList.toggle('open');
  }

  const toggleSenha = document.getElementById('toggleSenha');
  const inputSenha = document.getElementById('senha');
  toggleSenha.addEventListener('click', () => {
    const tipo = inputSenha.getAttribute('type') === 'password' ? 'text' : 'password';
    inputSenha.setAttribute('type', tipo);
    toggleSenha.classList.toggle('fa-eye');
    toggleSenha.classList.toggle('fa-eye-slash');
  });

  function checkCupom() {
      const codigo = document.getElementById('cupom').value;
      const msg = document.getElementById('msgCupom');
      const input = document.getElementById('cupom');
      if(!codigo) return;
      msg.innerHTML = '<span style="color:#aaa">Verificando...</span>';
      const formData = new FormData();
      formData.append('codigo', codigo);
      fetch('../actions/validar_cupom_api.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
          if(data.valid) {
              msg.innerHTML = `<span class='valid-msg'><i class='fas fa-check'></i> Cupom válido! Desconto de ${data.valor}${data.tipo=='porcentagem'?'%':''} será aplicado.</span>`;
              input.classList.add('input-valid'); input.classList.remove('input-invalid');
          } else {
              msg.innerHTML = `<span class='invalid-msg'><i class='fas fa-times'></i> ${data.msg}</span>`;
              input.classList.add('input-invalid'); input.classList.remove('input-valid');
          }
      });
  }

  function validarCodigoIndicacao() {
    let codigo = document.getElementById('inputCodigoIndicacao').value;
    let feedback = document.getElementById('feedbackIndicacao');
    let btnConfirmar = document.getElementById('btnConfirmarIndicacao');
    let hiddenId = document.getElementById('id_indicador_validado');
    let input = document.getElementById('inputCodigoIndicacao'); 

    if(codigo.length < 3) { feedback.innerHTML = "<span class='text-danger'>Código muito curto.</span>"; return; }
    feedback.innerHTML = "<span style='color: #ccc;'>Verificando...</span>";

    fetch('../actions/validar_indicacao_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'codigo_indicacao=' + encodeURIComponent(codigo)
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            feedback.innerHTML = "<span class='text-success fw-bold'>" + data.message + "</span>";
            hiddenId.value = data.id_indicador;
            btnConfirmar.classList.remove('d-none');
            input.classList.add('input-valid'); input.classList.remove('input-invalid');
        } else {
            feedback.innerHTML = "<span class='text-danger'>" + data.message + "</span>";
            btnConfirmar.classList.add('d-none');
            hiddenId.value = "";
            input.classList.add('input-invalid'); input.classList.remove('input-valid');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        feedback.innerHTML = "<span class='text-danger'>Erro ao validar.</span>";
    });
  }

  $(document).ready(function() {
      function aplicarMascaraDocumento(tipo) {
        const input = $("#documento");
        input.unmask();
        if (tipo === "cpf") { input.mask("000.000.000-00"); $("#labelDocumento").text("CPF:"); } 
        else { input.mask("00.000.000/0000-00"); $("#labelDocumento").text("CNPJ:"); }
      }
      $("#tipo_pessoa").on("change", function() {
        if ($(this).val() === "fisica") { $("#labelNome").text("Nome Completo:"); $("#tipo_doc").val("cpf").trigger("change"); } 
        else { $("#labelNome").text("Nome da Empresa:"); $("#tipo_doc").val("cnpj").trigger("change"); }
      });
      $("#tipo_doc").on("change", function() { aplicarMascaraDocumento($(this).val()); });
      $("#telefone").mask("(00) 00000-0000");
      loadLocal();
      aplicarMascaraDocumento($("#tipo_doc").val() || "cpf");
  });

  document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('cadastroForm');
      const senha2 = document.getElementById('senha2');
      const email2 = document.getElementById('email2');
      const senhaError = document.getElementById('senhaError');
      const emailError = document.getElementById('emailError');
      const btnAceitarLGPD = document.getElementById('btnAceitarLGPD');

      form.addEventListener('submit', (e) => {
        let valid = true;
        
        const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/;
        if (!senhaRegex.test(inputSenha.value)) { senhaError.textContent = "Senha fraca."; valid = false; } 
        else if (inputSenha.value !== senha2.value) { senhaError.textContent = "Senhas não coincidem."; valid = false; } 
        else { senhaError.textContent = ""; }

        if (document.getElementById('email').value !== email2.value) { emailError.textContent = "Emails não coincidem."; valid = false; } 
        else { emailError.textContent = ""; }

        if (!valid) {
            e.preventDefault();
            return; 
        }

        // Verifica se já houve o aceite. Se não, verifica duplicidade ANTES de mostrar o modal.
        if (!form.querySelector('input[name="aceite_lgpd"]')) {
            e.preventDefault(); // Impede envio imediato

            // 1. Preparar dados para checagem AJAX
            const formData = new FormData();
            formData.append('ajax_check', '1');
            formData.append('email', document.getElementById('email').value);
            formData.append('documento', document.getElementById('documento').value);

            // 2. Feedback visual no botão
            const submitBtn = form.querySelector('.btn-submit');
            const originalText = submitBtn.innerText;
            submitBtn.innerText = 'Verificando...';
            submitBtn.disabled = true;

            // 3. Chamar registro_processa.php em modo JSON
            fetch('registro_processa.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;

                // 4. Se houver duplicidade, mostrar erro no FLASH CARD e PARAR.
                if (data.email_exists) {
                    showFloatingError("Este E-mail já está cadastrado no sistema.");
                    document.getElementById('email').classList.add('input-invalid');
                    return; 
                }
                if (data.doc_exists) {
                    showFloatingError("Este CPF/CNPJ já está cadastrado no sistema.");
                    document.getElementById('documento').classList.add('input-invalid');
                    return;
                }

                // 5. Se for ÚNICO, aí sim abre o Modal LGPD
                const modalElement = document.getElementById('modalLGPD');
                const modalLGPD = new bootstrap.Modal(modalElement);
                modalLGPD.show();
            })
            .catch(err => {
                console.error(err);
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
                showFloatingError("Erro de conexão. Tente novamente.");
            });
        }
      });

      btnAceitarLGPD.addEventListener('click', function() {
          let inputAceite = document.createElement("input");
          inputAceite.type = "hidden";
          inputAceite.name = "aceite_lgpd";
          inputAceite.value = "1";
          form.appendChild(inputAceite);

          const modalElement = document.getElementById('modalLGPD');
          const modalInstance = bootstrap.Modal.getInstance(modalElement);
          modalInstance.hide();
          
          form.submit();
      });
  });
</script>

</body>
</html>
<?php
// pages/assinar.php
require_once '../includes/session_init.php';
require_once '../database.php'; 
require_once '../includes/utils.php'; // Importa Utils

include('../includes/header.php');

// ✅ EXIBE O POP-UP CENTRALIZADO
display_flash_message();

// Pega o plano selecionado da URL (padrão: basico)
$plano_selecionado = $_GET['plano_selecionado'] ?? 'basico';

$conn = getMasterConnection();

// Recupera ID do Tenant e do Usuário com segurança
$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id   = $_SESSION['usuario_id_master'] ?? $_SESSION['usuario_id'] ?? 0;

$saved_cupom = '';
$saved_indicacao = '';

if ($tenant_id && $user_id) {
    // 1. Verifica se já existe Cupom salvo
    $stmtCupom = $conn->prepare("SELECT cupom_registro FROM tenants WHERE tenant_id = ?");
    if ($stmtCupom) {
        $stmtCupom->bind_param("s", $tenant_id);
        $stmtCupom->execute();
        $resCupom = $stmtCupom->get_result();
        $saved_cupom = ($resCupom->num_rows > 0) ? $resCupom->fetch_assoc()['cupom_registro'] : '';
        $stmtCupom->close();
    }

    // 2. Verifica se já existe Indicação salva
    $stmtInd = $conn->prepare("
        SELECT u.codigo_indicacao 
        FROM indicacoes i 
        JOIN usuarios u ON i.id_indicador = u.id 
        WHERE i.id_indicado = ? LIMIT 1
    ");
    if ($stmtInd) {
        $stmtInd->bind_param("i", $user_id);
        $stmtInd->execute();
        $resInd = $stmtInd->get_result();
        if ($resInd->num_rows > 0) {
            $saved_indicacao = $resInd->fetch_assoc()['codigo_indicacao'];
        }
        $stmtInd->close();
    }
}
?>

<style>
    .planos-wrapper { display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; padding: 20px 0; }
    .plano-card {
        background: #1f1f1f; border: 1px solid #333; border-radius: 12px; padding: 30px; width: 300px;
        text-align: center; transition: transform 0.3s, border-color 0.3s; position: relative; display: flex; flex-direction: column;
    }
    .plano-card:hover { transform: translateY(-5px); border-color: #00bfff; box-shadow: 0 5px 20px rgba(0, 191, 255, 0.15); }
    .plano-card.destaque-selecionado { border: 2px solid #00bfff; background: #252525; transform: scale(1.05); z-index: 10; box-shadow: 0 0 15px rgba(0,191,255,0.3); }
    
    .plano-header { margin-bottom: 20px; border-bottom: 1px solid #444; padding-bottom: 20px; }
    .plano-title { font-size: 1.5rem; font-weight: bold; color: #fff; }
    .plano-price { font-size: 2rem; color: #28a745; font-weight: 800; margin: 10px 0; }
    .old-price { text-decoration: line-through; font-size: 1rem; color: #777; display: block; }
    .plano-features { list-style: none; padding: 0; margin: 0 0 20px 0; text-align: left; flex-grow: 1; }
    .plano-features li { margin-bottom: 10px; color: #ccc; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; }
    .plano-features li i { color: #00bfff; }
    .btn-plano { width: 100%; padding: 12px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer; transition: background 0.3s; }
    .btn-outline { background: transparent; border: 2px solid #00bfff; color: #00bfff; }
    .btn-outline:hover { background: #00bfff; color: #fff; }
    .btn-primary-custom { background: linear-gradient(135deg, #00bfff, #008cba); color: white; }
    .extra-options-container { max-width: 500px; margin: 0 auto 30px auto; display: flex; flex-direction: column; gap: 15px; }
    .option-box { background: #252525; padding: 15px; border-radius: 8px; border: 1px solid #333; }
    .option-title { color: #fff; font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; cursor: pointer; }
    .option-content { display: none; margin-top: 10px; }
    .option-content.active { display: block; }
    .input-row { display: flex; gap: 10px; }
    .custom-input { padding: 10px; border-radius: 5px; border: 1px solid #444; background: #1c1c1c; color: #fff; width: 100%; }
    .custom-input[readonly] { background: #333; color: #aaa; border-color: #2ecc71; cursor: not-allowed; }
    .btn-check { padding: 10px 15px; border-radius: 5px; border: none; background: #6c757d; color: white; font-weight: bold; cursor: pointer; white-space: nowrap; }
    .valid-border { border-color: #2ecc71 !important; box-shadow: 0 0 5px rgba(46, 204, 113, 0.5); }
    .invalid-border { border-color: #e74c3c !important; }
    .msg-feedback { font-size: 0.85rem; margin-top: 8px; font-weight: bold; text-align: center; }
    .text-success { color: #2ecc71; }
    .text-error { color: #e74c3c; }
    .text-promo { color: #f1c40f; display: block; margin-top: 5px; font-size: 0.85rem; }
    .badge-pop { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #ffc107; color: #000; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; }
</style>

<div class="container">
    <div class="text-center mt-5 mb-4">
        <h2 style="color: #fff;">Escolha ou Renove seu Plano</h2>
    </div>

    <div class="extra-options-container">
        <div class="option-box">
            <div class="option-title" onclick="toggleBox('boxCupom')"><i class="fas fa-ticket-alt"></i> Possui um Cupom?</div>
            <div id="boxCupom" class="option-content">
                <div class="input-row">
                    <input type="text" id="inputCupom" class="custom-input" placeholder="Digite o código" style="text-transform:uppercase;">
                    <button type="button" id="btnValidarCupom" class="btn-check" onclick="validarCupom()">Aplicar</button>
                </div>
                <div id="msgCupom" class="msg-feedback"></div>
            </div>
        </div>

        <div class="option-box">
            <div class="option-title" onclick="toggleBox('boxIndicacao')"><i class="fas fa-user-friends"></i> Foi indicado por alguém?</div>
            <div id="boxIndicacao" class="option-content">
                <label style="color:#ccc; display:block; margin-bottom:5px;">Código de Indicação:</label>
                <div class="input-row">
                    <input type="text" id="inputCodigoIndicacao" class="custom-input" placeholder="Ex: A1B2C3D4" style="text-transform:uppercase;">
                    <button type="button" id="btnConfInd" class="btn-check" onclick="validarIndicacao()">Conferir</button>
                </div>
                <div id="msgIndicacao" class="msg-feedback"></div>
            </div>
        </div>
    </div>

    <div class="planos-wrapper">
        <div class="plano-card <?= $plano_selecionado == 'basico' ? 'destaque-selecionado' : '' ?>">
            <div class="plano-header">
                <div class="plano-title">Básico</div>
                <div class="plano-price" data-original="19.00">R$ 19,00<small>/mês</small></div>
            </div>
            <ul class="plano-features">
                <li><i class="fa-solid fa-check"></i> 3 Usuários Totais</li>
                <li><i class="fa-solid fa-check"></i> Gestão Financeira</li>
            </ul>
            <form action="../actions/checkout_plano.php" method="POST" onsubmit="prepararEnvio(this)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="plano" value="basico">
                <input type="hidden" name="cupom" class="hidden-cupom">
                <input type="hidden" name="codigo_indicacao" class="hidden-codigo-indicacao">
                <button class="btn-plano btn-outline">Assinar Básico</button>
            </form>
        </div>

        <div class="plano-card <?= $plano_selecionado == 'plus' ? 'destaque-selecionado' : '' ?>">
            <?php if($plano_selecionado == 'plus'): ?><span class="badge-pop">Selecionado</span><?php endif; ?>
            <div class="plano-header">
                <div class="plano-title" style="color:#00bfff">Plus</div>
                <div class="plano-price" data-original="39.90">R$ 39,00<small>/mês</small></div>
            </div>
            <ul class="plano-features">
                <li><i class="fa-solid fa-check"></i> 6 Usuários Totais</li>
                <li><i class="fa-solid fa-check"></i> Suporte Prioritário</li>
            </ul>
            <form action="../actions/checkout_plano.php" method="POST" onsubmit="prepararEnvio(this)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="plano" value="plus">
                <input type="hidden" name="cupom" class="hidden-cupom">
                <input type="hidden" name="codigo_indicacao" class="hidden-codigo-indicacao">
                <button class="btn-plano btn-primary-custom">Assinar Plus</button>
            </form>
        </div>

        <div class="plano-card <?= $plano_selecionado == 'essencial' ? 'destaque-selecionado' : '' ?>">
            <?php if($plano_selecionado == 'essencial'): ?><span class="badge-pop">Selecionado</span><?php endif; ?>
            <div class="plano-header">
                <div class="plano-title">Essencial</div>
                <div class="plano-price" data-original="59.90">R$ 59,00<small>/mês</small></div>
            </div>
            <ul class="plano-features">
                <li><i class="fa-solid fa-check"></i> 16 Usuários Totais</li>
                <li><i class="fa-solid fa-check"></i> Controle Completo</li>
            </ul>
            <form action="../actions/checkout_plano.php" method="POST" onsubmit="prepararEnvio(this)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="plano" value="essencial">
                <input type="hidden" name="cupom" class="hidden-cupom">
                <input type="hidden" name="codigo_indicacao" class="hidden-codigo-indicacao">
                <button class="btn-plano btn-outline">Assinar Essencial</button>
            </form>
        </div>
    </div>
</div>

<script>
// Dados salvos no Banco de Dados (vindos do PHP)
const cupomSalvoDB = "<?= htmlspecialchars($saved_cupom ?? '') ?>";
const indicacaoSalvaDB = "<?= htmlspecialchars($saved_indicacao ?? '') ?>";

// Função para pegar parâmetros da URL
function getQueryParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

function toggleBox(id) {
    document.getElementById(id).classList.toggle('active');
}

function validarCupom() {
    const codigo = document.getElementById('inputCupom').value;
    const msg = document.getElementById('msgCupom');
    const btn = document.getElementById('btnValidarCupom');
    if(!codigo) return;
    
    msg.innerHTML = '<span style="color:#ccc">Verificando...</span>';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('codigo', codigo);

    fetch('../actions/validar_cupom_api.php', { method:'POST', body:formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if(data.valid) {
            msg.innerHTML = `<span class="text-success">Cupom aplicado! -${data.valor}${data.tipo=='porcentagem'?'%':''}</span>`;
            // Atualiza os inputs ocultos em TODOS os formulários
            document.querySelectorAll('.hidden-cupom').forEach(i => i.value = data.codigo);
            atualizarPrecos(data.tipo, data.valor);
        } else {
            msg.innerHTML = `<span class="text-error">${data.msg}</span>`;
            resetarPrecos();
        }
    });
}

function validarIndicacao() {
    const codigo = document.getElementById('inputCodigoIndicacao').value;
    const msg = document.getElementById('msgIndicacao');
    const btn = document.getElementById('btnConfInd');
    const input = document.getElementById('inputCodigoIndicacao');

    if(!codigo) {
        msg.innerHTML = '<span class="text-error">Digite o código de indicação.</span>';
        return;
    }
    
    btn.innerHTML = '...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('codigo_indicacao', codigo);

    fetch('../actions/validar_indicacao_api.php', { method:'POST', body:formData })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = 'Conferir';
        btn.disabled = false;
        
        if(data.valid) {
            input.classList.add('valid-border');
            input.classList.remove('invalid-border');
            
            msg.innerHTML = `
                <span class="text-success"><i class="fas fa-check-circle"></i> Indicado por: ${data.nome_indicador}</span>
                <span class="text-promo">Desconto de 10% aplicado!</span>
            `;
            // Atualiza os inputs ocultos em TODOS os formulários
            document.querySelectorAll('.hidden-codigo-indicacao').forEach(i => i.value = codigo);
            atualizarPrecos('porcentagem', 10);
        } else {
            input.classList.add('invalid-border');
            input.classList.remove('valid-border');
            msg.innerHTML = `<span class="text-error">${data.message}</span>`;
            document.querySelectorAll('.hidden-codigo-indicacao').forEach(i => i.value = '');
            resetarPrecos();
        }
    })
    .catch(e => {
        console.error(e);
        btn.innerHTML = 'Conferir';
        btn.disabled = false;
        msg.innerHTML = '<span class="text-error">Erro ao validar.</span>';
    });
}

function atualizarPrecos(tipo, valor) {
    document.querySelectorAll('.plano-price').forEach(el => {
        let original = parseFloat(el.getAttribute('data-original'));
        let novo = tipo === 'porcentagem' ? original - (original * (valor/100)) : original - valor;
        novo = Math.max(0, novo);
        el.innerHTML = `<span class="old-price">R$ ${original.toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>
                        R$ ${novo.toLocaleString('pt-BR',{minimumFractionDigits:2})}<small>/mês</small>`;
    });
}

function resetarPrecos() {
    document.querySelectorAll('.plano-price').forEach(el => {
        let original = parseFloat(el.getAttribute('data-original'));
        el.innerHTML = `R$ ${original.toLocaleString('pt-BR',{minimumFractionDigits:2})}<small>/mês</small>`;
    });
}

// Função executada ao clicar em "Assinar"
function prepararEnvio(form) {
    const inputCupom = document.getElementById('inputCupom');
    const inputIndicacao = document.getElementById('inputCodigoIndicacao');

    // Força a cópia do que está digitado para o hidden, caso o usuário não tenha clicado em "Aplicar"
    if (inputCupom && inputCupom.value.trim() !== "") {
        form.querySelector('.hidden-cupom').value = inputCupom.value.trim().toUpperCase();
    }
    
    if (inputIndicacao && inputIndicacao.value.trim() !== "") {
        form.querySelector('.hidden-codigo-indicacao').value = inputIndicacao.value.trim().toUpperCase();
    }
}

window.onload = function() {
    const urlCupom = getQueryParam('cupom');
    const urlIndicacao = getQueryParam('codigo_indicacao');

    if (indicacaoSalvaDB) {
        const box = document.getElementById('boxIndicacao');
        const input = document.getElementById('inputCodigoIndicacao');
        const btn = document.getElementById('btnConfInd');
        
        box.classList.add('active');
        input.value = indicacaoSalvaDB;
        input.readOnly = true; 
        btn.innerText = "Ativado";
        btn.disabled = true; 
        
        validarIndicacao(); 
    } 
    else if (urlIndicacao) {
        document.getElementById('boxIndicacao').classList.add('active');
        document.getElementById('inputCodigoIndicacao').value = urlIndicacao;
        validarIndicacao();
    }

    if (cupomSalvoDB) {
        const box = document.getElementById('boxCupom');
        const input = document.getElementById('inputCupom');
        const btn = document.getElementById('btnValidarCupom');

        box.classList.add('active');
        input.value = cupomSalvoDB;
        input.readOnly = true; 
        btn.innerText = "Aplicado";
        btn.disabled = true;

        validarCupom();
    }
    else if (urlCupom) {
        document.getElementById('boxCupom').classList.add('active');
        document.getElementById('inputCupom').value = urlCupom;
        validarCupom();
    }
};
</script>

<?php include('../includes/footer.php'); ?>

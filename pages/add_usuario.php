<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// Verifica Permissão
$nivel = $_SESSION['nivel_acesso'] ?? 'padrao';
if ($nivel !== 'admin' && $nivel !== 'master' && $nivel !== 'proprietario') {
    set_flash_message('danger', 'Acesso negado.');
    header('Location: usuarios.php');
    exit;
}
require_tenant_admin();

// --- BUSCA O PLANO ATUAL ---
$connMaster = getMasterConnection();
$plano_tenant = 'basico';
if ($connMaster && isset($_SESSION['tenant_id'])) {
    $tenant = getTenantById($_SESSION['tenant_id'], $connMaster);
    if ($tenant) {
        $plano_tenant = $tenant['plano_atual'] ?? 'basico';
    }
    $connMaster->close();
}

// Define itens disponíveis
$itens_basicos = [
    'contas_pagar.php' => 'Contas a Pagar',
    'contas_pagar_baixadas.php' => 'Contas Pagas',
    'contas_receber.php' => 'Contas a Receber',
    'contas_receber_baixadas.php' => 'Contas Recebidas',
    'lembrete.php' => 'Lembretes',
    'perfil.php' => 'Perfil',
    'selecionar_usuario.php' => 'Trocar Usuário',
    'usuarios.php' => 'Gestão de Usuários'
];

$itens_avancados = [
    'lancamento_caixa.php' => 'Fluxo de Caixa',
    'vendas_periodo.php' => 'Vendas e Comissão',
    'controle_estoque.php' => 'Estoque',
    'vendas.php' => 'Caixa de Vendas',
    'compras.php' => 'Compras',
    'cadastrar_pessoa_fornecedor.php' => 'Clientes e Fornecedores',
    'banco_cadastro.php' => 'Contas Bancárias',
    'categorias.php' => 'Categorias',
    'relatorios.php' => 'Relatórios',
    'configuracao_fiscal.php' => 'Configuração Fiscal'
];

$opcoes_para_exibir = $itens_basicos;
if ($plano_tenant === 'plus' || $plano_tenant === 'essencial') {
    $opcoes_para_exibir = array_merge($itens_basicos, $itens_avancados);
}

include('../includes/header.php');

// EXIBE O POP-UP CENTRALIZADO
display_flash_message();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Usuário</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        
       body { 
    background-color: #121212; 
    color: #eee; 
    font-family: 'Segoe UI', sans-serif; 
    margin: 0;
    padding: 0;
}

/* FULL DESKTOP */
.page-container { 
    width: 100%;
    max-width: 1200px; /* Maior para desktop */
    margin: 40px auto; 
    background: #1e1e1e; 
    padding: 35px; 
    border-radius: 12px; 
    color: #fff; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.5); 
    border: 1px solid #333; 
}

.page-container h2 { 
    color: #00bfff; 
    border-bottom: 1px solid #00bfff; 
    padding-bottom: 15px; 
    margin-bottom: 30px; 
    font-size: 1.8rem; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}

.form-group { margin-bottom: 20px; }

label { 
    display: block; 
    margin-bottom: 8px; 
    font-weight: 600; 
    color: #ccc; 
}

/* Input */
.input-wrapper { position: relative; }

.form-control, select { 
    width: 100%; 
    padding: 12px; 
    padding-right: 40px; 
    border-radius: 6px; 
    border: 1px solid #444; 
    background: #252525; 
    color: #fff; 
    font-size: 1rem; 
    box-sizing: border-box; 
    transition: 0.3s; 
}

.form-control:focus, select:focus { 
    outline: none; 
    border-color: #00bfff; 
    background-color: #2a2a2a; 
    box-shadow: 0 0 15px rgba(0, 191, 255, 0.8); 
}

/* Olho da senha */
.toggle-password { 
    position: absolute; 
    top: 50%; 
    right: 15px; 
    transform: translateY(-50%); 
    color: #aaa; 
    cursor: pointer; 
}

.btn-area { 
    display: flex; 
    justify-content: space-between; 
    margin-top: 30px; 
    gap: 15px; 
    flex-wrap: wrap; /* Responsivo */
}

.btn-custom { 
    padding: 12px 24px; 
    border-radius: 6px; 
    cursor: pointer; 
    border: none; 
    font-weight: bold; 
    font-size: 1rem; 
    text-decoration: none; 
    text-align: center; 
}

.btn-back { background: #444; color: #ddd; }
.btn-submit { 
    background: linear-gradient(135deg, #00bfff, #0099cc); 
    color: #fff; 
    flex-grow: 1; 
}

/* GRID de permissões */
.permissoes-container { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 10px; 
    background: #252525; 
    padding: 15px; 
    border-radius: 6px; 
    border: 1px solid #444; 
}

.check-item { 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    font-size: 0.9rem; 
    cursor: pointer; 
}

.check-item input { 
    accent-color: #00bfff; 
    width: 16px; 
    height: 16px; 
}

/* ============================
   RESPONSIVIDADE
   ============================ */

/* Tablets */
@media (max-width: 992px) {
    .page-container {
        padding: 25px;
        max-width: 90%;
    }

    .page-container h2 {
        font-size: 1.6rem;
    }

    .permissoes-container {
        grid-template-columns: 1fr 1fr;
    }
}

/* Mobile */
@media (max-width: 600px) {
    .page-container {
        max-width: 95%;
        padding: 20px;
        margin: 20px auto;
    }

    .page-container h2 {
        font-size: 1.4rem;
        flex-direction: column;
        text-align: center;
    }

    .btn-area {
        flex-direction: column;
    }

    /* .btn-custom {
        width: 100%;
    } */

    .permissoes-container {
        grid-template-columns: 1fr;
    }

    .form-control, select {
        font-size: 1rem;
    }
}

    </style>
</head>
<body>

<div class="page-container">
    <h2><i class="fa-solid fa-user-plus"></i> Novo Usuário</h2>

    <form action="../actions/add_usuario.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="form-group">
            <label>Nome Completo:</label>
            <input type="text" name="nome" class="form-control" required placeholder="Ex: Maria Souza">
        </div>

        <div class="form-group">
            <label>E-mail:</label>
            <input type="email" name="email" class="form-control" required placeholder="usuario@email.com">
        </div>

        <div class="form-group">
            <label>CPF (Opcional):</label>
            <input type="text" name="cpf" id="cpf" class="form-control" placeholder="000.000.000-00">
        </div>

        <div class="form-group">
            <label>Nível de Acesso:</label>
            <select name="nivel" id="nivel_acesso" class="form-control" onchange="togglePermissoes()">
                <option value="padrao">Padrão (Selecionar Permissões)</option>
                <option value="admin">Administrador (Acesso Total)</option>
            </select>
        </div>

        <div class="form-group" id="area_permissoes">
            <label>Permissões de Acesso:</label>
            <div class="permissoes-container">
                <?php foreach ($opcoes_para_exibir as $arquivo => $label): ?>
                    <label class="check-item">
                        <input type="checkbox" name="permissoes[]" value="<?= $arquivo ?>">
                        <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="color: #aaa; margin-top: 5px; display:block;">Marque o que este usuário poderá ver na Home.</small>
        </div>

        <div class="form-group">
            <label>Senha:</label>
            <div class="input-wrapper">
                <input type="password" name="senha" id="senha" class="form-control" required placeholder="******">
                <i class="fa-solid fa-eye toggle-password" onclick="togglePass('senha', this)"></i>
            </div>
        </div>

        <div class="form-group">
            <label>Confirmar Senha:</label>
            <div class="input-wrapper">
                <input type="password" name="senha_confirmar" id="senha_confirmar" class="form-control" required placeholder="******">
                <i class="fa-solid fa-eye toggle-password" onclick="togglePass('senha_confirmar', this)"></i>
            </div>
        </div>

        <div class="btn-area">
            <a href="usuarios.php" class="btn-custom btn-back">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
            <button type="submit" class="btn-custom btn-submit">
                <i class="fa-solid fa-save"></i> Salvar Usuário
            </button>
        </div>

    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
    $(document).ready(function(){ $('#cpf').mask('000.000.000-00'); });

    function togglePass(fieldId, icon) {
        const input = document.getElementById(fieldId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }

    function togglePermissoes() {
        var nivel = document.getElementById('nivel_acesso').value;
        var area = document.getElementById('area_permissoes');
        if (nivel === 'admin') {
            area.style.display = 'none';
        } else {
            area.style.display = 'block';
        }
    }
    togglePermissoes();
</script>

</body>
</html>
<?php include('../includes/footer.php'); ?>

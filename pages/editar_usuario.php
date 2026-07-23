<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_tenant_admin();
require_once '../includes/utils.php'; 

// Verifica Permissão do logado
$nivel_logado = $_SESSION['nivel_acesso'] ?? 'padrao';
$id_logado = $_SESSION['usuario_id'];

if ($nivel_logado !== 'admin' && $nivel_logado !== 'master' && $nivel_logado !== 'proprietario') {
    if (isset($_GET['id']) && $_GET['id'] != $id_logado) {
        set_flash_message('danger', 'Acesso negado.');
        header('Location: usuarios.php');
        exit;
    }
}

$conn = getTenantConnection();
$id_usuario = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_usuario) {
    set_flash_message('danger', 'ID inválido.');
    header('Location: usuarios.php');
    exit;
}

// Busca dados do usuário e suas permissões atuais
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash_message('danger', 'Usuário não encontrado.');
    header('Location: usuarios.php');
    exit;
}

// Decodifica as permissões atuais do usuário (armazenadas em JSON no banco)
$permissoes_atuais = json_decode($user['permissoes'] ?? '[]', true);

// --- BUSCA O PLANO ATUAL PARA EXIBIR AS OPÇÕES CORRETAS ---
$connMaster = getMasterConnection();
$plano_tenant = 'basico';
if ($connMaster && isset($_SESSION['tenant_id'])) {
    $tenant = getTenantById($_SESSION['tenant_id'], $connMaster);
    if ($tenant) {
        $plano_tenant = $tenant['plano_atual'] ?? 'basico';
    }
    $connMaster->close();
}

// Definição dos itens (Mesma lógica do add_usuario.php)
$itens_basicos = [
    'contas_pagar.php' => 'Contas a Pagar',
    'contas_pagar_baixadas.php' => 'Contas Pagas',
    'contas_receber.php' => 'Contas a Receber',
    'contas_receber_baixadas.php' => 'Contas Recebidas',
    'lembretes.php' => 'Lembretes',
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
display_flash_message();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #121212; color: #eee; font-family: 'Segoe UI', sans-serif; }
        .page-container { max-width: 800px; margin: 40px auto; background: #1e1e1e; padding: 35px; border-radius: 12px; color: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.5); border: 1px solid #333; }
        .page-container h2 { color: #ffc107; border-bottom: 1px solid #ffc107; padding-bottom: 15px; margin-bottom: 30px; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #ccc; }
        .form-control, select { width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #444; background: #252525; color: #fff; box-sizing: border-box; }
        .permissoes-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; background: #252525; padding: 15px; border-radius: 6px; border: 1px solid #444; }
        .check-item { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer; }
        .check-item input { accent-color: #00bfff; width: 16px; height: 16px; }
        .btn-area { display: flex; justify-content: space-between; margin-top: 30px; gap: 15px; }
        .btn-custom { padding: 12px 24px; border-radius: 6px; cursor: pointer; border: none; font-weight: bold; text-decoration: none; text-align: center; }
        .btn-back { background: #444; color: #ddd; }
        .btn-submit { background: linear-gradient(135deg, #ffc107, #e0a800); color: #000; flex-grow: 1; }
        @media (max-width: 600px) { .permissoes-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="page-container">
    <h2><i class="fa-solid fa-user-pen"></i> Editar Usuário</h2>

    <form action="../actions/editar_usuario.php" method="POST">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">

        <div class="form-group">
            <label>Nome Completo:</label>
            <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($user['nome']) ?>">
        </div>

        <div class="form-group">
            <label>E-mail:</label>
            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
        </div>

        <div class="form-group">
            <label>Nível de Acesso:</label>
            <select name="nivel" id="nivel_acesso" class="form-control" onchange="togglePermissoes()">
                <option value="padrao" <?= ($user['nivel_acesso'] === 'padrao') ? 'selected' : '' ?>>Padrão (Selecionar Permissões)</option>
                <option value="admin" <?= ($user['nivel_acesso'] === 'admin') ? 'selected' : '' ?>>Administrador (Acesso Total)</option>
            </select>
        </div>

        <div class="form-group" id="area_permissoes">
            <label>Permissões de Acesso:</label>
            <div class="permissoes-container">
                <?php foreach ($opcoes_para_exibir as $arquivo => $label): ?>
                    <label class="check-item">
                        <input type="checkbox" name="permissoes[]" value="<?= $arquivo ?>" 
                            <?= in_array($arquivo, $permissoes_atuais) ? 'checked' : '' ?>>
                        <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="color: #aaa; margin-top: 5px; display:block;">Gerencie o que este usuário visualiza na Home.</small>
        </div>

        <div class="form-group">
            <label>Nova Senha (Opcional):</label>
            <input type="password" name="senha" class="form-control" placeholder="Deixe em branco para manter">
        </div>

        <div class="btn-area">
            <a href="usuarios.php" class="btn-custom btn-back">Voltar</a>
            <button type="submit" class="btn-custom btn-submit">Atualizar Usuário</button>
        </div>
    </form>
</div>

<script>
    function togglePermissoes() {
        var nivel = document.getElementById('nivel_acesso').value;
        var area = document.getElementById('area_permissoes');
        area.style.display = (nivel === 'admin') ? 'none' : 'block';
    }
    togglePermissoes();
</script>

</body>
</html>
<?php 
$conn->close();
include('../includes/footer.php'); 
?>

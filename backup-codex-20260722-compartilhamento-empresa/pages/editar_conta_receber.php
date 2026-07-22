<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// 1. VERIFICA O LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: ../pages/login.php");
    exit;
}
$conn = getTenantConnection();

$id_usuario = $_SESSION['usuario_id'];
$id_conta = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if ($id_conta === 0) {
    set_flash_message('danger', 'ID inválido.');
    header("Location: contas_receber.php");
    exit;
}

// LÓGICA DE ATUALIZAÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido.');
        header("Location: editar_conta_receber.php?id=" . $id_conta);
        exit;
    }

    $id_pessoa = !empty($_POST['id_pessoa_fornecedor']) ? (int)$_POST['id_pessoa_fornecedor'] : null;
    
    // Tratamento de dados com utils.php
    $data_vencimento = data_para_iso($_POST['data_vencimento']);
    $valor = brl_to_float($_POST['valor']);

    $id_categoria = (int)$_POST['id_categoria'];
    $numero = trim($_POST['numero'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    $sql = "UPDATE contas_receber SET 
                id_pessoa_fornecedor = ?, 
                numero = ?,
                descricao = ?,
                data_vencimento = ?, 
                valor = ?, 
                id_categoria = ?
            WHERE id = ? AND usuario_id = ?";
            
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("isssdiii", 
        $id_pessoa, 
        $numero,
        $descricao,
        $data_vencimento, 
        $valor, 
        $id_categoria, 
        $id_conta, 
        $id_usuario
    );

    if ($stmt->execute()) {
        set_flash_message('success', "Receita atualizada com sucesso!");
        header("Location: ../pages/contas_receber.php");
    } else {
        set_flash_message('danger', "Erro ao atualizar: " . $stmt->error);
        header("Location: editar_conta_receber.php?id=" . $id_conta);
    }
    $stmt->close();
    exit;
}

include('../includes/header.php');

// Exibe mensagem centralizada se houver
display_flash_message();

// Busca a conta 
$stmt = $conn->prepare("SELECT * FROM contas_receber WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $id_conta, $id_usuario);
$stmt->execute();
$conta = $stmt->get_result()->fetch_assoc();

if (!$conta) {
    echo "<div class='container'><h3 style='color:#c0392b'>Conta não encontrada.</h3></div>";
    include('../includes/footer.php');
    exit;
}

// Listas para os selects
$categorias = $conn->query("SELECT id, nome FROM categorias WHERE id_usuario = $id_usuario AND tipo = 'receita' ORDER BY nome");
$clientes = $conn->query("SELECT id, nome FROM pessoas_fornecedores WHERE id_usuario = $id_usuario AND (tipo = 'pessoa' OR tipo = 'ambos') ORDER BY nome");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Receita</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background-color: #121212; color: #eee; font-family: Arial, sans-serif; padding: 20px; }
        .container { background-color: #1f1f1f; padding: 25px; border-radius: 8px; max-width: 800px; margin: 0 auto; border: 1px solid #444; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #ccc; }
        input, select { width: 100%; padding: 10px; background: #333; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        input:focus, select:focus { border-color: #00bfff; outline: none; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; color: white; text-decoration: none; display: inline-block; }
        .btn-success { background-color: #27ae60; }
        .btn-secondary { background-color: #555; }
        .row { display: flex; gap: 15px; }
        .col { flex: 1; }
    </style>
</head>
<body>

<div class="container">
    <h2 style="text-align:center; color:#00bfff;">Editar Conta a Receber</h2>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id_conta) ?>">

        <div class="row">
            <div class="col form-group">
                <label>Cliente / Pagador</label>
                <select name="id_pessoa_fornecedor" required>
                    <option value="">Selecione...</option>
                    <?php while($c = $clientes->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= ($c['id'] == $conta['id_pessoa_fornecedor']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col form-group">
                <label>Valor (R$)</label>
                <input type="text" name="valor" value="<?= number_format($conta['valor'], 2, ',', '.') ?>" required onkeyup="formatarMoeda(this)">
            </div>
        </div>

        <div class="row">
            <div class="col form-group">
                <label>Número/Documento</label>
                <input type="text" name="numero" value="<?= htmlspecialchars($conta['numero'] ?? '') ?>">
            </div>
            <div class="col form-group">
                <label>Data Vencimento</label>
                <input type="date" name="data_vencimento" value="<?= htmlspecialchars($conta['data_vencimento']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Descrição / Observação</label>
            <input type="text" name="descricao" value="<?= htmlspecialchars($conta['descricao'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Categoria</label>
            <select name="id_categoria" required>
                <option value="">Selecione...</option>
                <?php while($cat = $categorias->fetch_assoc()): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $conta['id_categoria']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="text-align:right; margin-top:20px;">
            <a href="../pages/contas_receber.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
        </div>
    </form>
</div>

<script>
function formatarMoeda(i) {
    var v = i.value.replace(/\D/g,'');
    v = (v/100).toFixed(2) + '';
    v = v.replace(".", ",");
    v = v.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
    v = v.replace(/(\d)(\d{3}),/g, "$1.$2,");
    i.value = v;
}
</script>

<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = getTenantConnection();
$id_usuario = $_SESSION['usuario_id'];
$id_registro = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM contas_bancarias WHERE id = ? AND id_usuario = ?");
$stmt->bind_param("ii", $id_registro, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$registro = $result->fetch_assoc();

include('../includes/header.php');

// POP-UP
display_flash_message();

if (!$registro) {
    echo "<div class='container'><h1>Conta não encontrada ou acesso negado.</h1></div>";
    include('../includes/footer.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Conta Bancária</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #121212; color: #eee; }
        .container { background-color: #222; padding: 25px; border-radius: 8px; margin-top: 30px; }
        h1 { color: #eee; border-bottom: 2px solid #0af; padding-bottom: 10px; }
        .form-control { background-color: #333; color: #eee; border: 1px solid #444; }
        .form-control:focus { background-color: #333; color: #eee; border-color: #0af; }
        .btn-primary { background-color: #0af; border: none; }
        .btn-secondary { background-color: #6c757d; border: none; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fa-solid fa-pen-to-square"></i> Editar Conta Bancária</h1>
    
    <form action="../actions/editar_banco_action.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($registro['id']) ?>">
        
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="nome_banco">Nome do Banco</label>
                <input type="text" class="form-control" name="nome_banco" value="<?= htmlspecialchars($registro['nome_banco']) ?>" required>
            </div>
            <div class="form-group col-md-6">
                <label for="tipo_conta">Tipo de Conta</label>
                <input type="text" class="form-control" name="tipo_conta" value="<?= htmlspecialchars($registro['tipo_conta']) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="agencia">Agência</label>
                <input type="text" class="form-control" name="agencia" value="<?= htmlspecialchars($registro['agencia']) ?>">
            </div>
            <div class="form-group col-md-6">
                <label for="conta">Número da Conta</label>
                <input type="text" class="form-control" name="conta" value="<?= htmlspecialchars($registro['conta']) ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="chave_pix">Chave PIX</label>
            <input type="text" class="form-control" name="chave_pix" value="<?= htmlspecialchars($registro['chave_pix']) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="banco_cadastro.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include('../includes/footer.php'); ?>
</body>
</html>
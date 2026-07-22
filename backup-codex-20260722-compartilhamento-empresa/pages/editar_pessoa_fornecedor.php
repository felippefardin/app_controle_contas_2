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

$stmt = $conn->prepare("SELECT * FROM pessoas_fornecedores WHERE id = ? AND id_usuario = ?");
$stmt->bind_param("ii", $id_registro, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$pessoa = $result->fetch_assoc();
$stmt->close();

include('../includes/header.php');

// EXIBE O POP-UP
display_flash_message();

if (!$pessoa) {
    echo "<div class='container' style='background-color: #222; padding: 25px; border-radius: 8px; margin-top: 30px; color: #eee;'>Registro não encontrado ou você não tem permissão.</div>";
    include('../includes/footer.php');
    exit; 
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar: <?= htmlspecialchars($pessoa['nome']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #121212; color: #eee; }
        .container { background-color: #222; padding: 25px; border-radius: 8px; margin-top: 30px; max-width: 800px; }
        h1 { color: #00bfff; border-bottom: 2px solid #0af; padding-bottom: 10px; }
        .form-control, .custom-select { background-color: #333; color: #eee; border: 1px solid #444; }
        .form-control:focus, .custom-select:focus { background-color: #333; color: #eee; border-color: #0af; box-shadow: none; }
        label { color: #ccc; font-weight: bold; }
        .btn-primary { background-color: #0af; border: none; font-weight: bold; color: #121212; }
        .btn-primary:hover { background-color: #0099cc; color: #fff; }
        .btn-secondary { background-color: #555; border: none; }
        .btn-secondary:hover { background-color: #777; }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fa-solid fa-pen-to-square"></i> Editar: <?= htmlspecialchars($pessoa['nome']) ?></h1>
    
    <form action="../actions/editar_pessoa_fornecedor_action.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" value="<?= $pessoa['id'] ?>">

        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="nome">Nome / Razão Social</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($pessoa['nome']) ?>" required>
            </div>
            <div class="form-group col-md-6">
                <label for="cpf_cnpj">CPF / CNPJ</label>
                <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?= htmlspecialchars($pessoa['cpf_cnpj']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="endereco">Endereço</label>
            <input type="text" class="form-control" id="endereco" name="endereco" value="<?= htmlspecialchars($pessoa['endereco']) ?>">
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="contato">Telefone / Contato</label>
                <input type="text" class="form-control" id="contato" name="contato" value="<?= htmlspecialchars($pessoa['contato']) ?>">
            </div>
            <div class="form-group col-md-6">
                <label for="email">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($pessoa['email']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="tipo">Tipo</label>
            <select class="custom-select" id="tipo" name="tipo" required>
                <option value="pessoa" <?= $pessoa['tipo'] == 'pessoa' ? 'selected' : '' ?>>Cliente (Pessoa)</option>
                <option value="fornecedor" <?= $pessoa['tipo'] == 'fornecedor' ? 'selected' : '' ?>>Fornecedor</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="cadastrar_pessoa_fornecedor.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include('../includes/footer.php'); ?>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
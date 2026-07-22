<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// 1. VERIFICA O LOGIN E PEGA A CONEXÃO
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: ../pages/login.php?error=not_logged_in");
    exit;
}
$conn = getTenantConnection();
if ($conn === null) {
    set_flash_message('danger', 'Erro de conexão com banco de dados.');
    header("Location: contas_pagar.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'] ?? null;
$id_conta = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if (!$id_usuario || $id_conta === 0) {
    set_flash_message('danger', 'Conta não identificada.');
    header("Location: contas_pagar.php");
    exit;
}

// LÓGICA DE ATUALIZAÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido.');
        header("Location: editar_conta_pagar.php?id=" . $id_conta);
        exit;
    }

    $id_pessoa_fornecedor = !empty($_POST['id_pessoa_fornecedor']) ? (int)$_POST['id_pessoa_fornecedor'] : null;
    
    // Tratamento de Dados
    $numero = trim($_POST['numero'] ?? ''); // Novo campo
    $data_vencimento = data_para_iso($_POST['data_vencimento']);
    $valor = brl_to_float($_POST['valor']); // Usa a função segura global

    $id_categoria = (int)$_POST['id_categoria'];
    $descricao = trim($_POST['descricao'] ?? '');

    // SQL Atualizado com o campo 'numero'
    $sql = "UPDATE contas_pagar SET 
                id_pessoa_fornecedor = ?, 
                numero = ?,
                data_vencimento = ?, 
                valor = ?, 
                id_categoria = ?, 
                descricao = ? 
            WHERE id = ? AND usuario_id = ?";
            
    $stmt = $conn->prepare($sql);
    // Tipos: i (int), s (string), s (string-data), d (double), i (int), s (string), i (int), i (int)
    $stmt->bind_param("issdisii", 
        $id_pessoa_fornecedor, 
        $numero,
        $data_vencimento, 
        $valor, 
        $id_categoria, 
        $descricao, 
        $id_conta, 
        $id_usuario
    );

    if ($stmt->execute()) {
        set_flash_message('success', 'Conta editada com sucesso!');
        header("Location: ../pages/contas_pagar.php");
    } else {
        set_flash_message('danger', 'Erro ao atualizar: ' . $stmt->error);
        header("Location: editar_conta_pagar.php?id=" . $id_conta);
    }
    $stmt->close();
    exit;
}

include('../includes/header.php');

// Exibe mensagem se houver (ex: erro de token)
display_flash_message();

// Busca a conta 
$stmt = $conn->prepare("SELECT * FROM contas_pagar WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $id_conta, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "<div class='container'><h3 style='color:#c0392b'>Conta não encontrada.</h3></div>";
    include('../includes/footer.php');
    exit;
}
$conta = $result->fetch_assoc();

// Dados para os selects
$categorias_result = $conn->query("SELECT id, nome FROM categorias WHERE id_usuario = $id_usuario AND tipo = 'despesa' ORDER BY nome");
$fornecedores_result = $conn->query("SELECT id, nome FROM pessoas_fornecedores WHERE id_usuario = $id_usuario AND (tipo = 'fornecedor' OR tipo = 'ambos') ORDER BY nome");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Editar Conta a Pagar</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #121212; color: #eee; }
        .container { background-color: #1f1f1f; padding: 25px; border-radius: 8px; margin-top: 30px; border: 1px solid #333; }
        .form-control { background-color: #333; color: #eee; border-color: #444; }
        .form-control:focus { background-color: #444; color: #fff; border-color: #00bfff; }
        label { color: #aaa; }
        h2 { color: #00bfff; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Editar Conta a Pagar</h2>
        
        <form method="POST" action="editar_conta_pagar.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($id_conta) ?>">

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="id_pessoa_fornecedor">Fornecedor</label>
                    <select class="form-control" id="id_pessoa_fornecedor" name="id_pessoa_fornecedor" required>
                        <option value="">Selecione...</option>
                        <?php 
                        if ($fornecedores_result) {
                            while($forn = $fornecedores_result->fetch_assoc()): 
                                $selected = ($forn['id'] == $conta['id_pessoa_fornecedor']) ? 'selected' : '';
                        ?>
                            <option value="<?= $forn['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($forn['nome']) ?>
                            </option>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group col-md-6">
                    <label for="numero">Número do Documento</label>
                    <input type="text" class="form-control" id="numero" name="numero" 
                           value="<?= htmlspecialchars($conta['numero'] ?? '') ?>" placeholder="Ex: 12345">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="valor">Valor (R$)</label>
                    <input type="text" class="form-control" id="valor" name="valor" 
                           value="<?= number_format($conta['valor'], 2, ',', '.') ?>" required 
                           onkeyup="formatarMoeda(this)">
                </div>

                <div class="form-group col-md-6">
                    <label for="data_vencimento">Data de Vencimento</label>
                    <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" value="<?= htmlspecialchars($conta['data_vencimento']) ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="id_categoria">Categoria</label>
                    <select class="form-control" id="id_categoria" name="id_categoria" required>
                        <option value="">Selecione...</option>
                        <?php 
                        if ($categorias_result) {
                            // Reinicia o ponteiro do resultado se necessário ou busca novamente se a lógica acima consumiu
                            $categorias_result->data_seek(0); 
                            while($cat = $categorias_result->fetch_assoc()): 
                                $selected = ($cat['id'] == $conta['id_categoria']) ? 'selected' : '';
                        ?>
                            <option value="<?= $cat['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group col-md-6">
                    <label for="descricao">Descrição / Observação</label>
                    <input type="text" class="form-control" id="descricao" name="descricao" value="<?= htmlspecialchars($conta['descricao'] ?? '') ?>">
                </div>
            </div>

            <hr style="border-color: #444;">
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="../pages/contas_pagar.php" class="btn btn-secondary">Cancelar</a>
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
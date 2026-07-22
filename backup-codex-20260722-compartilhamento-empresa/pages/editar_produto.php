<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// 1. Verifica login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php?erro=nao_logado');
    exit;
}

$conn = getTenantConnection();
if (!$conn) {
    die("Falha crítica: Não foi possível conectar ao banco de dados do cliente.");
}

$produto = null;
$erro = null;

// 3. Busca o produto
if (isset($_GET['id'])) {
    $id_produto = (int)$_GET['id'];
    $id_usuario = $_SESSION['usuario_id'];

    if ($stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ? AND id_usuario = ?")) {
        $stmt->bind_param("ii", $id_produto, $id_usuario);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $produto = $result->fetch_assoc();
            } else {
                set_flash_message('danger', "Produto não encontrado ou você não tem permissão.");
                header("Location: controle_estoque.php");
                exit;
            }
        } else {
            $erro = "Erro ao buscar produto.";
        }
        $stmt->close();
    }
} else {
    set_flash_message('danger', "ID não informado.");
    header("Location: controle_estoque.php");
    exit;
}

include('../includes/header.php');

// EXIBE MENSAGEM
display_flash_message();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #121212; color: #eee; }
        .container { background-color: #222; padding: 25px; border-radius: 8px; margin-top: 30px; }
        h2 { border-bottom: 2px solid #0af; padding-bottom: 10px; margin-bottom: 1rem; }
        .form-control { background-color: #333; color: #eee; border: 1px solid #444; }
        .form-control:focus { background-color: #333; color: #eee; border-color: #0af; box-shadow: none; }
        .btn-primary { background-color: #0af; border: none; }
        .btn-secondary { background-color: #555; border: none; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($erro) ?>
            </div>
            <a href="controle_estoque.php" class="btn btn-secondary">Voltar para Estoque</a>
        
        <?php elseif ($produto && is_array($produto)): ?>
            
            <h2><i class="fa-solid fa-pen-to-square"></i> Editar Produto</h2>
            
            <form action="../actions/editar_produto_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($produto['id']) ?>">
                
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="codigo">Código/SKU</label>
                        <input type="text" class="form-control" name="codigo" value="<?= htmlspecialchars($produto['codigo'] ?? '') ?>">
                    </div>

                    <div class="form-group col-md-5"> <label for="nome">Nome do Produto</label>
                        <input type="text" class="form-control" name="nome" value="<?= htmlspecialchars($produto['nome']) ?>" required>
                    </div>
                    <div class="form-group col-md-2"> <label for="quantidade_estoque">Qtd. em Estoque</label>
                        <input type="number" class="form-control" name="quantidade_estoque" value="<?= htmlspecialchars($produto['quantidade_estoque']) ?>" required>
                    </div>
                    <div class="form-group col-md-2"> <label for="quantidade_minima">Qtd. Mínima</label>
                        <input type="number" class="form-control" name="quantidade_minima" value="<?= htmlspecialchars($produto['quantidade_minima']) ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="2"><?= htmlspecialchars($produto['descricao']) ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="preco_compra">Preço de Compra</label>
                        <input type="text" class="form-control" name="preco_compra" placeholder="0,00" value="<?= number_format($produto['preco_compra'], 2, ',', '.') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="preco_venda">Preço de Venda</label>
                        <input type="text" class="form-control" name="preco_venda" placeholder="0,00" value="<?= number_format($produto['preco_venda'], 2, ',', '.') ?>" required>
                    </div>
                </div>
                
                <hr style="border-top: 1px solid #444;">
                <h5>Informações Fiscais (Opcional)</h5>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="ncm">NCM</label>
                        <input type="text" class="form-control" name="ncm" value="<?= htmlspecialchars($produto['ncm'] ?? '') ?>" placeholder="Ex: 84713000">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cfop">CFOP</label>
                        <input type="text" class="form-control" name="cfop" value="<?= htmlspecialchars($produto['cfop'] ?? '') ?>" placeholder="Ex: 5102">
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Salvar Alterações</button>
                    <a href="controle_estoque.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>

        <?php endif; ?>
    </div>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>
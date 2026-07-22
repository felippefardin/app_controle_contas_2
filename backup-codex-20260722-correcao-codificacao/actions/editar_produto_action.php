<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de seguranÃ§a invÃ¡lido.');
        header('Location: ../pages/controle_estoque.php');
        exit;
    }

    $conn = getTenantConnection();
    if (!$conn) {
        set_flash_message('danger', 'Erro de conexÃ£o.');
        header('Location: ../pages/controle_estoque.php');
        exit;
    }

    $id_usuario = get_data_owner_id();
    $id = intval($_POST['id']);
    
    // ADICIONADO: Captura do cÃ³digo
    $codigo = !empty($_POST['codigo']) ? $_POST['codigo'] : null;
    
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'] ?? '';
    $quantidade_estoque = intval($_POST['quantidade_estoque']);
    $quantidade_minima = intval($_POST['quantidade_minima'] ?? 0);

    // FormataÃ§Ã£o simples
    $preco_compra = !empty($_POST['preco_compra']) ? floatval(str_replace(['.',','], ['','.'], $_POST['preco_compra'])) : 0.00;
    $preco_venda = !empty($_POST['preco_venda']) ? floatval(str_replace(['.',','], ['','.'], $_POST['preco_venda'])) : 0.00;

    $ncm = $_POST['ncm'] ?? null;
    $cfop = $_POST['cfop'] ?? null;

    // ADICIONADO: codigo = ? no SQL
    $sql = "UPDATE produtos 
            SET codigo = ?, nome = ?, descricao = ?, quantidade_estoque = ?, quantidade_minima = ?, 
                preco_compra = ?, preco_venda = ?, ncm = ?, cfop = ?
            WHERE id = ? AND id_usuario = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // ADICIONADO: 's' no bind e $codigo nas variÃ¡veis
        $stmt->bind_param(
            "sssiiddssii",
            $codigo, $nome, $descricao,
            $quantidade_estoque, $quantidade_minima,
            $preco_compra, $preco_venda,
            $ncm, $cfop,
            $id, $id_usuario
        );

        if ($stmt->execute()) {
            set_flash_message('success', 'Produto atualizado com sucesso!');
            header('Location: ../pages/controle_estoque.php');
        } else {
            set_flash_message('danger', 'Erro ao atualizar produto.');
            header("Location: ../pages/editar_produto.php?id=$id");
        }
        $stmt->close();
    } else {
        set_flash_message('danger', 'Erro na preparaÃ§Ã£o da query.');
        header("Location: ../pages/editar_produto.php?id=$id");
    }
    exit;
} 

header('Location: ../pages/controle_estoque.php');
exit;
?>

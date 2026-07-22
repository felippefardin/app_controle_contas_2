<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// 1. VERIFICA O LOGIN
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. VERIFICA MÉTODO POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'Método inválido.');
    header('Location: ../pages/controle_estoque.php');
    exit;
}

// 3. VERIFICA CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de segurança inválido.');
    header('Location: ../pages/controle_estoque.php');
    exit;
}

$conn = getTenantConnection();
if ($conn === null) {
    set_flash_message('danger', 'Erro de conexão.');
    header('Location: ../pages/controle_estoque.php');
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$id_produto = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id_produto) {
    try {
        $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_produto, $id_usuario);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                set_flash_message('success', 'Produto excluído com sucesso.');
            } else {
                set_flash_message('danger', 'Produto não encontrado ou permissão negada.');
            }
        } else {
            set_flash_message('danger', 'Falha ao excluir o produto.');
        }
        $stmt->close();

    } catch (mysqli_sql_exception $e) {
        // Código 1451 é erro de chave estrangeira (foreign key constraint)
        if ($e->getCode() == 1451) {
            set_flash_message('danger', 'Não é possível excluir este produto pois ele já possui movimentações (compras ou vendas) registradas no histórico.');
        } else {
            // Outros erros de banco de dados
            set_flash_message('danger', 'Erro ao processar exclusão: ' . $e->getMessage());
        }
    } catch (Exception $e) {
        set_flash_message('danger', 'Erro inesperado.');
    }
} else {
    set_flash_message('danger', 'ID inválido.');
}

header('Location: ../pages/controle_estoque.php');
exit;
?>
<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa Flash Messages

// 1. Validação de Login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. Segurança (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'Método inválido.');
    header('Location: ../pages/categorias.php');
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de segurança inválido.');
    header('Location: ../pages/categorias.php');
    exit;
}

// 3. Processamento
$conn = getTenantConnection();
if ($conn === null) {
    set_flash_message('danger', 'Erro de conexão.');
    header('Location: ../pages/categorias.php');
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id) {
    // Verifica se a categoria pertence ao usuário antes de excluir
    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id, $usuarioId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            set_flash_message('success', 'Categoria excluída com sucesso!');
        } else {
            set_flash_message('danger', 'Categoria não encontrada ou permissão negada.');
        }
    } else {
        set_flash_message('danger', 'Erro ao excluir: ' . $stmt->error);
    }
    $stmt->close();
} else {
    set_flash_message('danger', 'ID inválido.');
}

header('Location: ../pages/categorias.php');
exit;
?>
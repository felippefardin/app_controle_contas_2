<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa Flash Messages

// 1. ValidaÃ§Ã£o de Login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. SeguranÃ§a (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'MÃ©todo invÃ¡lido.');
    header('Location: ../pages/categorias.php');
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de seguranÃ§a invÃ¡lido.');
    header('Location: ../pages/categorias.php');
    exit;
}

// 3. Processamento
$conn = getTenantConnection();
if ($conn === null) {
    set_flash_message('danger', 'Erro de conexÃ£o.');
    header('Location: ../pages/categorias.php');
    exit;
}

$usuarioId = get_data_owner_id();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id) {
    // Verifica se a categoria pertence ao usuÃ¡rio antes de excluir
    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id, $usuarioId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            set_flash_message('success', 'Categoria excluÃ­da com sucesso!');
        } else {
            set_flash_message('danger', 'Categoria nÃ£o encontrada ou permissÃ£o negada.');
        }
    } else {
        set_flash_message('danger', 'Erro ao excluir: ' . $stmt->error);
    }
    $stmt->close();
} else {
    set_flash_message('danger', 'ID invÃ¡lido.');
}

header('Location: ../pages/categorias.php');
exit;
?>

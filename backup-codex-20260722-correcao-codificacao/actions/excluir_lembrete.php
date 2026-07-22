<?php
require_once '../includes/session_init.php';
require_once '../database.php'; 
require_once '../includes/utils.php'; // Importa Flash Messages

// Verifica login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: ../pages/login.php");
    exit;
}

// SEGURANÃ‡A: Apenas POST e Token VÃ¡lido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'MÃ©todo invÃ¡lido.');
    header('Location: ../pages/lembrete.php');
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de seguranÃ§a invÃ¡lido.');
    header('Location: ../pages/lembrete.php');
    exit;
}

// Pega dados
$usuario_id = get_data_owner_id(); 
$conn = getTenantConnection();

$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

if ($id) {
    // Garante que sÃ³ exclui se for DONO do lembrete
    $stmt = $conn->prepare("DELETE FROM lembretes WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $id, $usuario_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            set_flash_message('success', 'Lembrete removido!');
        } else {
            set_flash_message('danger', 'PermissÃ£o negada ou lembrete nÃ£o encontrado.');
        }
    } else {
        set_flash_message('danger', 'Erro tÃ©cnico ao excluir.');
    }
    $stmt->close();
} else {
    set_flash_message('danger', 'ID invÃ¡lido.');
}

header('Location: ../pages/lembrete.php');
exit;
?>

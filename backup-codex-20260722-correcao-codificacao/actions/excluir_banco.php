<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// POST Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'MÃ©todo invÃ¡lido.');
    header('Location: ../pages/banco_cadastro.php');
    exit;
}

// CSRF Check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token invÃ¡lido.');
    header('Location: ../pages/banco_cadastro.php');
    exit;
}

$id_registro = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id_usuario = get_data_owner_id();

if ($id_registro) {
    $conn = getTenantConnection();
    if ($conn === null) {
        set_flash_message('danger', 'Erro de conexÃ£o.');
        header('Location: ../pages/banco_cadastro.php');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM contas_bancarias WHERE id = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id_registro, $id_usuario);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            set_flash_message('success', 'Conta bancÃ¡ria excluÃ­da!');
        } else {
            set_flash_message('danger', 'Conta nÃ£o encontrada ou sem permissÃ£o.');
        }
    } else {
        set_flash_message('danger', 'Erro ao excluir.');
    }
    $stmt->close();
    $conn->close();
} else {
    set_flash_message('danger', 'ID invÃ¡lido.');
}

header('Location: ../pages/banco_cadastro.php');
exit;
?>

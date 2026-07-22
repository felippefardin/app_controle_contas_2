<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

// 1. ValidaÃ§Ã£o de Login
if (!isset($_SESSION['usuario_logado'])) { 
    header('Location: ../pages/login.php'); 
    exit; 
}

// 2. SeguranÃ§a (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'MÃ©todo invÃ¡lido.');
    header('Location: ../pages/contas_receber.php');
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de seguranÃ§a invÃ¡lido.');
    header('Location: ../pages/contas_receber.php');
    exit;
}

// 3. Processamento
$id_conta = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$redirect = $_POST['redirect'] ?? '';
$usuario_id = get_data_owner_id();

if ($id_conta > 0) {
    $conn = getTenantConnection();
    
    // Garante que sÃ³ deleta se o ID for do usuÃ¡rio logado
    $stmt = $conn->prepare("DELETE FROM contas_receber WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $id_conta, $usuario_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            set_flash_message('success', 'Conta excluÃ­da com sucesso.');
        } else {
            set_flash_message('danger', 'Conta nÃ£o encontrada ou permissÃ£o negada.');
        }
    } else {
        set_flash_message('danger', 'Erro tÃ©cnico ao excluir.');
    }
    $stmt->close();
} else {
    set_flash_message('danger', 'ID invÃ¡lido.');
}

// 4. Redirecionamento
if ($redirect === 'baixadas') {
    header('Location: ../pages/contas_receber_baixadas.php');
} else {
    header('Location: ../pages/contas_receber.php');
}
exit;
?>

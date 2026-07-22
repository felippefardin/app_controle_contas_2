<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// 1. VERIFICA O LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php?error=not_logged_in');
    exit;
}

// 2. SEGURANÃ‡A: VERIFICA O MÃ‰TODO E O TOKEN CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "AÃ§Ã£o nÃ£o permitida (MÃ©todo invÃ¡lido).";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Token de seguranÃ§a invÃ¡lido. Tente novamente.";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

// 3. OBTÃ‰M DADOS (AGORA VIA POST)
$id_conta = isset($_POST['id']) ? intval($_POST['id']) : 0;
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : ''; 
$id_usuario = get_data_owner_id();

if ($id_conta > 0) {
    $conn = getTenantConnection();
    
    if ($conn) {
        // Prepara a exclusÃ£o garantindo que a conta pertence ao usuÃ¡rio logado
        $stmt = $conn->prepare("DELETE FROM contas_pagar WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $id_conta, $id_usuario);

        if ($stmt->execute()) {
            // Verifica se realmente deletou algo
            if ($stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Conta excluÃ­da com sucesso!";
            } else {
                $_SESSION['error_message'] = "Conta nÃ£o encontrada ou permissÃ£o negada.";
            }
        } else {
            $_SESSION['error_message'] = "Erro ao excluir a conta: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Falha na conexÃ£o com o banco de dados.";
    }
} else {
    $_SESSION['error_message'] = "Conta invÃ¡lida.";
}

// 4. REDIRECIONAMENTO INTELIGENTE
if ($redirect === 'baixadas') {
    header('Location: ../pages/contas_pagar_baixadas.php');
} else {
    header('Location: ../pages/contas_pagar.php');
}
exit;
?>

<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php');
    exit;
}

// POST & CSRF check
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Solicitação inválida ou expirada.');
    header('Location: ../pages/lancamento_caixa.php');
    exit;
}

$conn = getTenantConnection();
$id_usuario = $_SESSION['usuario_id'];
$id_lancamento = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id_lancamento) {
    $stmt = $conn->prepare("DELETE FROM caixa_diario WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $id_lancamento, $id_usuario);
    
    if ($stmt->execute()) {
        set_flash_message('success', 'Lançamento excluído com sucesso!');
    } else {
        set_flash_message('danger', 'Erro ao excluir: ' . $stmt->error);
    }
    $stmt->close();
} else {
    set_flash_message('danger', 'ID inválido.');
}

header('Location: ../pages/lancamento_caixa.php');
exit;
?>
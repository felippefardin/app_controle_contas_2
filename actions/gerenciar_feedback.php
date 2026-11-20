<?php
require_once '../includes/session_init.php';
include('../database.php');

require_super_admin();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

// Verificação de permissão admin necessária aqui

if (isset($_POST['acao']) && isset($_POST['id'])) {
    $conn = getMasterConnection();
    $id = intval($_POST['id']);
    $acao = $_POST['acao'];

    if ($acao === 'aprovar') {
        $stmt = $conn->prepare("UPDATE feedbacks SET aprovado = 1, lido = 1 WHERE id = ?");
    } elseif ($acao === 'reprovar') {
        $stmt = $conn->prepare("UPDATE feedbacks SET aprovado = 0, lido = 1 WHERE id = ?");
    } elseif ($acao === 'excluir') {
        $stmt = $conn->prepare("DELETE FROM feedbacks WHERE id = ?");
    } else {
        http_response_code(400);
        exit('Ação inválida.');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: ../pages/admin/feedback.php");
}
?>

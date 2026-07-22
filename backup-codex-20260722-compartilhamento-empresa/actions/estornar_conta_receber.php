<?php
require_once '../includes/session_init.php';
require_once '../database.php';

if (!isset($_SESSION['usuario_logado'])) { header('Location: ../pages/login.php'); exit; }

$id_conta = (int)$_GET['id'];
$conn = getTenantConnection();

if ($id_conta > 0) {
    $sql = "UPDATE contas_receber SET status='pendente', baixado_por=NULL, data_baixa=NULL, forma_pagamento=NULL, comprovante=NULL WHERE id=? AND usuario_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_conta, $_SESSION['usuario_id']);
    $stmt->execute();
    $_SESSION['success_message'] = "Conta estornada!";
    $stmt->close();
}

header('Location: ../pages/contas_receber_baixadas.php');
exit;
?>
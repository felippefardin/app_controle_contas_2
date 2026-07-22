<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// 1. Verifica sessão
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

$id_conta = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_usuario = $_SESSION['usuario_id'];

if ($id_conta > 0) {
    $conn = getTenantConnection();
    if ($conn) {
        // 2. Query de estorno
        // Define status como 'pendente' e NULL para os campos de pagamento
        $sql = "UPDATE contas_pagar 
                SET status = 'pendente', 
                    baixado_por = NULL, 
                    data_baixa = NULL, 
                    forma_pagamento = NULL,
                    juros = 0.00,
                    comprovante = NULL
                WHERE id = ? AND usuario_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id_conta, $id_usuario);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Conta estornada com sucesso! Ela voltou para a lista de pendentes.";
        } else {
            $_SESSION['error_message'] = "Erro ao estornar conta: " . $stmt->error;
        }
        $stmt->close();
    }
}

header('Location: ../pages/contas_pagar_baixadas.php');
exit;
?>
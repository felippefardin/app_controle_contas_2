<?php
require_once '../includes/session_init.php';
include('../database.php');
require_once '../includes/audit.php';

require_tenant_admin();
require_page_permission('contas_receber.php');
require_csrf();
$conn = getTenantConnection();
$adminEmail = (string)($_SESSION['email'] ?? '');
$ownerId = get_data_owner_id();

$id_conta = $_POST['id_conta'] ?? null;
$codigo = $_POST['codigo'] ?? null;

if (!$id_conta || !$codigo) {
    echo "Dados incompletos";
    exit;
}

// Verificar se o código é válido, não usado, criado nos últimos 10 minutos e corresponde ao e-mail do admin
$stmt = $conn->prepare("SELECT id FROM codigos_confirmacao WHERE codigo = ? AND email_admin = ? AND usado = 0 AND criado_em > (NOW() - INTERVAL 10 MINUTE)");
$stmt->bind_param("ss", $codigo, $adminEmail);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo "Código inválido ou expirado.";
    exit;
}

$stmt->bind_result($id_codigo);
$stmt->fetch();

// Marcar código como usado
$stmt_update = $conn->prepare("UPDATE codigos_confirmacao SET usado = 1 WHERE id = ?");
$stmt_update->bind_param("i", $id_codigo);
$stmt_update->execute();

// Excluir a conta a receber
soft_delete_record($conn, 'contas_receber', (int) $id_conta, $ownerId);

// Redirecionar para a lista de contas a receber
header('Location: ../pages/contas_receber.php');
exit;

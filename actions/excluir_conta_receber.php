<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/audit.php';
require_authenticated();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$redirect = (string) ($_POST['redirect'] ?? '');
$ownerId = get_data_owner_id();
$conn = getTenantConnection();
if ($id && $conn && soft_delete_record($conn, 'contas_receber', (int) $id, $ownerId)) {
    set_flash_message('success', 'Conta movida para a lixeira.');
} else {
    set_flash_message('danger', 'Conta não encontrada ou permissão negada.');
}
header('Location: ../pages/' . ($redirect === 'baixadas' ? 'contas_receber_baixadas.php' : 'contas_receber.php'));
exit;

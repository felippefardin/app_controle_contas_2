<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/audit.php';
require_tenant_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$ownId = (int) ($_SESSION['usuario_id'] ?? 0);
$ownerId = get_data_owner_id();
$conn = getTenantConnection();
if ($id && (int) $id !== $ownId && (int) $id !== $ownerId && $conn && soft_delete_record($conn, 'usuarios', (int) $id)) {
    set_flash_message('success', 'Usuário movido para a lixeira.');
} else {
    set_flash_message('danger', 'Usuário não encontrado ou não pode ser excluído.');
}
header('Location: ../pages/usuarios.php');
exit;

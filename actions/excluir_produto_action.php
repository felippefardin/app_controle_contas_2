<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/audit.php';
require_authenticated();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$ownerId = get_data_owner_id();
$conn = getTenantConnection();
if ($id && $conn && soft_delete_record($conn, 'produtos', (int) $id, $ownerId)) {
    set_flash_message('success', 'Produto movido para a lixeira.');
} else {
    set_flash_message('danger', 'Produto não encontrado ou permissão negada.');
}
header('Location: ../pages/controle_estoque.php');
exit;

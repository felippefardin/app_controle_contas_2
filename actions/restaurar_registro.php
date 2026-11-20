<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/audit.php';
require_tenant_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_csrf();
$entity = (string) ($_POST['entity'] ?? '');
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$conn = getTenantConnection();
if (!isset(SOFT_DELETE_ENTITIES[$entity])) {
    set_flash_message('danger', 'Tipo de registro inválido.');
    header('Location: ../pages/lixeira.php');
    exit;
}
$ownerId = $entity === 'usuarios' ? null : get_data_owner_id();
if ($id && $conn && restore_soft_deleted_record($conn, $entity, (int) $id, $ownerId)) {
    set_flash_message('success', 'Registro restaurado com sucesso.');
} else {
    set_flash_message('danger', 'Não foi possível restaurar o registro.');
}
header('Location: ../pages/lixeira.php');
exit;

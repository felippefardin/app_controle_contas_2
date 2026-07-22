<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/cupom_service.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['usuario_logado'])) {
    echo json_encode(['valid' => false, 'msg' => 'Acesso inválido.']); exit;
}
$codigo = strtoupper(trim($_POST['codigo'] ?? ''));
if ($codigo === '') { echo json_encode(['valid' => false, 'msg' => 'Código vazio.']); exit; }

$conn = getMasterConnection();
$tenant = buscarTenantCupom($conn, (string)($_SESSION['tenant_id'] ?? ''));
if (!$tenant) { echo json_encode(['valid' => false, 'msg' => 'Conta não identificada.']); exit; }
$validacao = validarCupomPassivo($conn, $codigo, (int)$tenant['id']);
if (!$validacao['ok']) { echo json_encode(['valid' => false, 'msg' => $validacao['msg']]); exit; }
$cupom = $validacao['cupom'];
echo json_encode([
    'valid' => true,
    'tipo' => $cupom['tipo_desconto'],
    'valor' => (float)$cupom['valor'],
    'codigo' => $cupom['codigo'],
    'aplicar_extras' => (bool)$cupom['aplicar_extras'],
]);

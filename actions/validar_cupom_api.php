<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/cupom_service.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'msg' => 'Acesso inválido.']); exit;
}

$validacaoCadastro = ($_POST['contexto'] ?? '') === 'registro';
if (empty($_SESSION['usuario_logado']) && !$validacaoCadastro) {
    echo json_encode(['valid' => false, 'msg' => 'Acesso inválido.']); exit;
}

$codigo = strtoupper(trim($_POST['codigo'] ?? ''));
if ($codigo === '') { echo json_encode(['valid' => false, 'msg' => 'Código vazio.']); exit; }

$conn = getMasterConnection();
if ($validacaoCadastro && empty($_SESSION['usuario_logado'])) {
    // No cadastro público o tenant ainda não existe. As regras específicas
    // da conta serão verificadas novamente no checkout.
    $tenantId = 0;
} else {
    $tenant = buscarTenantCupom($conn, (string)($_SESSION['tenant_id'] ?? ''));
    if (!$tenant) { echo json_encode(['valid' => false, 'msg' => 'Conta não identificada.']); exit; }
    $tenantId = (int)$tenant['id'];
}

$validacao = validarCupomPassivo($conn, $codigo, $tenantId);
if (!$validacao['ok']) { echo json_encode(['valid' => false, 'msg' => $validacao['msg']]); exit; }
$cupom = $validacao['cupom'];
echo json_encode([
    'valid' => true,
    'tipo' => $cupom['tipo_desconto'],
    'valor' => (float)$cupom['valor'],
    'codigo' => $cupom['codigo'],
    'aplicar_extras' => (bool)$cupom['aplicar_extras'],
]);

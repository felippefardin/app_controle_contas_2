<?php
// actions/validar_cupom_api.php
require_once '../includes/session_init.php';
include('../database.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'msg' => 'Método inválido']);
    exit;
}

$codigo = strtoupper(trim($_POST['codigo'] ?? ''));
if (empty($codigo)) {
    echo json_encode(['valid' => false, 'msg' => 'Código vazio']);
    exit;
}

$conn = getMasterConnection();
$hoje = date('Y-m-d');

// Busca cupom ativo e dentro da validade
$sql = "SELECT * FROM cupons_desconto 
        WHERE codigo = ? 
        AND ativo = 1 
        AND (data_expiracao IS NULL OR data_expiracao >= ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $codigo, $hoje);
$stmt->execute();
$res = $stmt->get_result();

if ($cupom = $res->fetch_assoc()) {
    echo json_encode([
        'valid' => true,
        'tipo' => $cupom['tipo_desconto'],
        'valor' => floatval($cupom['valor']),
        'codigo' => $cupom['codigo']
    ]);
} else {
    echo json_encode(['valid' => false, 'msg' => 'Cupom inválido ou expirado.']);
}
$conn->close();
?>
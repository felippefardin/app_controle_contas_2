<?php
// actions/checkout_plano.php
session_start();
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/cupom_service.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// 🔹 SELEÇÃO DE AMBIENTE
$modo = $_ENV['MERCADOPAGO_MODE'] ?? 'sandbox';
$mp_access_token = ($modo === 'producao') ? $_ENV['MP_ACCESS_TOKEN_PRODUCAO'] : $_ENV['MP_ACCESS_TOKEN_SANDBOX'];
$mp_back_url     = ($modo === 'producao') ? $_ENV['MP_BACK_URL_PRODUCAO']     : $_ENV['MP_BACK_URL_SANDBOX'];
$payer_email = ($modo === 'sandbox') ? ($_ENV['MP_TEST_PAYER_EMAIL'] ?? '') : $_SESSION['email'];

if (empty($mp_access_token)) {
    die("Erro: Token do Mercado Pago não configurado no .env");
}
if (empty($payer_email)) {
    die("Erro: E-mail do comprador de teste não configurado no .env");
}

if (!isset($_SESSION['usuario_logado']) || !$_SESSION['tenant_id']) {
    header("Location: ../pages/login.php");
    exit;
}

$plano = $_POST['plano'] ?? 'basico';
$id_conta_receber = $_POST['id_conta'] ?? ''; 
$tenant_id = $_SESSION['tenant_id'];
$codigo_cupom = strtoupper(trim($_POST['cupom'] ?? ''));

$valor_plano = match($plano) {
    'plus' => 39.00,
    'essencial' => 59.00,
    default => 19.00
};

$conn = getMasterConnection();
$tenant = buscarTenantCupom($conn, $tenant_id);
if (!$tenant) die('Erro: conta não identificada.');
$valor_extras = max(0, (int)($tenant['usuarios_extras'] ?? 0)) * 4.00;
$cupom = null;
$cupom_passivo = false;
if ($codigo_cupom !== '') {
    $validacao = validarCupomPassivo($conn, $codigo_cupom, (int)$tenant['id']);
    if (!$validacao['ok']) die('Cupom recusado: ' . htmlspecialchars($validacao['msg']));
    $cupom = $validacao['cupom'];
    $cupom_passivo = true;
} else {
    $cupom = buscarPromocaoInterna($conn, (int)$tenant['id']);
}
$calculo = $cupom
    ? calcularDescontoCupom($valor_plano, $valor_extras, $cupom)
    : ['original' => $valor_plano + $valor_extras, 'final' => $valor_plano + $valor_extras, 'desconto' => 0];
$valor_cobranca = max(0.01, $calculo['final']);
$cupom_id = $cupom ? (int)$cupom['id'] : 0;

// 🔹 REFERÊNCIA FORMATADA: "TENANT_ID|ID_CONTA"
$external_reference = $tenant_id . '|' . $id_conta_receber . '|' . ($cupom_passivo ? $cupom_id : 0);

$data = [
    "payer_email" => $payer_email,
    "back_url" => $mp_back_url,
    "reason" => "Assinatura " . ucfirst($plano) . ($cupom ? " - Cupom " . $cupom['codigo'] : '') . " - App Controle",
    "external_reference" => $external_reference,
    "auto_recurring" => [
        "frequency" => 1,
        "frequency_type" => "months",
        "transaction_amount" => $valor_cobranca,
        "currency_id" => "BRL"
    ],
    "status" => "pending"
];

$ch = curl_init("https://api.mercadopago.com/preapproval");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $mp_access_token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$mp_response = json_decode($response, true);

if (isset($mp_response['init_point'])) {
    $stmt = $conn->prepare("UPDATE tenants SET plano_atual = ?, id_assinatura_mp = ? WHERE tenant_id = ?");
    $stmt->bind_param("sss", $plano, $mp_response['id'], $tenant_id);
    $stmt->execute();
    $stmt->close();
    if ($cupom_passivo) {
        $statusUso = 'pendente';
        $stmtUso = $conn->prepare("INSERT INTO cupom_utilizacoes (cupom_id, tenant_id, mp_preapproval_id, status, valor_original, valor_final) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE mp_preapproval_id=VALUES(mp_preapproval_id), status='pendente', valor_original=VALUES(valor_original), valor_final=VALUES(valor_final)");
        $tenantDbId = (int)$tenant['id'];
        $mpPreapprovalId = (string)$mp_response['id'];
        $valorOriginal = (float)$calculo['original'];
        $valorFinal = (float)$calculo['final'];
        $stmtUso->bind_param('iissdd', $cupom_id, $tenantDbId, $mpPreapprovalId, $statusUso, $valorOriginal, $valorFinal);
        $stmtUso->execute();
        $stmtUso->close();
    }
    $conn->close();

    header("Location: " . $mp_response['init_point']);
    exit;
} else {
    // 🔹 PREVINE TELA EM BRANCO: Exibe o erro real da API
    echo "<h3>Erro ao gerar checkout</h3>";
    echo "<p>Código HTTP: $httpCode</p>";
    echo "<pre>";
    print_r($mp_response);
    echo "</pre>";
    exit;
}

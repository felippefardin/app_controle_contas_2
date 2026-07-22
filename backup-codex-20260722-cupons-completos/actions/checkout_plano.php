<?php
// actions/checkout_plano.php
session_start();
require_once '../database.php';
require_once '../includes/utils.php';

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

$valor_plano = match($plano) {
    'plus' => 39.00,
    'essencial' => 59.00,
    default => 19.00
};

// 🔹 REFERÊNCIA FORMATADA: "TENANT_ID|ID_CONTA"
$external_reference = $tenant_id . ($id_conta_receber ? "|" . $id_conta_receber : "");

$data = [
    "payer_email" => $payer_email,
    "back_url" => $mp_back_url,
    "reason" => "Assinatura " . ucfirst($plano) . " - App Controle",
    "external_reference" => $external_reference,
    "auto_recurring" => [
        "frequency" => 1,
        "frequency_type" => "months",
        "transaction_amount" => $valor_plano,
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
    $conn = getMasterConnection();
    $stmt = $conn->prepare("UPDATE tenants SET plano_atual = ?, id_assinatura_mp = ? WHERE tenant_id = ?");
    $stmt->bind_param("sss", $plano, $mp_response['id'], $tenant_id);
    $stmt->execute();
    $stmt->close();
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

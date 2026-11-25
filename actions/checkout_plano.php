<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/cupom_service.php';
require_once __DIR__ . '/../includes/indicacao_reward_service.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';

require_tenant_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}
require_csrf();

$planos = ['basico' => 19.00, 'plus' => 39.00, 'essencial' => 59.00];
$plano = strtolower(trim((string) ($_POST['plano'] ?? '')));
if (!isset($planos[$plano])) {
    http_response_code(422);
    exit('Plano inválido.');
}

$tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
$contaId = filter_var($_POST['id_conta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$contaId = $contaId === false ? null : (int) $contaId;
$codigoCupom = strtoupper(trim((string) ($_POST['cupom'] ?? '')));
$payerEmail = mp_mode() === 'sandbox'
    ? trim((string) ($_ENV['MP_TEST_PAYER_EMAIL'] ?? ''))
    : trim((string) ($_SESSION['email'] ?? ''));

if ($tenantId === '' || $payerEmail === '' || mp_access_token() === '' || mp_back_url() === '') {
    error_log('Checkout MP: configuração incompleta ou tenant não identificado.');
    header('Location: ../pages/minha_assinatura.php?erro=configuracao_pagamento');
    exit;
}

$conn = getMasterConnection();
$tenant = buscarTenantCupom($conn, $tenantId);
if (!$tenant) {
    http_response_code(404);
    exit('Conta não identificada.');
}

// O checkout de adesão não pode criar outra recorrência para um assinante ativo.
$stmtRecorrencia = $conn->prepare("SELECT 1 FROM tenants WHERE tenant_id=? AND status_assinatura='ativo' AND COALESCE(id_assinatura_mp, '') <> '' LIMIT 1");
$stmtRecorrencia->bind_param('s', $tenantId);
$stmtRecorrencia->execute();
$temRecorrenciaAtiva = (bool) $stmtRecorrencia->get_result()->fetch_row();
$stmtRecorrencia->close();
if ($temRecorrenciaAtiva) {
    header('Location: ../pages/minha_assinatura.php?aviso=recorrencia_ativa', true, 303);
    exit;
}

$mesesGratisIndicacao = (int)($tenant['indicacao_beneficio_utilizado'] ?? 0) === 0
    ? min(2, max(0, (int)($tenant['indicacao_meses_gratis'] ?? 0)))
    : 0;
$mesesGratisIndicador = mesesPendentesIndicador($conn, (int)$tenant['usuario_id']);
$totalMesesGratis = $mesesGratisIndicacao + $mesesGratisIndicador;
$inicioCobrancaDb = null;
$inicioCobrancaMp = null;
if ($totalMesesGratis > 0) {
    $inicioCobranca = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . $totalMesesGratis . ' months');
    $inicioCobrancaDb = $inicioCobranca->format('Y-m-d H:i:s');
    $inicioCobrancaMp = $inicioCobranca->format('Y-m-d\TH:i:s.000\Z');
}

$valorExtras = max(0, (int) ($tenant['usuarios_extras'] ?? 0)) * 4.00;
$cupom = null;
$cupomPassivo = false;
if ($codigoCupom !== '') {
    $validacao = validarCupomPassivo($conn, $codigoCupom, (int) $tenant['id']);
    if (!$validacao['ok']) {
        header('Location: ../pages/minha_assinatura.php?erro=cupom_recusado');
        exit;
    }
    $cupom = $validacao['cupom'];
    $cupomPassivo = true;
} else {
    $cupom = buscarPromocaoInterna($conn, (int) $tenant['id']);
}

$calculo = $cupom
    ? calcularDescontoCupom($planos[$plano], $valorExtras, $cupom)
    : ['original' => $planos[$plano] + $valorExtras, 'final' => $planos[$plano] + $valorExtras, 'desconto' => 0];
$valorCobranca = max(0.01, (float) $calculo['final']);
$cupomId = $cupom ? (int) $cupom['id'] : null;
$promocaoId = $cupom && !$cupomPassivo ? (int)($cupom['promocao_id'] ?? 0) : null;

// Reutiliza um checkout recente, evitando duas assinaturas por clique duplo/atualização.
$reuse = $conn->prepare("SELECT init_point FROM checkout_assinaturas
    WHERE tenant_id=? AND plano=? AND status IN ('pendente','criando')
      AND COALESCE(conta_receber_id, 0)=? AND COALESCE(cupom_id, 0)=?
      AND criado_em >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY id DESC LIMIT 1");
$contaBusca = $contaId ?? 0;
$cupomBusca = $cupomId ?? 0;
$reuse->bind_param('ssii', $tenantId, $plano, $contaBusca, $cupomBusca);
$reuse->execute();
$existing = $reuse->get_result()->fetch_assoc();
$reuse->close();
if (!empty($existing['init_point'])) {
    header('Location: ' . $existing['init_point']);
    exit;
}

$checkoutToken = bin2hex(random_bytes(32));
$idempotencyKey = bin2hex(random_bytes(32));
$statusCheckout = 'criando';
$valorOriginal = (float) $calculo['original'];
$insert = $conn->prepare('INSERT INTO checkout_assinaturas
    (checkout_token, tenant_id, plano, conta_receber_id, cupom_id, idempotency_key,
     valor_original, valor_final, meses_gratis_indicacao, meses_gratis_indicador,
     inicio_cobranca, promocao_id, status, solicitado_por)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->bind_param(
    'sssiisddiisisi',
    $checkoutToken,
    $tenantId,
    $plano,
    $contaId,
    $cupomId,
    $idempotencyKey,
    $valorOriginal,
    $valorCobranca,
    $mesesGratisIndicacao,
    $mesesGratisIndicador,
    $inicioCobrancaDb,
    $promocaoId,
    $statusCheckout,
    $usuarioId
);
$insert->execute();
$checkoutId = (int) $conn->insert_id;
$insert->close();
if ($mesesGratisIndicador > 0) {
    reservarRecompensasIndicador($conn, (int)$tenant['usuario_id'], $checkoutId);
}

$payload = [
    'payer_email' => $payerEmail,
    'back_url' => mp_back_url(),
    'reason' => 'Assinatura ' . ucfirst($plano)
        . ($cupom ? ' - Cupom ' . $cupom['codigo'] : '')
        . ($mesesGratisIndicacao > 0 ? ' - Indicação: 2 meses grátis' : '')
        . ($mesesGratisIndicador > 0 ? " - Prêmio: {$mesesGratisIndicador} mês(es)" : '')
        . ' - App Controle',
    'external_reference' => $checkoutToken,
    'auto_recurring' => [
        'frequency' => 1,
        'frequency_type' => 'months',
        'transaction_amount' => $valorCobranca,
        'currency_id' => 'BRL',
    ],
    'status' => 'pending',
];

if ($inicioCobrancaMp !== null) {
    // Na assinatura sem plano associado, start_date define quando ocorrerá
    // a primeira cobrança recorrente.
    $payload['auto_recurring']['start_date'] = $inicioCobrancaMp;
}

try {
    $result = mp_api_request('POST', '/preapproval', $payload, $idempotencyKey);
    $mp = $result['body'];
    if (empty($mp['id']) || empty($mp['init_point'])) throw new RuntimeException('Checkout incompleto.');

    $mpId = (string) $mp['id'];
    $initPoint = (string) $mp['init_point'];
    $statusCheckout = 'pendente';
    $update = $conn->prepare('UPDATE checkout_assinaturas SET status=?, mp_preapproval_id=?, init_point=? WHERE id=?');
    $update->bind_param('sssi', $statusCheckout, $mpId, $initPoint, $checkoutId);
    $update->execute();
    $update->close();

    if ($cupomPassivo && $cupomId !== null) {
        $statusUso = 'pendente';
        $tenantDbId = (int) $tenant['id'];
        $stmt = $conn->prepare("INSERT INTO cupom_utilizacoes
            (cupom_id, tenant_id, mp_preapproval_id, status, valor_original, valor_final)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status='pendente', valor_original=VALUES(valor_original), valor_final=VALUES(valor_final)");
        $stmt->bind_param('iissdd', $cupomId, $tenantDbId, $mpId, $statusUso, $valorOriginal, $valorCobranca);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    header('Location: ' . $initPoint);
    exit;
} catch (Throwable $e) {
    error_log('Checkout MP falhou: ' . $e->getMessage());
    $statusCheckout = 'erro';
    $update = $conn->prepare('UPDATE checkout_assinaturas SET status=? WHERE id=?');
    $update->bind_param('si', $statusCheckout, $checkoutId);
    $update->execute();
    $update->close();
    $conn->close();
    header('Location: ../pages/minha_assinatura.php?erro=checkout_indisponivel');
    exit;
}

<?php

declare(strict_types=1);

define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = (string) file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$type = (string) ($data['type'] ?? $data['topic'] ?? 'desconhecido');
$action = (string) ($data['action'] ?? '');
$resourceId = (string) ($_GET['data_id'] ?? $_GET['id'] ?? $data['data']['id'] ?? $data['id'] ?? '');
$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
$signature = trim((string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''));
$secret = mp_webhook_secret();
$allowUnsignedSandbox = mp_mode() === 'sandbox'
    && filter_var($_ENV['MP_ALLOW_UNSIGNED_SANDBOX'] ?? false, FILTER_VALIDATE_BOOL);
$signatureValid = mp_validate_webhook_signature($signature, $requestId, $resourceId, $secret);

if ($resourceId === '' || $type === 'desconhecido') {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}
if (!$signatureValid && !$allowUnsignedSandbox) {
    error_log('Webhook Mercado Pago rejeitado: assinatura inválida.');
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
if ($requestId === '') {
    // Somente permitido no modo de compatibilidade sandbox explicitamente habilitado.
    $requestId = 'sandbox-' . hash('sha256', $raw);
}

$conn = getMasterConnection();
$signatureFlag = $signatureValid ? 1 : 0;
$receivedStatus = 'recebido';
$safePayload = json_encode([
    'type' => $type,
    'action' => $action,
    'data' => ['id' => $resourceId],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$event = $conn->prepare("INSERT INTO webhook_eventos_mp
    (request_id, resource_id, tipo, acao, assinatura_valida, status, payload)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE tentativas=tentativas+1");
$event->bind_param('ssssiss', $requestId, $resourceId, $type, $action, $signatureFlag, $receivedStatus, $safePayload);
$event->execute();
$event->close();

$eventCheck = $conn->prepare('SELECT id, status FROM webhook_eventos_mp WHERE request_id=? AND resource_id=? AND tipo=? LIMIT 1');
$eventCheck->bind_param('sss', $requestId, $resourceId, $type);
$eventCheck->execute();
$eventRow = $eventCheck->get_result()->fetch_assoc();
$eventCheck->close();
$eventId = (int) ($eventRow['id'] ?? 0);
if (($eventRow['status'] ?? '') === 'processado') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'duplicate' => true]);
    exit;
}

try {
    if (in_array($type, ['subscription_preapproval', 'subscription_preapproval.updated'], true)) {
        $sub = mp_api_request('GET', '/preapproval/' . rawurlencode($resourceId))['body'];
        $externalReference = (string) ($sub['external_reference'] ?? '');
        $checkout = mp_checkout_by_reference($conn, $externalReference);
        $statusMp = (string) ($sub['status'] ?? '');

        if ($checkout) {
            if (!empty($checkout['mp_preapproval_id']) && !hash_equals((string) $checkout['mp_preapproval_id'], $resourceId)) {
                throw new RuntimeException('Assinatura divergente do checkout.');
            }
            $checkoutStatus = match ($statusMp) {
                'authorized' => 'autorizado',
                'cancelled', 'canceled' => 'cancelado',
                'paused' => 'suspenso',
                default => 'pendente',
            };
            $checkoutUpdate = $conn->prepare('UPDATE checkout_assinaturas SET status=?, mp_preapproval_id=? WHERE id=?');
            $checkoutId = (int) $checkout['id'];
            $checkoutUpdate->bind_param('ssi', $checkoutStatus, $resourceId, $checkoutId);
            $checkoutUpdate->execute();
            $checkoutUpdate->close();

            if ($statusMp === 'authorized') {
                $systemStatus = 'ativo';
                $tenantUpdate = $conn->prepare('UPDATE tenants SET plano_atual=?, status_assinatura=?, id_assinatura_mp=? WHERE tenant_id=?');
                $tenantUpdate->bind_param('ssss', $checkout['plano'], $systemStatus, $resourceId, $checkout['tenant_id']);
                $tenantUpdate->execute();
                $tenantUpdate->close();
            } else {
                // Não derruba um plano ativo durante uma tentativa de troca ainda pendente.
                $tenantUpdate = $conn->prepare("UPDATE tenants SET status_assinatura=? WHERE tenant_id=? AND id_assinatura_mp=?");
                $systemStatus = in_array($statusMp, ['cancelled', 'canceled'], true) ? 'cancelado' : ($statusMp === 'paused' ? 'suspenso' : 'pendente');
                $tenantUpdate->bind_param('sss', $systemStatus, $checkout['tenant_id'], $resourceId);
                $tenantUpdate->execute();
                $tenantUpdate->close();
            }
        } else {
            // Compatibilidade segura com assinaturas criadas antes desta etapa.
            $legacy = explode('|', $externalReference);
            $legacyTenant = trim((string) ($legacy[0] ?? ''));
            if ($legacyTenant === '') throw new RuntimeException('Checkout não reconhecido.');
            $systemStatus = match ($statusMp) {
                'authorized' => 'ativo',
                'paused' => 'suspenso',
                'cancelled', 'canceled' => 'cancelado',
                default => 'pendente',
            };
            $legacyUpdate = $conn->prepare('UPDATE tenants SET status_assinatura=? WHERE tenant_id=? AND id_assinatura_mp=?');
            $legacyUpdate->bind_param('sss', $systemStatus, $legacyTenant, $resourceId);
            $legacyUpdate->execute();
            if ($legacyUpdate->affected_rows < 1) throw new RuntimeException('Assinatura antiga não pertence ao tenant.');
            $legacyUpdate->close();
        }

        $couponStatus = $statusMp === 'authorized' ? 'aplicado' : (in_array($statusMp, ['cancelled', 'canceled'], true) ? 'cancelado' : 'pendente');
        $appliedAt = $couponStatus === 'aplicado' ? date('Y-m-d H:i:s') : null;
        $coupon = $conn->prepare('UPDATE cupom_utilizacoes SET status=?, aplicado_em=? WHERE mp_preapproval_id=?');
        $coupon->bind_param('sss', $couponStatus, $appliedAt, $resourceId);
        $coupon->execute();
        $coupon->close();
    } elseif (in_array($type, ['payment', 'payment.created', 'payment.updated', 'subscription_authorized_payment'], true)) {
        $transactionId = $resourceId;
        if ($type === 'subscription_authorized_payment') {
            $authorizedPayment = mp_api_request('GET', '/authorized_payments/' . rawurlencode($resourceId))['body'];
            $paymentId = (string) ($authorizedPayment['payment']['id'] ?? '');
            if ($paymentId !== '') {
                $payment = mp_api_request('GET', '/v1/payments/' . rawurlencode($paymentId))['body'];
                $transactionId = $paymentId;
            } else {
                $payment = [
                    'external_reference' => $authorizedPayment['external_reference'] ?? '',
                    'status' => $authorizedPayment['payment']['status'] ?? $authorizedPayment['summarized'] ?? 'pending',
                    'transaction_amount' => $authorizedPayment['transaction_amount'] ?? 0,
                    'payment_type_id' => 'assinatura_mercado_pago',
                    'date_created' => $authorizedPayment['date_created'] ?? 'now',
                    'date_approved' => null,
                ];
                $transactionId = 'authorized-' . $resourceId;
            }
        } else {
            $payment = mp_api_request('GET', '/v1/payments/' . rawurlencode($resourceId))['body'];
        }
        $externalReference = (string) ($payment['external_reference'] ?? $authorizedPayment['external_reference'] ?? '');
        $checkout = mp_checkout_by_reference($conn, $externalReference);
        if (!$checkout && !empty($authorizedPayment['preapproval_id'])) {
            $preapprovalId = (string) $authorizedPayment['preapproval_id'];
            $checkoutStmt = $conn->prepare('SELECT * FROM checkout_assinaturas WHERE mp_preapproval_id=? LIMIT 1');
            $checkoutStmt->bind_param('s', $preapprovalId);
            $checkoutStmt->execute();
            $checkout = $checkoutStmt->get_result()->fetch_assoc() ?: null;
            $checkoutStmt->close();
        }
        $legacy = $checkout ? [] : explode('|', $externalReference);
        $tenantId = (string) ($checkout['tenant_id'] ?? $legacy[0] ?? '');
        $accountId = $checkout ? ($checkout['conta_receber_id'] ?? null) : ($legacy[1] ?? null);
        $couponId = (int) ($checkout['cupom_id'] ?? $legacy[2] ?? 0);
        if ($tenantId === '') throw new RuntimeException('Pagamento sem tenant reconhecido.');

        $statusMp = (string) ($payment['status'] ?? '');
        $dbStatus = $statusMp === 'approved' ? 'pago' : (in_array($statusMp, ['cancelled', 'canceled', 'rejected'], true) ? 'cancelado' : 'pendente');
        $amount = (float) ($payment['transaction_amount'] ?? 0);
        $method = (string) ($payment['payment_type_id'] ?? $payment['payment_method_id'] ?? 'mercado_pago');
        $dueDate = date('Y-m-d', strtotime((string) ($payment['date_created'] ?? 'now')));
        $paidAt = $statusMp === 'approved' ? date('Y-m-d', strtotime((string) ($payment['date_approved'] ?? 'now'))) : null;

        $invoice = $conn->prepare("INSERT INTO faturas_assinatura
            (tenant_id, valor, data_vencimento, data_pagamento, status, forma_pagamento, transacao_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE valor=VALUES(valor), data_pagamento=VALUES(data_pagamento), status=VALUES(status), forma_pagamento=VALUES(forma_pagamento)");
        $invoice->bind_param('sdsssss', $tenantId, $amount, $dueDate, $paidAt, $dbStatus, $method, $transactionId);
        $invoice->execute();
        $invoice->close();

        if ($couponId > 0) {
            $couponStatus = $statusMp === 'approved' ? 'aplicado' : ($dbStatus === 'cancelado' ? 'cancelado' : 'pendente');
            $appliedAt = $couponStatus === 'aplicado' ? date('Y-m-d H:i:s') : null;
            $coupon = $conn->prepare('UPDATE cupom_utilizacoes cu JOIN tenants t ON t.id=cu.tenant_id SET cu.status=?, cu.aplicado_em=? WHERE cu.cupom_id=? AND t.tenant_id=?');
            $coupon->bind_param('ssis', $couponStatus, $appliedAt, $couponId, $tenantId);
            $coupon->execute();
            $coupon->close();
        }

        if ($statusMp === 'approved' && $accountId) {
            $tenantStmt = $conn->prepare('SELECT usuario_id, db_host, db_database, db_user, db_password FROM tenants WHERE tenant_id=? LIMIT 1');
            $tenantStmt->bind_param('s', $tenantId);
            $tenantStmt->execute();
            $tenant = $tenantStmt->get_result()->fetch_assoc();
            $tenantStmt->close();
            if ($tenant) {
                $tenantConn = new mysqli($tenant['db_host'], $tenant['db_user'], $tenant['db_password'], $tenant['db_database']);
                $tenantConn->set_charset('utf8mb4');
                $note = 'Baixa automática via Mercado Pago (Transação: ' . $transactionId . ')';
                $ownerId = (int) $tenant['usuario_id'];
                $accountId = (int) $accountId;
                $lower = $tenantConn->prepare("UPDATE contas_receber SET status='baixada', data_baixa=?, forma_pagamento=?, observacao=? WHERE id=? AND usuario_id=? AND status<>'baixada'");
                $lower->bind_param('sssii', $paidAt, $method, $note, $accountId, $ownerId);
                $lower->execute();
                $lower->close();
                $tenantConn->close();
            }
        }
    } else {
        $ignored = 'ignorado';
        $done = $conn->prepare('UPDATE webhook_eventos_mp SET status=?, processado_em=NOW() WHERE id=?');
        $done->bind_param('si', $ignored, $eventId);
        $done->execute();
        $done->close();
        http_response_code(200);
        echo json_encode(['ok' => true, 'ignored' => true]);
        exit;
    }

    $processed = 'processado';
    $done = $conn->prepare('UPDATE webhook_eventos_mp SET status=?, erro=NULL, processado_em=NOW() WHERE id=?');
    $done->bind_param('si', $processed, $eventId);
    $done->execute();
    $done->close();
    $conn->close();
    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Webhook Mercado Pago falhou: ' . $e->getMessage());
    $failed = 'erro';
    $safeError = mb_substr($e->getMessage(), 0, 500);
    $failure = $conn->prepare('UPDATE webhook_eventos_mp SET status=?, erro=? WHERE id=?');
    $failure->bind_param('ssi', $failed, $safeError, $eventId);
    $failure->execute();
    $failure->close();
    $conn->close();
    http_response_code(500);
    echo json_encode(['ok' => false]);
}

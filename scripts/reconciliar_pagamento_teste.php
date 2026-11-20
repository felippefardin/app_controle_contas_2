<?php

declare(strict_types=1);

// Uso: php scripts/reconciliar_pagamento_teste.php ID_DA_ASSINATURA
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';

try {
    $id = $argv[1] ?? '';
    if (!preg_match('/\A[a-f0-9]{32}\z/', $id)) {
        throw new RuntimeException('Informe o ID da assinatura de teste.');
    }

    $secret = mp_webhook_secret();
    if (mp_mode() !== 'sandbox' || $secret === '' ||
        filter_var($_ENV['MP_ALLOW_UNSIGNED_SANDBOX'] ?? false, FILTER_VALIDATE_BOOL)) {
        throw new RuntimeException('O sandbox precisa exigir assinatura válida no webhook.');
    }

    $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
    if (!preg_match('~\Ahttp://(?:localhost|127\.0\.0\.1)(?::[0-9]+)?(?:/[a-zA-Z0-9_-]+)*\z~', $appUrl)) {
        throw new RuntimeException('APP_URL deve apontar para o XAMPP local.');
    }

    $db = getMasterConnection();
    $stmt = $db->prepare('SELECT checkout_token FROM checkout_assinaturas WHERE mp_preapproval_id=? LIMIT 1');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $checkout = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$checkout) {
        throw new RuntimeException('Checkout local não encontrado.');
    }

    $subscription = mp_api_request('GET', '/preapproval/' . $id)['body'];
    if (($subscription['status'] ?? '') !== 'authorized' ||
        !hash_equals((string) $checkout['checkout_token'], (string) ($subscription['external_reference'] ?? ''))) {
        throw new RuntimeException('A API não confirmou a autorização deste checkout.');
    }

    $offset = 0;
    $processed = 0;
    do {
        $query = http_build_query(['preapproval_id' => $id, 'limit' => 50, 'offset' => $offset]);
        $batch = mp_api_request('GET', '/authorized_payments/search?' . $query)['body'];
        $entries = $batch['results'] ?? [];
        foreach ($entries as $entry) {
            $authorizedId = (string) ($entry['id'] ?? '');
            $paymentId = (string) ($entry['payment']['id'] ?? '');
            if (!ctype_digit($authorizedId) || !ctype_digit($paymentId)
                || (string) ($entry['preapproval_id'] ?? '') !== $id) {
                continue;
            }
            $payment = mp_api_request('GET', '/v1/payments/' . $paymentId)['body'];
            if (($payment['status'] ?? '') !== 'approved') {
                continue;
            }

            $requestId = 'local-reconciliacao-' . $authorizedId;
            $time = (string) (int) floor(microtime(true) * 1000);
            $signature = hash_hmac('sha256', "id:$authorizedId;request-id:$requestId;ts:$time;", $secret);
            $body = json_encode([
                'type' => 'subscription_authorized_payment',
                'action' => 'updated',
                'data' => ['id' => $authorizedId],
            ], JSON_THROW_ON_ERROR);

            $curl = curl_init($appUrl . '/pages/webhook_mercadopago.php');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 50,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Request-Id: ' . $requestId,
                    'X-Signature: ts=' . $time . ',v1=' . $signature,
                ],
            ]);
            $reply = json_decode((string) curl_exec($curl), true);
            $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($http !== 200 || ($reply['ok'] ?? false) !== true) {
                throw new RuntimeException("Falha ao processar a cobrança no webhook local (HTTP $http).");
            }

            $invoice = $db->prepare("SELECT id FROM faturas_assinatura WHERE transacao_id=? AND status='pago' LIMIT 1");
            $invoice->bind_param('s', $paymentId);
            $invoice->execute();
            $saved = $invoice->get_result()->fetch_assoc();
            $invoice->close();
            if (!$saved) {
                throw new RuntimeException('O webhook respondeu, mas a fatura paga não foi encontrada.');
            }
            $processed++;
        }
        $offset += count($entries);
    } while ($entries && $offset < (int) ($batch['paging']['total'] ?? 0));

    echo $processed > 0
        ? "$processed pagamento(s) aprovado(s) registrado(s) no histórico.\n"
        : "A API ainda não retornou cobranças aprovadas para esta assinatura.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Reconciliação interrompida: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
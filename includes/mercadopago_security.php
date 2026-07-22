<?php

declare(strict_types=1);

function mp_mode(): string
{
    return strtolower((string) ($_ENV['MERCADOPAGO_MODE'] ?? 'sandbox')) === 'producao' ? 'producao' : 'sandbox';
}

function mp_access_token(): string
{
    $key = mp_mode() === 'producao' ? 'MP_ACCESS_TOKEN_PRODUCAO' : 'MP_ACCESS_TOKEN_SANDBOX';
    return trim((string) ($_ENV[$key] ?? ''));
}

function mp_back_url(): string
{
    $key = mp_mode() === 'producao' ? 'MP_BACK_URL_PRODUCAO' : 'MP_BACK_URL_SANDBOX';
    return trim((string) ($_ENV[$key] ?? ''));
}

function mp_webhook_secret(): string
{
    $specificKey = mp_mode() === 'producao' ? 'MP_WEBHOOK_SECRET_PRODUCAO' : 'MP_WEBHOOK_SECRET_SANDBOX';
    return trim((string) ($_ENV[$specificKey] ?? $_ENV['MP_WEBHOOK_SECRET'] ?? ''));
}

function mp_api_request(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
{
    $token = mp_access_token();
    if ($token === '') {
        throw new RuntimeException('Credencial do Mercado Pago não configurada.');
    }

    $headers = ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token];
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'X-Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init('https://api.mercadopago.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $curlError !== '') {
        throw new RuntimeException('Falha de comunicação com o Mercado Pago.');
    }
    $body = json_decode((string) $raw, true);
    if (!is_array($body)) $body = [];
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = (string) ($body['message'] ?? $body['error'] ?? 'Resposta recusada pelo Mercado Pago.');
        throw new RuntimeException($message, $httpCode);
    }
    return ['http_code' => $httpCode, 'body' => $body];
}

function mp_validate_webhook_signature(string $signature, string $requestId, string $dataId, string $secret): bool
{
    if ($signature === '' || $requestId === '' || $dataId === '' || $secret === '') return false;
    $parts = [];
    foreach (explode(',', $signature) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key !== '' && $value !== '') $parts[$key] = $value;
    }
    if (empty($parts['ts']) || empty($parts['v1'])) return false;

    $normalizedId = preg_match('/[a-z]/i', $dataId) ? strtolower($dataId) : $dataId;
    $manifest = 'id:' . $normalizedId . ';request-id:' . $requestId . ';ts:' . $parts['ts'] . ';';
    $expected = hash_hmac('sha256', $manifest, $secret);
    return hash_equals($expected, strtolower($parts['v1']));
}

function mp_checkout_by_reference(mysqli $conn, string $externalReference): ?array
{
    if ($externalReference === '') return null;
    $stmt = $conn->prepare('SELECT * FROM checkout_assinaturas WHERE checkout_token=? LIMIT 1');
    $stmt->bind_param('s', $externalReference);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}


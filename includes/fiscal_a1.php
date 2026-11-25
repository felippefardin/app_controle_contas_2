<?php
require_once __DIR__ . '/private_storage.php';
require_once __DIR__ . '/../vendor/autoload.php';

use NFePHP\Common\Certificate;

function fiscal_a1_path(string $cnpj): string
{
    $tenant = (string) ($_SESSION['tenant_id'] ?? '');
    if ($tenant === '' || !preg_match('/^\d{14}$/D', $cnpj)) {
        throw new RuntimeException('Identificação fiscal inválida.');
    }
    return private_storage_path('certificados-a1/' . hash('sha256', $tenant . ':' . $cnpj));
}

function fiscal_a1_key(): string
{
    $key = @file_get_contents('/etc/appcontrolepro-fiscal-a1.key');
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('Armazenamento de certificados indisponível.');
    }
    return $key;
}

function fiscal_a1_upload(string $cnpj, array $upload, string $password): void
{
    $tmp = (string) ($upload['tmp_name'] ?? '');
    if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)
        || ($upload['size'] ?? 0) < 1 || $upload['size'] > 1048576 || strlen($password) > 1024) {
        throw new RuntimeException('Selecione um certificado A1 válido de até 1 MB.');
    }
    $pfx = file_get_contents($tmp);
    try {
        $certificate = Certificate::readPfx($pfx, $password);
    } catch (Throwable $e) {
        throw new RuntimeException('Não foi possível abrir o certificado A1. Confira o arquivo e a senha.');
    }
    if (preg_replace('/\D/', '', (string) $certificate->getCnpj()) !== $cnpj
        || $certificate->isExpired() || $certificate->getValidFrom() > new DateTimeImmutable()) {
        throw new RuntimeException('O certificado deve estar vigente e pertencer ao CNPJ emitente.');
    }
    $destination = fiscal_a1_path($cnpj);
    $directory = ensure_private_storage_directory('certificados-a1');
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $payload = json_encode([basename($destination), base64_encode($pfx), base64_encode($password)], JSON_THROW_ON_ERROR);
    $encoded = $nonce . sodium_crypto_secretbox($payload, $nonce, fiscal_a1_key());
    $pending = tempnam($directory, '.a1-');
    if ($pending === false) {
        throw new RuntimeException('Não foi possível preparar o certificado.');
    }
    try {
        if (!chmod($pending, 0600) || file_put_contents($pending, $encoded) !== strlen($encoded)
            || !rename($pending, $destination)) {
            throw new RuntimeException('Não foi possível armazenar o certificado.');
        }
    } finally {
        if (is_file($pending)) unlink($pending);
    }
}

function fiscal_a1_load(array $company): Certificate
{
    $path = fiscal_a1_path(preg_replace('/\D/', '', (string) ($company['cnpj'] ?? '')));
    if (!is_file($path)) {
        $legacy = __DIR__ . '/../' . ($company['certificado_a1_path'] ?? '');
        if (!is_file($legacy)) throw new RuntimeException('Cadastre o certificado A1 do emitente.');
        return Certificate::readPfx(file_get_contents($legacy), $company['certificado_senha']);
    }
    $encoded = file_get_contents($path);
    $size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
    $payload = sodium_crypto_secretbox_open(substr($encoded, $size), substr($encoded, 0, $size), fiscal_a1_key());
    if ($payload === false) throw new RuntimeException('Certificado armazenado inválido.');
    [$id, $pfx, $password] = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    if ($id !== basename($path)) throw new RuntimeException('Certificado incompatível com o emitente.');
    return Certificate::readPfx(base64_decode($pfx, true), base64_decode($password, true));
}

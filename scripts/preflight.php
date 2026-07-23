<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("Esta verificação deve ser executada pelo terminal.\n");

define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/environment.php';
require_once __DIR__ . '/../includes/migration_runner.php';

$jsonOutput = in_array('--json', $argv, true);
$targetArg = 'test';
foreach ($argv as $arg) if (str_starts_with($arg, '--target=')) $targetArg = substr($arg, 9);
$target = strtolower($targetArg);
if (!in_array($target, ['test', 'production'], true)) {
    fwrite(STDERR, "Use --target=test ou --target=production.\n"); exit(2);
}

$checks = [];
$add = static function (string $level, string $name, string $message) use (&$checks): void {
    $checks[] = compact('level', 'name', 'message');
};
$ok = static fn(string $name, string $message) => $add('ok', $name, $message);
$warn = static fn(string $name, string $message) => $add('warning', $name, $message);
$error = static fn(string $name, string $message) => $add('error', $name, $message);

$environment = app_environment();
if ($target === 'test') {
    app_is_production() ? $error('Ambiente', 'O .env está marcado como produção; os testes devem permanecer fora de produção.') : $ok('Ambiente', "Modo {$environment} mantido. Nenhuma publicação será realizada.");
} else {
    app_is_production() ? $ok('Ambiente', 'APP_ENV está em produção.') : $error('Ambiente', 'APP_ENV deve ser production somente no momento da publicação.');
}

version_compare(PHP_VERSION, '8.2.0', '>=') ? $ok('PHP', PHP_VERSION) : $error('PHP', 'É necessário PHP 8.2 ou superior.');
foreach (['mysqli', 'curl', 'mbstring', 'openssl', 'json', 'fileinfo'] as $extension) {
    extension_loaded($extension) ? $ok('Extensão ' . $extension, 'Carregada.') : $error('Extensão ' . $extension, 'Não está instalada ou habilitada.');
}
is_readable(__DIR__ . '/../vendor/autoload.php') ? $ok('Dependências', 'vendor/autoload.php encontrado.') : $error('Dependências', 'Execute composer install.');

$appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
if ($target === 'production') {
    str_starts_with($appUrl, 'https://') ? $ok('URL HTTPS', $appUrl) : $error('URL HTTPS', 'APP_URL precisa utilizar HTTPS.');
    env_bool('APP_DEBUG', false) ? $error('Debug', 'APP_DEBUG deve estar false em produção.') : $ok('Debug', 'Desativado.');
} else {
    $appUrl !== '' ? $ok('URL local', $appUrl) : $warn('URL local', 'APP_URL não configurada.');
}

$privateStorage = trim((string) ($_ENV['PRIVATE_STORAGE_PATH'] ?? ''));
if ($privateStorage === '') {
    $error('Armazenamento privado', 'PRIVATE_STORAGE_PATH não configurado.');
} elseif (!is_dir($privateStorage)) {
    $warn('Armazenamento privado', 'A pasta ainda não existe: ' . $privateStorage);
} elseif (!is_writable($privateStorage)) {
    $error('Armazenamento privado', 'Sem permissão de escrita: ' . $privateStorage);
} else {
    $ok('Armazenamento privado', 'Pasta existente e gravável.');
}

try {
    $master = getMasterConnection();
    $ok('Banco principal', (string) ($master->query('SELECT DATABASE()')->fetch_row()[0] ?? 'conectado'));
    $masterStatus = run_migrations_for_connection($master, 'master', true, true);
    empty($masterStatus['pending']) ? $ok('Migrações master', 'Atualizadas.') : $error('Migrações master', implode(', ', $masterStatus['pending']));
    $tenants = $master->query('SELECT tenant_id, db_host, db_database, db_user, db_password FROM tenants')->fetch_all(MYSQLI_ASSOC);
    foreach ($tenants as $tenant) {
        try {
            $tenantDb = new mysqli($tenant['db_host'], $tenant['db_user'], $tenant['db_password'], $tenant['db_database']);
            $tenantDb->set_charset('utf8mb4');
            $status = run_migrations_for_connection($tenantDb, 'tenant', true, true);
            empty($status['pending']) ? $ok('Tenant ' . $tenant['db_database'], 'Conectado e atualizado.') : $error('Tenant ' . $tenant['db_database'], 'Migrações pendentes.');
            $tenantDb->close();
        } catch (Throwable $e) {
            $error('Tenant ' . $tenant['db_database'], 'Falha de conexão ou migração: ' . $e->getMessage());
        }
    }
    $master->close();
} catch (Throwable $e) {
    $error('Banco principal', $e->getMessage());
}

$mpMode = strtolower((string) ($_ENV['MERCADOPAGO_MODE'] ?? 'sandbox'));
if ($target === 'test') {
    $mpMode === 'sandbox' ? $ok('Mercado Pago', 'Sandbox ativo.') : $error('Mercado Pago', 'Use sandbox enquanto estiver testando.');
} else {
    $mpMode === 'producao' ? $ok('Mercado Pago', 'Modo produção selecionado.') : $error('Mercado Pago', 'Ainda está em sandbox.');
}
$mpTokenKey = $target === 'production' ? 'MP_ACCESS_TOKEN_PRODUCAO' : 'MP_ACCESS_TOKEN_SANDBOX';
empty($_ENV[$mpTokenKey]) ? $warn('Token Mercado Pago', $mpTokenKey . ' ainda não preenchido.') : $ok('Token Mercado Pago', 'Configurado.');
$webhookKey = $target === 'production' ? 'MP_WEBHOOK_SECRET_PRODUCAO' : 'MP_WEBHOOK_SECRET_SANDBOX';
empty($_ENV[$webhookKey]) && empty($_ENV['MP_WEBHOOK_SECRET']) ? $warn('Webhook Mercado Pago', 'Assinatura secreta ainda não configurada.') : $ok('Webhook Mercado Pago', 'Assinatura secreta configurada.');
env_bool('MP_ALLOW_UNSIGNED_SANDBOX', false) ? $error('Webhook sem assinatura', 'MP_ALLOW_UNSIGNED_SANDBOX deve permanecer false.') : $ok('Webhook sem assinatura', 'Bloqueado.');

env_bool('FISCAL_ALLOW_PRODUCTION', false)
    ? ($target === 'production' ? $warn('Emissão fiscal', 'Produção fiscal habilitada; valide certificado e credenciamento.') : $error('Emissão fiscal', 'Desative FISCAL_ALLOW_PRODUCTION durante os testes.'))
    : $ok('Emissão fiscal', 'Produção fiscal desativada.');

empty($_ENV['MAIL_HOST']) || empty($_ENV['MAIL_USERNAME'])
    ? $warn('E-mail', 'SMTP incompleto; envios reais continuarão indisponíveis.')
    : $ok('E-mail', 'SMTP configurado.');

if ($appUrl !== '' && str_starts_with($appUrl, 'http')) {
    foreach (['/.env', '/composer.json', '/schema.sql'] as $sensitivePath) {
        $url = $appUrl . $sensitivePath;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 2]);
        curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if (in_array($code, [401, 403, 404], true)) $ok('Proteção HTTP ' . $sensitivePath, 'Bloqueado (HTTP ' . $code . ').');
        elseif ($code === 0) $warn('Proteção HTTP ' . $sensitivePath, 'Servidor local indisponível para conferir.');
        else $error('Proteção HTTP ' . $sensitivePath, 'Arquivo possivelmente exposto (HTTP ' . $code . ').');
    }
}

$errors = count(array_filter($checks, static fn(array $check): bool => $check['level'] === 'error'));
$warnings = count(array_filter($checks, static fn(array $check): bool => $check['level'] === 'warning'));
if ($jsonOutput) {
    echo json_encode(['target' => $target, 'environment' => $environment, 'errors' => $errors, 'warnings' => $warnings, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} else {
    echo "Verificação de ambiente — alvo: {$target}\n\n";
    $symbols = ['ok' => '[OK]', 'warning' => '[AVISO]', 'error' => '[ERRO]'];
    foreach ($checks as $check) echo $symbols[$check['level']] . " {$check['name']}: {$check['message']}\n";
    echo "\nResultado: {$errors} erro(s), {$warnings} aviso(s).\n";
    if ($target === 'test' && $errors === 0) echo "O ambiente pode continuar em testes. Nenhuma publicação foi realizada.\n";
}
exit($errors === 0 ? 0 : 1);

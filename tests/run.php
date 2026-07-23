<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("Os testes só podem ser executados pelo terminal.\n");
require_once __DIR__ . '/bootstrap.php';

$sessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app_controle_test_sessions';
if (!is_dir($sessionDir)) mkdir($sessionDir, 0777, true);
session_save_path($sessionDir);
session_id('codex-tests-' . bin2hex(random_bytes(4)));
require_once __DIR__ . '/../includes/session_init.php';

define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/migration_runner.php';

$environment = strtolower((string) ($_ENV['APP_ENV'] ?? 'local'));
if (in_array($environment, ['production', 'producao'], true)) {
    fwrite(STDERR, "Testes bloqueados em ambiente de produção.\n"); exit(2);
}

foreach (glob(__DIR__ . '/unit/*_test.php') ?: [] as $file) require $file;
foreach (glob(__DIR__ . '/integration/*_test.php') ?: [] as $file) require $file;
$requestedGroup = strtolower((string) ($argv[1] ?? 'all'));
if (!in_array($requestedGroup, ['all', 'unit', 'integration'], true)) {
    fwrite(STDERR, "Use: php tests/run.php [all|unit|integration]\n"); exit(2);
}

$passed = 0; $failed = 0; $started = microtime(true);
foreach ($GLOBALS['codex_tests'] ?? [] as $test) {
    if ($requestedGroup !== 'all' && $test['group'] !== $requestedGroup) continue;
    try {
        ($test['test'])(); $passed++; echo "[OK] {$test['group']} - {$test['name']}\n";
    } catch (Throwable $e) {
        $failed++; echo "[FALHOU] {$test['group']} - {$test['name']}\n  {$e->getMessage()}\n";
    }
}
$elapsed = number_format(microtime(true) - $started, 2, ',', '.');
echo "\nResultado: {$passed} aprovado(s), {$failed} falha(s), {$elapsed}s.\n";
exit($failed === 0 ? 0 : 1);


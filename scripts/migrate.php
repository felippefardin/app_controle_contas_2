<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("Este comando só pode ser executado pelo terminal.\n");
define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/migration_runner.php';

$options = getopt('', ['scope::', 'tenant::', 'status', 'dry-run']);
$scope = strtolower((string) ($options['scope'] ?? 'all'));
if (!in_array($scope, ['master', 'tenants', 'all'], true)) {
    fwrite(STDERR, "Use --scope=master, --scope=tenants ou --scope=all.\n"); exit(2);
}
$tenantFilter = trim((string) ($options['tenant'] ?? ''));
$statusOnly = array_key_exists('status', $options);
$dryRun = array_key_exists('dry-run', $options);
$master = getMasterConnection();
$failed = false;
$printResult = static function (array $result) use ($statusOnly, $dryRun): void {
    echo "[{$result['scope']}] {$result['database']}\n";
    foreach ($result['applied'] as $id) echo "  APLICADA  {$id}\n";
    foreach ($result['pending'] as $id) if (!in_array($id, $result['applied'], true)) {
        echo '  ' . (($statusOnly || $dryRun) ? 'PENDENTE ' : 'IGNORADA ') . " {$id}\n";
    }
    if (!$result['applied'] && !$result['pending']) echo "  ATUALIZADO\n";
};
try {
    if (in_array($scope, ['master', 'all'], true)) $printResult(run_migrations_for_connection($master, 'master', $dryRun, $statusOnly));
    if (in_array($scope, ['tenants', 'all'], true)) {
        $sql = 'SELECT tenant_id, db_host, db_database, db_user, db_password FROM tenants';
        if ($tenantFilter !== '') $sql .= ' WHERE tenant_id=? OR db_database=?';
        $stmt = $master->prepare($sql . ' ORDER BY id');
        if ($tenantFilter !== '') $stmt->bind_param('ss', $tenantFilter, $tenantFilter);
        $stmt->execute(); $tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        if ($tenantFilter !== '' && !$tenants) throw new RuntimeException('Tenant não encontrado.');
        foreach ($tenants as $tenant) {
            try {
                $db = new mysqli($tenant['db_host'], $tenant['db_user'], $tenant['db_password'], $tenant['db_database']);
                $db->set_charset('utf8mb4');
                $printResult(run_migrations_for_connection($db, 'tenant', $dryRun, $statusOnly));
                $db->close();
            } catch (Throwable $e) {
                $failed = true; fwrite(STDERR, "[ERRO] {$tenant['tenant_id']}/{$tenant['db_database']}: {$e->getMessage()}\n");
            }
        }
    }
} catch (Throwable $e) {
    $failed = true; fwrite(STDERR, '[ERRO] ' . $e->getMessage() . "\n");
}
$master->close(); exit($failed ? 1 : 0);


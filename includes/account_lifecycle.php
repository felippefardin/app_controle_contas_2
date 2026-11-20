<?php
declare(strict_types=1);

function account_pause_status(): string { return 'desativado_temporario'; }

function account_is_temporarily_disabled(array $tenant): bool
{
    return (string) ($tenant['status_assinatura'] ?? '') === account_pause_status()
        && !empty($tenant['exclusao_programada_em'])
        && strtotime((string) $tenant['exclusao_programada_em']) > time();
}

function account_set_tenant_users_status(array $tenant, string $status): void
{
    $db = new mysqli((string)$tenant['db_host'], (string)$tenant['db_user'], (string)$tenant['db_password'], (string)$tenant['db_database']);
    $db->set_charset('utf8mb4');
    $stmt = $db->prepare('UPDATE usuarios SET status=? WHERE deleted_at IS NULL');
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

function account_delete_tenant_database(array $tenant): void
{
    $database = (string) ($tenant['db_database'] ?? '');
    if ($database === '' || !preg_match('/^[A-Za-z0-9_]+$/', $database)) throw new RuntimeException('Banco do tenant inválido para exclusão.');
    $admin = new mysqli((string)($_ENV['DB_HOST'] ?? 'localhost'), (string)($_ENV['DB_ADMIN_USER'] ?? $_ENV['DB_USER'] ?? ''), (string)($_ENV['DB_ADMIN_PASS'] ?? $_ENV['DB_PASSWORD'] ?? ''));
    $admin->set_charset('utf8mb4');
    $admin->query('DROP DATABASE IF EXISTS `' . $database . '`');
    $admin->close();
}

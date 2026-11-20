<?php

declare(strict_types=1);

final class TestFailure extends RuntimeException {}

function test_assert(bool $condition, string $message = 'Falha na asserção'): void
{
    if (!$condition) throw new TestFailure($message);
}

function test_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(($message !== '' ? $message . ': ' : '') . 'esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true));
    }
}

function test_register(string $group, string $name, Closure $test): void
{
    $GLOBALS['codex_tests'][] = compact('group', 'name', 'test');
}

function test_tenant_connection(mysqli $master): mysqli
{
    $tenant = $master->query('SELECT db_host, db_database, db_user, db_password FROM tenants ORDER BY id LIMIT 1')->fetch_assoc();
    if (!$tenant) throw new RuntimeException('Nenhum tenant disponível para os testes de integração.');
    $db = new mysqli($tenant['db_host'], $tenant['db_user'], $tenant['db_password'], $tenant['db_database']);
    $db->set_charset('utf8mb4');
    return $db;
}


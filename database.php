<?php
// database.php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

use Dotenv\Dotenv;

/*
|--------------------------------------------------------------------------
| Carrega o .env
|--------------------------------------------------------------------------
*/

$dotenvPath = __DIR__;

if (!file_exists($dotenvPath . '/.env')) {
    $dotenvPath = dirname(__DIR__);
}

if (!file_exists($dotenvPath . '/.env')) {
    die("Arquivo .env não encontrado.");
}

$dotenv = Dotenv::createImmutable($dotenvPath);
$dotenv->safeLoad();

/*
|--------------------------------------------------------------------------
| Configuração Banco Master
|--------------------------------------------------------------------------
*/

$db_host_master = $_ENV['DB_HOST'] ?? 'localhost';
$db_user_master = $_ENV['DB_USER'] ?? 'root';
$db_pass_master = $_ENV['DB_PASSWORD'] ?? '';
$db_name_master = $_ENV['DB_DATABASE'] ?? 'app_controle_contas';

/*
|--------------------------------------------------------------------------
| Conexão Master
|--------------------------------------------------------------------------
*/

function getMasterConnection(): mysqli
{
    global $db_host_master;
    global $db_user_master;
    global $db_pass_master;
    global $db_name_master;

    try {

        $conn = new mysqli(
            $db_host_master,
            $db_user_master,
            $db_pass_master,
            $db_name_master
        );

        $conn->set_charset('utf8mb4');

        return $conn;

    } catch (mysqli_sql_exception $e) {

        error_log($e->getMessage());

        die(
            "<h2>Erro ao conectar no banco MASTER</h2>
            <b>Mensagem:</b> {$e->getMessage()}<br><br>
            <b>Host:</b> {$db_host_master}<br>
            <b>Banco:</b> {$db_name_master}<br>
            <b>Usuário:</b> {$db_user_master}"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Conexão Tenant
|--------------------------------------------------------------------------
*/

function getTenantConnection(): ?mysqli
{
    if (!isset($_SESSION['tenant_db'])) {
        return null;
    }

    $db = $_SESSION['tenant_db'];

    try {

        $conn = new mysqli(
            $db['db_host'],
            $db['db_user'],
            $db['db_password'],
            $db['db_database']
        );

        $conn->set_charset('utf8mb4');

        return $conn;

    } catch (mysqli_sql_exception $e) {

        die(
            "<h2>Erro ao conectar no banco do Tenant</h2>
            <b>Mensagem:</b> {$e->getMessage()}<br><br>
            <b>Banco:</b> {$db['db_database']}<br>
            <b>Host:</b> {$db['db_host']}<br>
            <b>Usuário:</b> {$db['db_user']}"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Buscar Tenant
|--------------------------------------------------------------------------
*/

function getTenantById(string $tenantId, mysqli $conn): ?array
{
    $stmt = $conn->prepare("
        SELECT *
        FROM tenants
        WHERE tenant_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $tenantId);
    $stmt->execute();

    $resultado = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $resultado ?: null;
}
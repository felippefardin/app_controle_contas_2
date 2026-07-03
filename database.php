<?php
// database.php
require_once __DIR__ . '/vendor/autoload.php';
// session_init já deve iniciar a sessão, mas vamos garantir aqui
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

use Dotenv\Dotenv;

$dotenvPath = realpath(__DIR__ . '/');
if (!file_exists($dotenvPath . '/.env')) {
    $dotenvPath = realpath(__DIR__ . '/../');
}
$dotenv = Dotenv::createImmutable($dotenvPath);
$dotenv->safeLoad();

$db_host_master = $_ENV['DB_HOST'] ?? 'localhost';
$db_user_master = $_ENV['DB_USER'] ?? 'root';
$db_pass_master = $_ENV['DB_PASSWORD'] ?? '';
$db_name_master = $_ENV['DB_DATABASE'] ?? 'app_controle_contas';

function getMasterConnection() {
    global $db_host_master, $db_user_master, $db_pass_master, $db_name_master;
    $conn = new mysqli($db_host_master, $db_user_master, $db_pass_master, $db_name_master);
    if ($conn->connect_error) {
        error_log("Erro MASTER: " . $conn->connect_error);
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function getTenantConnection() {
    if (!isset($_SESSION['tenant_db'])) return null;
    $db = $_SESSION['tenant_db'];
    $conn = new mysqli($db['db_host'], $db['db_user'], $db['db_password'], $db['db_database']);
    if ($conn->connect_error) {
    die(
        "Erro ao conectar no banco do tenant:<br><br>" .
        $conn->connect_error .
        "<br><br>Banco: " . $db['db_database'] .
        "<br>Host: " . $db['db_host'] .
        "<br>Usuário: " . $db['db_user']
    );
}
    $conn->set_charset("utf8mb4");
    return $conn;
}

function getTenantById($tenantId, $conn)
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

    return $resultado;
}
?>
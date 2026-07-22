<?php 
// pages/registro_processa.php

// 1. Inicia o buffer de saída para evitar vazamento de avisos/HTML antes do JSON
ob_start(); 

require_once __DIR__ . '/../includes/session_init.php';

// Captura erros de inclusão de banco de dados de forma segura para o AJAX
try {
    require_once __DIR__ . '/../database.php'; 
} catch (Exception $e) {
    if (isset($_POST['ajax_check']) && $_POST['ajax_check'] === '1') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => 'Erro ao carregar banco de dados.']);
        exit;
    }
}

require_once __DIR__ . '/../includes/utils.php';

use Dotenv\Dotenv;
use Dompdf\Dompdf;

define('TURNSTILE_SECRET_KEY', '0x4AAAAAACDq-BZPjGJTF8DhHDOEQ6NrWBw');

// Inicializa a conexão com o banco master
$conn = getMasterConnection();

// --- VERIFICAÇÃO AJAX DE DUPLICIDADE ---
if (isset($_POST['ajax_check']) && $_POST['ajax_check'] === '1') {
    // Garante que o buffer de saída seja limpo de qualquer warning inesperado
    ob_clean(); 
    header('Content-Type: application/json');
    
    $email_check = trim($_POST['email'] ?? '');
    $doc_check = trim($_POST['documento'] ?? '');
    
    $email_exists = false;
    $doc_exists = false;

    if ($conn) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email_check);
            $stmt->execute();
            $email_exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE documento = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $doc_check);
            $stmt->execute();
            $doc_exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }
    }

    echo json_encode(['email_exists' => $email_exists, 'doc_exists' => $doc_exists]);
    exit;
}

// --- PROCESSAMENTO DO CADASTRO (VIA FORM POST NORMAL) ---
function verificarTurnstile($token) {
    if (!$token) return false;
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $data = ['secret' => TURNSTILE_SECRET_KEY, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR']];
    $options = ['http' => ['header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data)]];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    $response = json_decode($result);
    return $response->success ?? false;
}

$nome         = trim($_POST['nome'] ?? '');
$email        = trim($_POST['email'] ?? '');
$senha        = trim($_POST['senha'] ?? '');
$tipo_pessoa  = trim($_POST['tipo_pessoa'] ?? 'fisica');
$documento    = trim($_POST['documento'] ?? '');
$telefone     = trim($_POST['telefone'] ?? '');
$plano_post   = trim($_POST['plano'] ?? 'basico');
$aceite_lgpd  = $_POST['aceite_lgpd'] ?? '0';
$turnstileToken = $_POST['cf-turnstile-response'] ?? ''; 
$cupom_codigo = isset($_POST['cupom']) && !empty($_POST['cupom']) ? strtoupper(trim($_POST['cupom'])) : null;
$codigo_indicacao_recebido = isset($_POST['codigo_indicacao']) && !empty($_POST['codigo_indicacao']) ? strtoupper(trim($_POST['codigo_indicacao'])) : null;

$form_data = [
    'nome' => $nome, 'email' => $email, 'tipo_pessoa' => $tipo_pessoa, 
    'documento' => $documento, 'telefone' => $telefone, 'plano' => $plano_post,
    'cupom' => $_POST['cupom'] ?? '', 'codigo_indicacao' => $_POST['codigo_indicacao'] ?? ''
];

function return_error($msg, $data) {
    global $conn;
    if(isset($conn) && $conn) $conn->close();
    $_SESSION['form_data'] = $data; 
    set_flash_message('danger', $msg);
    header("Location: ../pages/registro.php");
    exit;
}

// if (!verificarTurnstile($turnstileToken)) return_error("Verificação de segurança falhou (Captcha).", $form_data);
if ($aceite_lgpd !== '1') return_error("É obrigatório aceitar os Termos LGPD.", $form_data);

$plano_escolhido = ($plano_post === 'essencial') ? 'essencial' : (($plano_post === 'plus') ? 'plus' : 'basico');
$dias_teste = ($plano_post === 'essencial') ? 30 : 15;

if (!$nome || !$email || !$senha || !$documento) return_error("Preencha todos os campos.", $form_data);

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);
$conn->begin_transaction();

try {
    $stmtUser = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo_pessoa, documento, telefone, nivel_acesso, perfil, tipo, status, is_master) VALUES (?, ?, ?, ?, ?, ?, 'proprietario', 'admin', 'admin', 'ativo', 1)");
    $stmtUser->bind_param("ssssss", $nome, $email, $senha_hash, $tipo_pessoa, $documento, $telefone);
    $stmtUser->execute();
    $new_usuario_id = $conn->insert_id;
    $stmtUser->close();
    $conn->query("UPDATE usuarios SET codigo_indicacao = CONCAT('IND', LPAD(id, 6, '0')) WHERE id = $new_usuario_id");

    $tenantId = 'T' . substr(md5(uniqid($email, true)), 0, 32);
    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
    $dbDatabase = 'tenant_db_' . $new_usuario_id;
    $dbUser = 'dbuser_' . $new_usuario_id;
    $dbPassword = bin2hex(random_bytes(16));

    $stmtTenant = $conn->prepare("INSERT INTO tenants (tenant_id, usuario_id, nome, nome_empresa, admin_email, senha, status_assinatura, data_inicio_teste, plano_atual, db_host, db_database, db_user, db_password, cupom_registro) VALUES (?, ?, ?, ?, ?, ?, 'trial', NOW(), ?, ?, ?, ?, ?, ?)");
    $stmtTenant->bind_param("sissssssssss", $tenantId, $new_usuario_id, $nome, $nome, $email, $senha_hash, $plano_escolhido, $dbHost, $dbDatabase, $dbUser, $dbPassword, $cupom_codigo);
    $stmtTenant->execute();
    $new_tenant_id = $conn->insert_id;
    $stmtTenant->close();

    $conn->query("UPDATE usuarios SET tenant_id = '$tenantId' WHERE id = $new_usuario_id");

    $rootConn = new mysqli($dbHost, $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    $rootConn->query("CREATE DATABASE IF NOT EXISTS `$dbDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $rootConn->query("CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$dbPassword'");
    $rootConn->query("GRANT ALL PRIVILEGES ON `$dbDatabase`.* TO '$dbUser'@'localhost'");
    $rootConn->query("FLUSH PRIVILEGES");
    $rootConn->close();

    // Todo tenant precisa receber sua estrutura antes do primeiro login.
    $tenantSchemaPath = __DIR__ . '/../includes/tenant_schema._sql';
    if (!is_readable($tenantSchemaPath)) {
        throw new RuntimeException('Arquivo de estrutura do tenant não encontrado.');
    }
    $tenantSchema = file_get_contents($tenantSchemaPath);
    $tenantConn = new mysqli($dbHost, $dbUser, $dbPassword, $dbDatabase);
    $tenantConn->set_charset('utf8mb4');
    if (!$tenantConn->multi_query($tenantSchema)) {
        throw new RuntimeException('Não foi possível criar as tabelas do tenant: ' . $tenantConn->error);
    }
    while ($tenantConn->more_results() && $tenantConn->next_result()) {
        // Consome os resultados das instruções do arquivo SQL.
    }
    $tenantConn->query("DELETE FROM usuarios WHERE email = 'admin@cliente.com'");
    $stmtTenantUser = $tenantConn->prepare(
        "INSERT INTO usuarios (nome, tipo_pessoa, documento, telefone, email, senha, perfil, tipo, nivel_acesso, status, is_master, tenant_id)
         VALUES (?, ?, ?, ?, ?, ?, 'admin', 'admin', 'proprietario', 'ativo', 1, ?)"
    );
    $stmtTenantUser->bind_param("ssssssi", $nome, $tipo_pessoa, $documento, $telefone, $email, $senha_hash, $new_tenant_id);
    $stmtTenantUser->execute();
    $stmtTenantUser->close();
    $tenantConn->close();

    $conn->commit();
    unset($_SESSION['form_data']);
    set_flash_message('success', "Cadastro realizado com sucesso!");
    header("Location: ../pages/login.php");
    exit;
} catch (Exception $e) {
    if(isset($conn) && $conn) $conn->rollback();
    // Isso vai mostrar na tela o que está acontecendo de verdade
    die("ERRO DETALHADO: " . $e->getMessage()); 
}
?>

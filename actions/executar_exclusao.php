<?php
// actions/executar_exclusao.php

// Carrega as configurações e a conexão
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/account_deletion_consent.php';

// Configura o PHP para reportar erros do MySQLi como exceções
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1️⃣ Conexão principal (Master)
$conn = getMasterConnection();
if ($conn === null) {
    die("Falha ao conectar ao banco de dados principal.");
}

$token = $_POST['token'] ?? '';
$id_usuario = null;
$tenant_id_string = null; 
$db_name = null;
$db_user = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo nao permitido.');
}
require_csrf();
if ((string)($_POST['aceite_exclusao'] ?? '') !== '1') {
    set_flash_message('danger', 'Confirme que leu e compreendeu a exclusao permanente dos dados.');
    header('Location: ../pages/login.php');
    exit;
}

if (empty($token)) {
    header("Location: ../pages/login.php?erro=token_invalido");
    exit;
}

try {
    // 2️⃣ Valida o token e busca o ID do usuário
    $stmt = $conn->prepare("SELECT id_usuario FROM solicitacoes_exclusao WHERE token = ? AND expira_em > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($id_usuario);
    if (!$stmt->fetch()) {
        $stmt->close();
        throw new Exception("Token inválido ou expirado.");
    }
    $stmt->close();

    // 3️⃣ Busca o ID do tenant (String) na tabela de usuários
    $stmt_tenant = $conn->prepare("SELECT tenant_id, nome, email, documento FROM usuarios WHERE id = ?");
    $stmt_tenant->bind_param("i", $id_usuario);
    $stmt_tenant->execute();
    $stmt_tenant->bind_result($tenant_id_string, $nome_snapshot, $email_snapshot, $documento_snapshot);
    
    if (!$stmt_tenant->fetch() || $tenant_id_string === null) {
        $stmt_tenant->close();
        throw new Exception("Não foi possível encontrar o tenant_id para este usuário.");
    }
    $stmt_tenant->close();

    // Guarda uma prova independente da conta antes de apagar os dados. Nao ha
    // chave estrangeira para o usuario, pois o registro deve sobreviver a exclusao.
    $protocolo = 'EXC-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $versaoTermo = account_deletion_consent_version();
    $textoAceito = account_deletion_consent_text();
    $textoHash = account_deletion_consent_hash();
    $documentoHash = trim((string)$documento_snapshot) !== '' ? hash('sha256', preg_replace('/\D+/', '', (string)$documento_snapshot)) : null;
    $ipUsuario = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
    $userAgentHash = !empty($_SERVER['HTTP_USER_AGENT']) ? hash('sha256', (string)$_SERVER['HTTP_USER_AGENT']) : null;
    $excluirApos = date('Y-m-d H:i:s', strtotime('+5 years'));
    $aceite = $conn->prepare('INSERT INTO aceites_exclusao_conta
        (protocolo, usuario_id_original, tenant_id_snapshot, nome_snapshot, email_snapshot, documento_hash,
         versao_termo, texto_aceito, texto_hash, ip_usuario, user_agent_hash, data_aceite, excluir_apos)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
    $aceite->bind_param('sissssssssss', $protocolo, $id_usuario, $tenant_id_string, $nome_snapshot, $email_snapshot, $documentoHash, $versaoTermo, $textoAceito, $textoHash, $ipUsuario, $userAgentHash, $excluirApos);
    $aceite->execute();
    $aceite->close();

    // 4️⃣ Busca as credenciais do banco de dados do tenant
    $stmt_creds = $conn->prepare("SELECT db_database, db_user FROM tenants WHERE tenant_id = ?");
    $stmt_creds->bind_param("s", $tenant_id_string);
    $stmt_creds->execute();
    $stmt_creds->bind_result($db_name, $db_user);
    
    if (!$stmt_creds->fetch()) {
        $db_name = null; // Tenant pode já não ter banco associado
    }
    $stmt_creds->close();

    // 5️⃣ Conecta-se como admin do MySQL para apagar o banco e o usuário do DB
    // CORREÇÃO: Usa $_ENV em vez de $env e define valores padrão
    if (!empty($db_name) && !empty($db_user)) {
        $db_host = $_ENV['DB_HOST'] ?? 'localhost';
        // Tenta usar usuário 'DB_ADMIN_USER' do .env, senão usa o usuário padrão 'DB_USER'
        $db_admin_user = $_ENV['DB_ADMIN_USER'] ?? $_ENV['DB_USER'] ?? 'root';
        $db_admin_pass = $_ENV['DB_ADMIN_PASS'] ?? $_ENV['DB_PASSWORD'] ?? '';

        $admin_conn = new mysqli($db_host, $db_admin_user, $db_admin_pass);
        
        if ($admin_conn->connect_error) {
            throw new Exception("Falha ao conectar para exclusão de DB: " . $admin_conn->connect_error);
        }
        
        // Apaga o banco de dados do tenant
        $admin_conn->query("DROP DATABASE IF EXISTS `{$db_name}`");
        
        // Tenta apagar o usuário do MySQL (pode falhar em alguns servidores, então usamos try/catch)
        try {
            $admin_conn->query("DROP USER IF EXISTS '{$db_user}'@'localhost'");
            $admin_conn->query("DROP USER IF EXISTS '{$db_user}'@'%'");
        } catch (Exception $e) {
            error_log("Aviso (exclusão): Não foi possível remover usuário MySQL '{$db_user}'. O banco foi apagado.");
        }
        
        $admin_conn->close();
    }

    // 6️⃣ Apaga os registros das tabelas principais (tenants e usuarios)
    $conn->begin_transaction();
    
    try {
        // Apaga da tabela 'tenants'
        $stmt_del_tenant = $conn->prepare("DELETE FROM tenants WHERE tenant_id = ?");
        $stmt_del_tenant->bind_param("s", $tenant_id_string);
        $stmt_del_tenant->execute();
        $stmt_del_tenant->close();
        
        // Apaga da tabela 'usuarios'
        $stmt_del_user = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt_del_user->bind_param("i", $id_usuario);
        $stmt_del_user->execute();
        $stmt_del_user->close();
        
        $conn->commit();
    } catch (Exception $ex_trans) {
        $conn->rollback();
        throw $ex_trans;
    }

    // 7️⃣ Finaliza a sessão e redireciona
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    
    header("Location: ../pages/login.php?sucesso=conta_excluida");
    exit;

} catch (Exception $e) {
    error_log("Falha crítica na exclusão da conta: " . $e->getMessage());
    header("Location: ../pages/login.php?erro=falha_exclusao_completa");
    exit;
} finally {
    if ($conn) $conn->close();
}
?>

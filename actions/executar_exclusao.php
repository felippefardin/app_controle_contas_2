<?php
// actions/executar_exclusao.php

// Carrega as configurações e a conexão
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/account_cycle_consent.php';
require_once '../includes/account_cycle_transition.php';

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

    account_cycle_change($conn, $tenant_id_string, false, function (mysqli $db, array $tenant) use ($tenant_id_string, $id_usuario, $nome_snapshot, $email_snapshot, $documento_snapshot, $token) {
        if (strcasecmp((string) $tenant['admin_email'], $email_snapshot) !== 0) {
            throw new DomainException('O titular da conta deve confirmar o pedido.');
        }
        $db->execute_query('DELETE FROM solicitacoes_exclusao WHERE token=? AND expira_em>NOW()', [$token]);
        if ($db->affected_rows !== 1) {
            throw new DomainException('Token inválido ou expirado.');
        }
        account_cycle_record_consent($db, $tenant_id_string, $id_usuario, $nome_snapshot, $email_snapshot, $documento_snapshot);
    });
    set_flash_message('success', 'Encerramento agendado para o fim do ciclo.');
    header('Location: ../pages/perfil.php');
    exit;
} catch (Throwable $e) {
    error_log("Falha crítica na exclusão da conta: " . $e->getMessage());
    header("Location: ../pages/login.php?erro=falha_exclusao_completa");
    exit;
} finally {
    if ($conn) $conn->close();
}
?>

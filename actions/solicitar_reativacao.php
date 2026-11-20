<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/enviar_email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
require_csrf();
$email = mb_strtolower(trim((string)($_POST['email_reativacao'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash_message('success', 'Se a conta estiver dentro do prazo, enviaremos as instruções para o e-mail informado.');
    header('Location: ../pages/login.php'); exit;
}

$attempt = $_SESSION['reactivation_request_at'] ?? 0;
if (time() - (int)$attempt < 60) {
    set_flash_message('warning', 'Aguarde um minuto antes de solicitar um novo link.');
    header('Location: ../pages/login.php'); exit;
}
$_SESSION['reactivation_request_at'] = time();

$db = getMasterConnection();
try {
    $stmt = $db->prepare("SELECT u.id, u.nome, u.email, t.nome_empresa, t.exclusao_programada_em FROM usuarios u JOIN tenants t ON t.tenant_id=u.tenant_id AND LOWER(t.admin_email)=LOWER(u.email) WHERE LOWER(u.email)=? AND t.status_assinatura='desativado_temporario' AND t.exclusao_programada_em>NOW() LIMIT 1");
    $stmt->bind_param('s', $email); $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($user) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $update = $db->prepare('UPDATE usuarios SET token_reset=?, token_expira_em=? WHERE id=?');
        $userId = (int)$user['id'];
        $update->bind_param('ssi', $tokenHash, $expires, $userId); $update->execute(); $update->close();
        $baseUrl = rtrim((string)($_ENV['APP_URL'] ?? 'http://localhost/app_controle_contas_2'), '/');
        $link = $baseUrl . '/pages/reativar_processo.php?token=' . rawurlencode($rawToken);
        $deadline = date('d/m/Y', strtotime((string)$user['exclusao_programada_em']));
        $name = (string)($user['nome'] ?: 'Cliente');
        $message = 'Olá ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',<br><br>Use o botão abaixo para reativar sua conta e recuperar todos os usuários, cadastros e dados preservados.<br><br><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#0878e8;color:#fff;text-decoration:none;font-weight:bold">Reativar minha conta</a><br><br>O link vale por 1 hora. A exclusão definitiva está programada para ' . $deadline . ' caso a conta não seja reativada.';
        enviarEmail($email, $name, 'Reativação da sua conta', $message);
    }
} catch (Throwable $e) { error_log('Falha ao solicitar reativação: ' . $e->getMessage()); }
finally { $db->close(); }

set_flash_message('success', 'Se a conta estiver dentro do prazo, enviaremos as instruções para o e-mail informado.');
header('Location: ../pages/login.php'); exit;

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/account_lifecycle.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
require_once __DIR__ . '/enviar_email.php';

require_tenant_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
require_csrf();

$tenantId = (string) ($_SESSION['tenant_id'] ?? '');
if ($tenantId === '' || (string)($_POST['opcao_cancelamento'] ?? '') !== 'desativar') {
    set_flash_message('danger', 'Selecione a desativação temporária para continuar.');
    header('Location: ../pages/minha_assinatura.php'); exit;
}

$db = getMasterConnection();
try {
    $stmt = $db->prepare('SELECT * FROM tenants WHERE tenant_id=? LIMIT 1');
    $stmt->bind_param('s', $tenantId); $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$tenant) throw new RuntimeException('Conta não encontrada.');
    if (account_is_temporarily_disabled($tenant)) {
        set_flash_message('warning', 'A conta já está desativada temporariamente.');
        header('Location: ../pages/minha_assinatura.php'); exit;
    }

    $subscriptionId = trim((string)($tenant['id_assinatura_mp'] ?? ''));
    if ($subscriptionId !== '') mp_api_request('PUT', '/preapproval/' . rawurlencode($subscriptionId), ['status' => 'cancelled']);

    $db->begin_transaction();
    $previousStatus = (string)($tenant['status_assinatura'] ?? 'ativo');
    $pauseStatus = account_pause_status();
    $tenantPk = (int)$tenant['id'];
    $update = $db->prepare("UPDATE tenants SET status_antes_desativacao=?, status_assinatura=?, tipo_cancelamento='desativar', desativada_em=NOW(), exclusao_programada_em=DATE_ADD(NOW(), INTERVAL 1 YEAR), reativada_em=NULL, id_assinatura_mp_anterior=id_assinatura_mp, id_assinatura_mp=NULL, data_renovacao=NULL WHERE id=?");
    $update->bind_param('ssi', $previousStatus, $pauseStatus, $tenantPk); $update->execute(); $update->close();

    $suspended = 'suspenso';
    $users = $db->prepare('UPDATE usuarios SET status=? WHERE tenant_id=? AND is_master=0');
    $users->bind_param('ss', $suspended, $tenantId); $users->execute(); $users->close();
    account_set_tenant_users_status($tenant, $suspended);
    $db->commit();

    $deadline = (new DateTimeImmutable('+1 year'))->format('d/m/Y');
    $name = (string)($tenant['nome'] ?: $tenant['nome_empresa'] ?: 'Cliente');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $message = "Olá {$safeName},<br><br>Sua conta foi desativada temporariamente e não haverá novas cobranças.<br>Todos os cadastros e dados permanecerão preservados até <strong>{$deadline}</strong>.<br>Para voltar, acesse a tela de login e use <strong>Reativar Minha Conta</strong> com este e-mail.<br><br>Se a conta não for reativada até essa data, ela e seus dados serão excluídos permanentemente.";
    enviarEmail((string)$tenant['admin_email'], $name, 'Conta desativada temporariamente', $message);

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite'] ?? 'Lax']);
    }
    session_destroy(); session_start();
    set_flash_message('success', 'Conta desativada por até 1 ano. Seus dados foram preservados e as cobranças interrompidas.');
    header('Location: ../pages/login.php');
} catch (Throwable $e) {
    try { $db->rollback(); } catch (Throwable) {}
    error_log('Falha ao desativar conta: ' . $e->getMessage());
    set_flash_message('danger', 'Não foi possível desativar a conta ou interromper a cobrança. Nenhuma alteração foi concluída. Tente novamente ou procure o suporte.');
    header('Location: ../pages/minha_assinatura.php');
} finally { $db->close(); }
exit;

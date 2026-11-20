<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/account_lifecycle.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
require_csrf();
$rawToken = trim((string)($_POST['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) { set_flash_message('danger', 'Link inválido ou expirado.'); header('Location: ../pages/login.php'); exit; }

$hash = hash('sha256', $rawToken);
$db = getMasterConnection();
try {
    $db->begin_transaction();
    $stmt = $db->prepare("SELECT t.* FROM usuarios u JOIN tenants t ON t.tenant_id=u.tenant_id AND LOWER(t.admin_email)=LOWER(u.email) WHERE u.token_reset=? AND u.token_expira_em>NOW() AND t.status_assinatura='desativado_temporario' AND t.exclusao_programada_em>NOW() LIMIT 1 FOR UPDATE");
    $stmt->bind_param('s', $hash); $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$tenant) throw new RuntimeException('Link inválido, expirado ou conta fora do prazo de recuperação.');
    $tenantId = (string)$tenant['tenant_id'];
    $pending = 'pendente';
    $update = $db->prepare("UPDATE tenants SET status_assinatura=?, tipo_cancelamento=NULL, desativada_em=NULL, exclusao_programada_em=NULL, reativada_em=NOW(), id_assinatura_mp=NULL, data_renovacao=NULL WHERE id=?");
    $tenantPk=(int)$tenant['id']; $update->bind_param('si',$pending,$tenantPk); $update->execute(); $update->close();
    $active='ativo';
    $users=$db->prepare("UPDATE usuarios SET status=?, token_reset=NULL, token_expira_em=NULL WHERE tenant_id=? AND is_master=0");
    $users->bind_param('ss',$active,$tenantId); $users->execute(); $users->close();
    account_set_tenant_users_status($tenant,$active);
    $db->commit();
    set_flash_message('success','Conta reativada com todos os usuários e cadastros preservados. Entre com seu e-mail e senha para escolher o plano.');
} catch(Throwable $e) {
    try{$db->rollback();}catch(Throwable){}
    error_log('Falha ao confirmar reativação: '.$e->getMessage());
    set_flash_message('danger','Não foi possível reativar a conta. Solicite um novo link ou procure o suporte.');
} finally { $db->close(); }
header('Location: ../pages/login.php'); exit;

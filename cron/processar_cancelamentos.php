<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Somente CLI.\n"); }

define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/account_lifecycle.php';
require_once __DIR__ . '/../actions/enviar_email.php';

$db = getMasterConnection();
$result = $db->query("SELECT * FROM tenants WHERE status_assinatura IN ('desativado_temporario','exclusao_em_processamento') AND exclusao_programada_em IS NOT NULL AND exclusao_programada_em<=NOW() ORDER BY id");
$deleted = 0; $failed = 0;
while ($tenant = $result->fetch_assoc()) {
    $tenantId = (string)$tenant['tenant_id'];
    try {
        if ((string)$tenant['status_assinatura'] === 'desativado_temporario') {
            $claim = $db->prepare("UPDATE tenants SET status_assinatura='exclusao_em_processamento' WHERE id=? AND status_assinatura='desativado_temporario' AND exclusao_programada_em<=NOW()");
            $tenantPk = (int)$tenant['id'];
            $claim->bind_param('i', $tenantPk); $claim->execute();
            $claimed = $claim->affected_rows === 1; $claim->close();
            if (!$claimed) continue;
        }
        account_delete_tenant_database($tenant);
        $db->begin_transaction();
        $users = $db->prepare('DELETE FROM usuarios WHERE tenant_id=?');
        $users->bind_param('s',$tenantId); $users->execute(); $users->close();
        $tenantDelete = $db->prepare("DELETE FROM tenants WHERE id=? AND status_assinatura='exclusao_em_processamento' AND exclusao_programada_em<=NOW()");
        $tenantPk=(int)$tenant['id']; $tenantDelete->bind_param('i',$tenantPk); $tenantDelete->execute();
        if($tenantDelete->affected_rows!==1) throw new RuntimeException('Tenant mudou de estado durante a exclusão.');
        $tenantDelete->close(); $db->commit(); $deleted++;
        $name=(string)($tenant['nome'] ?: $tenant['nome_empresa'] ?: 'Cliente');
        enviarEmail((string)$tenant['admin_email'],$name,'Conta excluída após o período de preservação','Sua conta permaneceu desativada por um ano sem solicitação de reativação. Conforme informado, a conta e os dados preservados foram excluídos permanentemente.');
        echo "[EXCLUÍDA] {$tenantId}\n";
    } catch(Throwable $e) {
        try{$db->rollback();}catch(Throwable){}
        $failed++; error_log("Falha ao excluir tenant expirado {$tenantId}: ".$e->getMessage());
        fwrite(STDERR,"[ERRO] {$tenantId}: {$e->getMessage()}\n");
    }
}
$db->close();
echo "Resultado: {$deleted} excluída(s), {$failed} falha(s).\n";
exit($failed ? 1 : 0);

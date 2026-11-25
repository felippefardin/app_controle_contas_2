<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Somente CLI.\n"); }

define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/account_lifecycle.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
require_once __DIR__ . '/../actions/enviar_email.php';

$db = getMasterConnection();
$result = $db->query("SELECT * FROM tenants WHERE tipo_cancelamento='excluir' AND exclusao_programada_em IS NOT NULL AND exclusao_programada_em<=NOW() AND EXISTS (SELECT 1 FROM aceites_exclusao_conta a WHERE BINARY a.tenant_id_snapshot=BINARY tenants.tenant_id AND a.data_aceite=tenants.desativada_em) ORDER BY id");
if (in_array('--dry-run', $argv, true)) {
    echo 'Encerramentos vencidos: ', $result->num_rows, PHP_EOL;
    exit(0);
}
$deleted = 0; $failed = 0;
while ($tenant = $result->fetch_assoc()) {
    $tenantId = (string)$tenant['tenant_id'];
    try {
        if ((string)$tenant['status_assinatura'] !== 'exclusao_em_processamento') {
            $claim = $db->prepare("UPDATE tenants SET status_assinatura='exclusao_em_processamento' WHERE id=? AND tipo_cancelamento='excluir' AND status_assinatura<>'exclusao_em_processamento' AND exclusao_programada_em<=NOW()");
            $tenantPk = (int)$tenant['id'];
            $claim->bind_param('i', $tenantPk); $claim->execute();
            $claimed = $claim->affected_rows === 1; $claim->close();
            if (!$claimed) continue;
        }
        if (!empty($tenant['id_assinatura_mp'])) {
            $path = '/preapproval/' . rawurlencode($tenant['id_assinatura_mp']);
            $subscription = mp_api_request('GET', $path)['body'];
            if (!in_array($subscription['status'] ?? '', ['cancelled', 'canceled'], true)) {
                $subscription = mp_api_request('PUT', $path, ['status' => 'cancelled'])['body'];
                if (!in_array($subscription['status'] ?? '', ['cancelled', 'canceled'], true)) {
                    throw new RuntimeException('O Mercado Pago não confirmou o cancelamento da recorrência.');
                }
            }
        }
        if (!preg_match('/^tenant_db_[0-9]+$/D', (string) $tenant['db_database'])) {
            throw new RuntimeException('Banco dedicado inválido para o encerramento.');
        }
        account_delete_tenant_database($tenant);
        $db->begin_transaction();
        // Remove dependent legacy rows in the master database before removing users.
        $dependencies = [
            'DELETE item FROM venda_items item JOIN vendas v ON v.id=item.id_venda JOIN usuarios u ON u.id=v.id_usuario WHERE u.tenant_id=?',
            'DELETE nota FROM notas_fiscais nota JOIN vendas v ON v.id=nota.id_venda JOIN usuarios u ON u.id=v.id_usuario WHERE u.tenant_id=?',
            'DELETE item FROM compra_items item JOIN compras c ON c.id=item.id_compra JOIN usuarios u ON u.id=c.id_usuario WHERE u.tenant_id=?',
            'DELETE m FROM movimento_estoque m JOIN usuarios u ON u.id=m.id_usuario WHERE u.tenant_id=?',
            'DELETE c FROM caixa_diario c JOIN usuarios u ON u.id=c.usuario_id WHERE u.tenant_id=?',
            'DELETE c FROM compras c JOIN usuarios u ON u.id=c.id_usuario WHERE u.tenant_id=?',
            'DELETE v FROM vendas v JOIN usuarios u ON u.id=v.id_usuario WHERE u.tenant_id=?',
        ];
        foreach ($dependencies as $sql) {
            $db->execute_query($sql, [$tenantId]);
        }
        $users = $db->prepare('DELETE FROM usuarios WHERE tenant_id=?');
        $users->bind_param('s',$tenantId); $users->execute(); $users->close();
        $tenantDelete = $db->prepare("DELETE FROM tenants WHERE id=? AND status_assinatura='exclusao_em_processamento' AND exclusao_programada_em<=NOW()");
        $tenantPk=(int)$tenant['id']; $tenantDelete->bind_param('i',$tenantPk); $tenantDelete->execute();
        if($tenantDelete->affected_rows!==1) throw new RuntimeException('Tenant mudou de estado durante a exclusão.');
        $tenantDelete->close(); $db->commit(); $deleted++;
        $name=(string)($tenant['nome'] ?: $tenant['nome_empresa'] ?: 'Cliente');
        enviarEmail((string)$tenant['admin_email'],$name,'Conta excluída ao fim do ciclo','O ciclo da sua conta terminou com o cancelamento pendente. Conforme informado, a conta e os dados preservados foram excluídos permanentemente.');
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

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("Somente CLI.\n");
define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/cupom_service.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';

$precos = ['basico' => 19.00, 'plus' => 39.00, 'essencial' => 59.00];
$db = getMasterConnection();
$tenants = $db->query("SELECT * FROM tenants WHERE status_assinatura='ativo' AND id_assinatura_mp IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
foreach ($tenants as $tenant) {
    try {
        $base = $precos[$tenant['plano_atual']] ?? 19.00;
        $extras = max(0, (int)$tenant['usuarios_extras']) * 4.00;
        $cupom = buscarPromocaoInterna($db, (int)$tenant['id']);
        if (!$cupom && !empty($tenant['cupom_ativo_id'])) {
            $stmt = $db->prepare("SELECT * FROM cupons_desconto WHERE id=? AND ativo=1
                AND (data_inicio IS NULL OR data_inicio<=CURDATE())
                AND (tempo_indeterminado=1 OR data_fim IS NULL OR data_fim>=CURDATE()) LIMIT 1");
            $stmt->bind_param('i', $tenant['cupom_ativo_id']);
            $stmt->execute(); $cupom = $stmt->get_result()->fetch_assoc() ?: null; $stmt->close();
        }
        $valor = $cupom ? calcularDescontoCupom($base, $extras, $cupom)['final'] : $base + $extras;
        mp_api_request('PUT', '/preapproval/' . rawurlencode($tenant['id_assinatura_mp']), [
            'auto_recurring' => ['transaction_amount' => max(0.01, (float)$valor), 'currency_id' => 'BRL'],
        ]);
        if (!$cupom) {
            $stmt = $db->prepare('UPDATE tenants SET cupom_ativo_id=NULL, promocao_ativa_id=NULL WHERE id=?');
            $stmt->bind_param('i', $tenant['id']); $stmt->execute(); $stmt->close();
        }
        echo "[OK] {$tenant['tenant_id']} R$ " . number_format((float)$valor, 2, '.', '') . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, "[ERRO] {$tenant['tenant_id']}: {$e->getMessage()}\n");
    }
}
$db->close();

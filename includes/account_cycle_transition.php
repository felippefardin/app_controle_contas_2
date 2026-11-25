<?php
declare(strict_types=1);

require_once __DIR__ . '/account_cycle_deadline.php';
require_once __DIR__ . '/mercadopago_security.php';

function account_cycle_change(mysqli $db, string $tenantId, bool $restore, callable $record, ?string $requestDate = null): void
{
    $db->begin_transaction();
    $path = null;
    $previous = null;
    $attempted = false;
    try {
        $tenant = $db->execute_query('SELECT *, UNIX_TIMESTAMP(exclusao_programada_em) AS deadline FROM tenants WHERE tenant_id=? FOR UPDATE', [$tenantId])->fetch_assoc();
        if (!$tenant) {
            throw new DomainException('Conta não encontrada.');
        }
        if ($restore && $tenant['desativada_em'] !== $requestDate) {
            throw new DomainException('O protocolo não corresponde ao pedido vigente.');
        }
        $scheduled = ($tenant['tipo_cancelamento'] ?? '') === 'excluir' && (int) $tenant['deadline'] > 0;
        if ($restore ? (!$scheduled || (int) $tenant['deadline'] <= time()) : ($scheduled || !in_array($tenant['status_assinatura'], ['ativo', 'trial'], true))) {
            throw new DomainException('A conta não está elegível para esta operação.');
        }

        $subscription = null;
        if (!empty($tenant['id_assinatura_mp'])) {
            $path = '/preapproval/' . rawurlencode($tenant['id_assinatura_mp']);
            $subscription = mp_api_request('GET', $path)['body'];
            $previous = $subscription['status'] ?? '';
            if ($previous !== ($restore ? 'paused' : 'authorized')) {
                throw new DomainException('O estado da assinatura no Mercado Pago impede a operação.');
            }
        }
        $deadline = $restore ? (int) $tenant['deadline'] : account_cycle_deadline($tenant, $subscription)->getTimestamp();
        if ($deadline <= time()) {
            throw new DomainException('O ciclo informado já terminou.');
        }
        if ($restore) {
            $db->execute_query("UPDATE tenants SET tipo_cancelamento=NULL, exclusao_programada_em=NULL, reativada_em=NOW() WHERE id=?", [$tenant['id']]);
        } else {
            $db->execute_query("UPDATE tenants SET tipo_cancelamento='excluir', desativada_em=NOW(), exclusao_programada_em=FROM_UNIXTIME(?), reativada_em=NULL WHERE id=?", [$deadline, $tenant['id']]);
        }
        $record($db, $tenant);
        if ($path !== null) {
            $target = $restore ? 'authorized' : 'paused';
            $attempted = true;
            $updated = mp_api_request('PUT', $path, ['status' => $target])['body'];
            if (($updated['status'] ?? '') !== $target) {
                throw new RuntimeException('O Mercado Pago não confirmou a alteração.');
            }
            if ($restore && strtotime((string) ($updated['next_payment_date'] ?? '')) !== $deadline) {
                throw new RuntimeException('O Mercado Pago retornou uma data diferente da data de encerramento do ciclo.');
            }
        }
        if ($deadline <= time()) {
            throw new DomainException('O ciclo terminou durante a operação.');
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($attempted) {
            try {
                mp_api_request('PUT', $path, ['status' => $previous]);
            } catch (Throwable $recoveryError) {
                error_log('Falha ao compensar alteração de recorrência: ' . $recoveryError->getMessage());
            }
        }
        $db->rollback();
        throw $error;
    }
}

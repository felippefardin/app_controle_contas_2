<?php
declare(strict_types=1);

/** Resolve the deadline without changing the subscription at the provider. */
function account_cycle_deadline(array $tenant, ?array $subscription = null): DateTimeImmutable
{
    if ($subscription !== null) {
        $nextPayment = trim((string) ($subscription['next_payment_date'] ?? ''));
        if ($nextPayment === '') {
            throw new DomainException('O Mercado Pago não informou a próxima cobrança.');
        }
        return new DateTimeImmutable($nextPayment);
    }

    if (in_array($tenant['status_assinatura'] ?? '', ['trial', 'trial_expired'], true)) {
        $start = trim((string) ($tenant['data_inicio_teste'] ?? $tenant['data_criacao'] ?? ''));
        if ($start !== '') {
            $days = ($tenant['plano_atual'] ?? '') === 'essencial' ? 30 : 15;
            return (new DateTimeImmutable($start))->modify("+$days days");
        }
    }

    $renewal = trim((string) ($tenant['data_renovacao'] ?? ''));
    if ($renewal === '') {
        throw new DomainException('Não foi possível confirmar o fim do ciclo.');
    }
    return new DateTimeImmutable($renewal);
}

<?php

declare(strict_types=1);

test_register('unit', 'status de pausa temporaria e reconhecido', function (): void {
    test_same('desativado_temporario', account_pause_status());
    test_assert(account_is_temporarily_disabled([
        'status_assinatura' => 'desativado_temporario',
        'exclusao_programada_em' => date('Y-m-d H:i:s', time() + 3600),
    ]));
});

test_register('unit', 'prazo vencido ou outro status nao e pausa valida', function (): void {
    test_assert(!account_is_temporarily_disabled([
        'status_assinatura' => 'desativado_temporario',
        'exclusao_programada_em' => date('Y-m-d H:i:s', time() - 3600),
    ]));
    test_assert(!account_is_temporarily_disabled([
        'status_assinatura' => 'ativo',
        'exclusao_programada_em' => date('Y-m-d H:i:s', time() + 3600),
    ]));
});

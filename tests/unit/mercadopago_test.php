<?php

test_register('unit', 'Assinatura válida do webhook Mercado Pago é aceita', static function (): void {
    $secret = 'segredo-de-teste'; $requestId = 'req-123'; $dataId = 'ABC123'; $ts = '1720000000';
    $manifest = 'id:abc123;request-id:req-123;ts:1720000000;';
    $signature = 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, $secret);
    test_assert(mp_validate_webhook_signature($signature, $requestId, $dataId, $secret));
});

test_register('unit', 'Webhook adulterado é rejeitado', static function (): void {
    test_assert(!mp_validate_webhook_signature('ts=1,v1=invalid', 'req', '10', 'secret'));
    test_assert(!mp_validate_webhook_signature('', 'req', '10', 'secret'));
});


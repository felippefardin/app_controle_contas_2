<?php

test_register('unit', 'Auditoria remove senhas e tokens', static function (): void {
    $safe = audit_sanitize(['email' => 'teste@example.com', 'senha' => 'hash', 'token_reset' => 'token', 'db_password' => 'secret']);
    test_same(['email' => 'teste@example.com'], $safe);
});

test_register('unit', 'Somente entidades autorizadas possuem exclusão lógica', static function (): void {
    test_assert(isset(SOFT_DELETE_ENTITIES['contas_pagar'], SOFT_DELETE_ENTITIES['contas_receber'], SOFT_DELETE_ENTITIES['produtos'], SOFT_DELETE_ENTITIES['usuarios']));
    test_assert(!isset(SOFT_DELETE_ENTITIES['tenants']));
});


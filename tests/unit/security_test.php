<?php

test_register('unit', 'CSRF aceita somente o token da sessão', static function (): void {
    $_SESSION['csrf_token'] = str_repeat('a', 64);
    test_assert(csrf_is_valid(str_repeat('a', 64)));
    test_assert(!csrf_is_valid(str_repeat('b', 64)));
    test_assert(!csrf_is_valid(null));
});

test_register('unit', 'Administrador e usuário comum são diferenciados', static function (): void {
    $_SESSION['nivel_acesso'] = 'admin';
    test_assert(is_tenant_admin());
    $_SESSION['nivel_acesso'] = 'padrao';
    test_assert(!is_tenant_admin());
});

test_register('unit', 'Permissões de página não concedem acesso indevido', static function (): void {
    $_SESSION['nivel_acesso'] = 'padrao';
    $_SESSION['permissoes'] = ['contas_receber.php'];
    test_assert(has_page_permission('contas_receber.php'));
    test_assert(!has_page_permission('usuarios.php'));
});

test_register('unit', 'Usuário extra compartilha o proprietário correto', static function (): void {
    $_SESSION['usuario_id'] = 22;
    $_SESSION['dados_usuario_id'] = 7;
    test_same(7, get_data_owner_id());
    unset($_SESSION['dados_usuario_id']);
    test_same(22, get_data_owner_id());
});

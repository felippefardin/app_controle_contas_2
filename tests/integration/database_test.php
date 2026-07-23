<?php

test_register('integration', 'Migrações estão aplicadas em todos os bancos', static function (): void {
    $master = getMasterConnection();
    $masterStatus = run_migrations_for_connection($master, 'master', true, true);
    test_same([], $masterStatus['pending'], 'Banco principal possui migração pendente');
    $tenants = $master->query('SELECT db_host, db_database, db_user, db_password FROM tenants')->fetch_all(MYSQLI_ASSOC);
    foreach ($tenants as $tenant) {
        $db = new mysqli($tenant['db_host'], $tenant['db_user'], $tenant['db_password'], $tenant['db_database']);
        $status = run_migrations_for_connection($db, 'tenant', true, true);
        test_same([], $status['pending'], $tenant['db_database'] . ' possui migração pendente');
        $db->close();
    }
    $master->close();
});

test_register('integration', 'Exclusão lógica, isolamento, auditoria e restauração funcionam', static function (): void {
    $master = getMasterConnection(); $db = test_tenant_connection($master); $master->close();
    $db->begin_transaction();
    try {
        $owners = $db->query('SELECT id FROM usuarios WHERE deleted_at IS NULL ORDER BY id LIMIT 2')->fetch_all(MYSQLI_ASSOC);
        test_assert(count($owners) >= 1, 'Tenant sem proprietário para teste');
        $ownerId = (int) $owners[0]['id'];
        $wrongOwner = isset($owners[1]) ? (int) $owners[1]['id'] : $ownerId + 999999;
        $name = '__automated_soft_delete_' . bin2hex(random_bytes(4));
        $stmt = $db->prepare('INSERT INTO produtos (id_usuario, nome, quantidade_estoque) VALUES (?, ?, 0)');
        $stmt->bind_param('is', $ownerId, $name); $stmt->execute(); $id = (int) $db->insert_id; $stmt->close();
        $_SESSION['usuario_id'] = $ownerId; $_SESSION['nome'] = 'Teste automatizado';
        test_assert(!soft_delete_record($db, 'produtos', $id, $wrongOwner), 'Outro proprietário conseguiu excluir o registro');
        test_assert(soft_delete_record($db, 'produtos', $id, $ownerId));
        test_same('0', (string) $db->query("SELECT COUNT(*) FROM produtos WHERE id=$id AND deleted_at IS NULL")->fetch_row()[0]);
        test_same('1', (string) $db->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='produtos' AND entity_id='$id' AND action='soft_delete'")->fetch_row()[0]);
        test_assert(restore_soft_deleted_record($db, 'produtos', $id, $ownerId));
        test_same('1', (string) $db->query("SELECT COUNT(*) FROM produtos WHERE id=$id AND deleted_at IS NULL")->fetch_row()[0]);
    } finally {
        $db->rollback(); $db->close();
    }
});

test_register('integration', 'Banco impede fatura duplicada do Mercado Pago', static function (): void {
    $master = getMasterConnection(); $master->begin_transaction();
    try {
        $transaction = 'test-' . bin2hex(random_bytes(8)); $tenant = 'test'; $value = 1.0; $date = date('Y-m-d'); $status = 'pendente';
        $stmt = $master->prepare('INSERT INTO faturas_assinatura (tenant_id, valor, data_vencimento, status, transacao_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sdsss', $tenant, $value, $date, $status, $transaction); $stmt->execute();
        $blocked = false;
        try { $stmt->execute(); } catch (mysqli_sql_exception $e) { $blocked = $e->getCode() === 1062; }
        $stmt->close(); test_assert($blocked, 'Transação duplicada não foi bloqueada');
    } finally {
        $master->rollback(); $master->close();
    }
});


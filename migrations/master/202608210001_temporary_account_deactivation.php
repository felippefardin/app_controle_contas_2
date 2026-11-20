<?php
return [
    'id' => '202608210001_temporary_account_deactivation',
    'description' => 'Ciclo de desativação temporária, reativação e exclusão após 365 dias',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->addColumn('tenants', 'desativada_em', 'DATETIME NULL AFTER tipo_cancelamento');
        $ctx->addColumn('tenants', 'exclusao_programada_em', 'DATETIME NULL AFTER desativada_em');
        $ctx->addColumn('tenants', 'reativada_em', 'DATETIME NULL AFTER exclusao_programada_em');
        $ctx->addColumn('tenants', 'status_antes_desativacao', 'VARCHAR(50) NULL AFTER reativada_em');
        $ctx->addColumn('tenants', 'id_assinatura_mp_anterior', 'VARCHAR(100) NULL AFTER status_antes_desativacao');
        $ctx->addIndex('tenants', 'idx_tenants_exclusao_programada', 'INDEX `idx_tenants_exclusao_programada` (`status_assinatura`, `exclusao_programada_em`)');
    },
];

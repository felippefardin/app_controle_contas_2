<?php
return [
    'id' => '202607270001_referral_two_free_months',
    'description' => 'Registra e controla dois meses grátis para novos usuários indicados',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->addColumn('tenants', 'indicacao_meses_gratis', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
        $ctx->addColumn('tenants', 'indicacao_inicio_beneficio', 'DATETIME NULL');
        $ctx->addColumn('tenants', 'indicacao_fim_beneficio', 'DATETIME NULL');
        $ctx->addColumn('tenants', 'indicacao_beneficio_utilizado', 'TINYINT(1) NOT NULL DEFAULT 0');

        $ctx->addColumn('checkout_assinaturas', 'meses_gratis_indicacao', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
        $ctx->addColumn('checkout_assinaturas', 'inicio_cobranca', 'DATETIME NULL');

        // Contas já vinculadas a um indicador também recebem o benefício,
        // desde que ainda não exista registro de utilização.
        $ctx->db->query("
            UPDATE tenants t
            JOIN indicacoes i ON i.id_indicado = t.usuario_id
            SET t.indicacao_meses_gratis = 2
            WHERE t.indicacao_beneficio_utilizado = 0
              AND t.indicacao_meses_gratis = 0
        ");
    },
];

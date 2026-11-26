<?php
return [
    'id' => '202607270002_coupon_validity',
    'description' => 'Vigência e duração indeterminada de cupons e promoções',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->addColumn('cupons_desconto', 'data_inicio', 'DATE NULL');
        $ctx->addColumn('cupons_desconto', 'data_fim', 'DATE NULL');
        $ctx->addColumn('cupons_desconto', 'tempo_indeterminado', 'TINYINT(1) NOT NULL DEFAULT 0');
        $ctx->addColumn('tenant_promocoes', 'tempo_indeterminado', 'TINYINT(1) NOT NULL DEFAULT 0');
        $ctx->addColumn('tenants', 'cupom_ativo_id', 'INT NULL');
        $ctx->addColumn('tenants', 'promocao_ativa_id', 'INT NULL');
        $ctx->addColumn('checkout_assinaturas', 'promocao_id', 'INT NULL');
        $ctx->db->query("UPDATE cupons_desconto SET data_fim=data_expiracao WHERE data_fim IS NULL AND data_expiracao IS NOT NULL");
        $ctx->db->query("UPDATE cupons_desconto SET tempo_indeterminado=1 WHERE data_fim IS NULL");
    },
];

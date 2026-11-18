<?php

declare(strict_types=1);

return [
    'id' => '202608210002_manual_billing_control',
    'description' => 'Auditoria das cobrancas manuais geradas pelo painel master',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query("CREATE TABLE IF NOT EXISTS cobrancas_manuais (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id VARCHAR(32) NOT NULL,
            checkout_id BIGINT UNSIGNED NULL,
            mp_preapproval_id VARCHAR(120) NULL,
            email_destino VARCHAR(190) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'enviada',
            solicitado_por_email VARCHAR(190) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cobranca_manual_tenant (tenant_id, criado_em),
            KEY idx_cobranca_manual_status (status, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];

<?php
return [
    'id' => '202607220001_billing_idempotency',
    'description' => 'Controle idempotente de checkout e webhooks do Mercado Pago',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query("CREATE TABLE IF NOT EXISTS checkout_assinaturas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, checkout_token CHAR(64) NOT NULL, tenant_id VARCHAR(32) NOT NULL,
            plano VARCHAR(50) NOT NULL, conta_receber_id INT NULL, cupom_id INT NULL, idempotency_key CHAR(64) NOT NULL,
            valor_original DECIMAL(10,2) NOT NULL, valor_final DECIMAL(10,2) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'criando', mp_preapproval_id VARCHAR(120) NULL, init_point TEXT NULL,
            solicitado_por INT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY ux_checkout_token (checkout_token),
            UNIQUE KEY ux_checkout_idempotency (idempotency_key), UNIQUE KEY ux_checkout_preapproval (mp_preapproval_id),
            KEY idx_checkout_reutilizacao (tenant_id, plano, status, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ctx->db->query("CREATE TABLE IF NOT EXISTS webhook_eventos_mp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, request_id VARCHAR(150) NOT NULL, resource_id VARCHAR(150) NOT NULL,
            tipo VARCHAR(100) NOT NULL, acao VARCHAR(120) NULL, assinatura_valida TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'recebido', tentativas INT NOT NULL DEFAULT 1, erro VARCHAR(500) NULL,
            payload MEDIUMTEXT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, processado_em DATETIME NULL,
            PRIMARY KEY (id), UNIQUE KEY ux_webhook_request_resource (request_id, resource_id, tipo),
            KEY idx_webhook_status (status, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if ($ctx->tableExists('faturas_assinatura')) {
            $ctx->addIndex('faturas_assinatura', 'ux_fatura_transacao_id', 'UNIQUE INDEX ux_fatura_transacao_id (transacao_id)');
        }
    },
];


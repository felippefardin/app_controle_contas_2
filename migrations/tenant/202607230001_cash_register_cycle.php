<?php
return [
    'id' => '202607230001_cash_register_cycle',
    'description' => 'Ciclo diário de abertura, movimentação e fechamento do PDV',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query("CREATE TABLE IF NOT EXISTS aberturas_caixa (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT NOT NULL,
            data_caixa DATE NOT NULL,
            valor_abertura DECIMAL(14,2) NOT NULL DEFAULT 0,
            status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
            aberto_por INT NULL,
            aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fechado_por INT NULL,
            fechado_em DATETIME NULL,
            observacao VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ux_abertura_usuario_data (id_usuario, data_caixa),
            KEY idx_abertura_status_data (status, data_caixa)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if ($ctx->tableExists('caixa_diario')) {
            $ctx->addColumn('caixa_diario', 'forma_pagamento', 'VARCHAR(50) NULL');
            $ctx->addColumn('caixa_diario', 'id_venda', 'INT NULL');
            $ctx->addColumn('caixa_diario', 'abertura_id', 'BIGINT UNSIGNED NULL');
            $ctx->addColumn('caixa_diario', 'criado_por', 'INT NULL');
            $ctx->addColumn('caixa_diario', 'criado_em', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
            $ctx->addIndex('caixa_diario', 'ux_caixa_venda', 'UNIQUE INDEX ux_caixa_venda (usuario_id, id_venda)');
            $ctx->addIndex('caixa_diario', 'idx_caixa_usuario_data', 'INDEX idx_caixa_usuario_data (usuario_id, data)');
        }
        if ($ctx->tableExists('fechamentos_caixa')) {
            $ctx->addColumn('fechamentos_caixa', 'abertura_id', 'BIGINT UNSIGNED NULL');
            $ctx->addColumn('fechamentos_caixa', 'valor_abertura', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
            $ctx->addColumn('fechamentos_caixa', 'total_dinheiro', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
            $ctx->addColumn('fechamentos_caixa', 'total_outros', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
            $ctx->addColumn('fechamentos_caixa', 'saldo_dinheiro_esperado', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        }
    },
];


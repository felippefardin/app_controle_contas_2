<?php
return [
    'id' => '202608100001_purchase_payables_flow',
    'description' => 'Vínculo entre compras e contas a pagar, com categoria, documento e parcelas',
    'up' => static function (MigrationContext $ctx): void {
        if ($ctx->tableExists('compras')) {
            $ctx->addColumn('compras', 'numero_documento', 'VARCHAR(20) NULL');
            $ctx->addColumn('compras', 'id_categoria', 'INT NULL');
            $ctx->addColumn('compras', 'data_primeiro_vencimento', 'DATE NULL');
            $ctx->addColumn('compras', 'numero_parcelas', 'INT NOT NULL DEFAULT 1');
            $ctx->addColumn('compras', 'gerar_conta_pagar', 'TINYINT(1) NOT NULL DEFAULT 1');
            $ctx->addColumn('compras', 'condicao_pagamento', "VARCHAR(20) NOT NULL DEFAULT 'unica'");
            $ctx->addIndex('compras', 'idx_compras_categoria', 'INDEX idx_compras_categoria (id_categoria)');
        }
        if ($ctx->tableExists('contas_pagar')) {
            $ctx->addColumn('contas_pagar', 'id_compra', 'INT NULL');
            $ctx->addColumn('contas_pagar', 'parcela_numero', 'INT NULL');
            $ctx->addColumn('contas_pagar', 'parcela_total', 'INT NULL');
            $ctx->addIndex('contas_pagar', 'ux_contas_pagar_compra_parcela', 'UNIQUE INDEX ux_contas_pagar_compra_parcela (id_compra, parcela_numero)');
        }
    },
];

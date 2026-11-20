<?php
return [
    'id' => '202607220001_operational_baseline',
    'description' => 'Colunas operacionais exigidas pelos usuários, estoque e área fiscal',
    'up' => static function (MigrationContext $ctx): void {
        if ($ctx->tableExists('usuarios')) {
            $ctx->addColumn('usuarios', 'owner_id', 'INT NULL');
            $ctx->addColumn('usuarios', 'tema_preferencia', "VARCHAR(10) NOT NULL DEFAULT 'dark'");
            $ctx->addColumn('usuarios', 'permissoes', 'TEXT NULL');
            $ctx->addColumn('usuarios', 'is_master', 'TINYINT(1) NOT NULL DEFAULT 0');
        }
        if ($ctx->tableExists('produtos')) {
            $ctx->addColumn('produtos', 'codigo', 'VARCHAR(50) NULL AFTER id_usuario');
            $ctx->addColumn('produtos', 'ncm', 'VARCHAR(8) NULL');
            $ctx->addColumn('produtos', 'cfop', 'VARCHAR(4) NULL');
            $ctx->addColumn('produtos', 'cest', 'VARCHAR(7) NULL');
            $ctx->addColumn('produtos', 'origem', 'TINYINT NOT NULL DEFAULT 0');
            $ctx->addColumn('produtos', 'csosn', "VARCHAR(3) NULL DEFAULT '102'");
            $ctx->addColumn('produtos', 'cst_icms', 'VARCHAR(3) NULL');
            $ctx->addColumn('produtos', 'aliquota_icms', 'DECIMAL(7,4) NOT NULL DEFAULT 0');
            $ctx->addColumn('produtos', 'pis_cst', "VARCHAR(2) NOT NULL DEFAULT '07'");
            $ctx->addColumn('produtos', 'cofins_cst', "VARCHAR(2) NOT NULL DEFAULT '07'");
        }
    },
];


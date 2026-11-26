<?php

declare(strict_types=1);

return [
    'id' => '202608210004_lgpd_consent_metadata',
    'description' => 'Metadados de versao, integridade e preferencias do aceite LGPD',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->addColumn('termos_consentimento', 'versao_documento', "VARCHAR(20) NULL");
        $ctx->addColumn('termos_consentimento', 'hash_documento', "CHAR(64) NULL");
        $ctx->addColumn('termos_consentimento', 'preferencias_json', "TEXT NULL");
        $ctx->addColumn('termos_consentimento', 'user_agent_hash', "CHAR(64) NULL");
    },
];

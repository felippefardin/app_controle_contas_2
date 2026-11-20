<?php

declare(strict_types=1);

return [
    'id' => '202608210003_account_deletion_consents',
    'description' => 'Provas de aceite da exclusao definitiva com retencao de cinco anos',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query("CREATE TABLE IF NOT EXISTS aceites_exclusao_conta (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            protocolo VARCHAR(40) NOT NULL,
            usuario_id_original INT NULL,
            tenant_id_snapshot VARCHAR(32) NULL,
            nome_snapshot VARCHAR(150) NOT NULL,
            email_snapshot VARCHAR(190) NOT NULL,
            documento_hash CHAR(64) NULL,
            versao_termo VARCHAR(20) NOT NULL,
            texto_aceito MEDIUMTEXT NOT NULL,
            texto_hash CHAR(64) NOT NULL,
            ip_usuario VARCHAR(45) NULL,
            user_agent_hash CHAR(64) NULL,
            data_aceite DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            excluir_apos DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ux_aceite_exclusao_protocolo (protocolo),
            KEY idx_aceite_exclusao_email (email_snapshot),
            KEY idx_aceite_exclusao_retencao (excluir_apos)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];

<?php
return [
    'id' => '202608040001_solicitacoes_lgpd',
    'description' => 'Fila de solicitações de acesso, correção e exclusão de dados pessoais',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query("CREATE TABLE IF NOT EXISTS solicitacoes_lgpd (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            protocolo VARCHAR(32) NOT NULL,
            tipo ENUM('acesso','correcao','exclusao') NOT NULL,
            email VARCHAR(190) NOT NULL,
            telefone VARCHAR(30) NOT NULL,
            descricao TEXT NOT NULL,
            usuario_id INT NULL,
            tenant_id VARCHAR(32) NULL,
            status ENUM('nova','em_analise','concluida','recusada') NOT NULL DEFAULT 'nova',
            resposta_admin TEXT NULL,
            atualizado_por VARCHAR(190) NULL,
            ip_hash CHAR(64) NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ux_solicitacoes_lgpd_protocolo (protocolo),
            KEY idx_solicitacoes_lgpd_status_data (status, criado_em),
            KEY idx_solicitacoes_lgpd_email (email),
            KEY idx_solicitacoes_lgpd_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];

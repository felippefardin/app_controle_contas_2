<?php
return [
    'id' => '202607270003_referrer_rewards',
    'description' => 'Créditos automáticos de um mês por três indicações',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query("CREATE TABLE IF NOT EXISTS indicacao_recompensas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            indicador_id INT NOT NULL, ciclo INT NOT NULL, meses TINYINT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('pendente','reservado','aplicado','erro') NOT NULL DEFAULT 'pendente',
            checkout_id BIGINT UNSIGNED NULL, mp_preapproval_id VARCHAR(120) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, aplicado_em DATETIME NULL,
            PRIMARY KEY(id), UNIQUE KEY ux_indicador_ciclo(indicador_id,ciclo),
            KEY idx_recompensa_status(indicador_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ctx->addColumn('checkout_assinaturas', 'meses_gratis_indicador', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
    },
];

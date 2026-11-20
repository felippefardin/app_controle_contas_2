<?php
return [
    'id' => '202607220002_audit_soft_deletes',
    'description' => 'Auditoria e exclusão lógica dos registros operacionais críticos',
    'up' => static function (MigrationContext $ctx): void {
        $ctx->db->query("CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, actor_id INT NULL, actor_name VARCHAR(150) NULL,
            action VARCHAR(50) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id VARCHAR(80) NULL,
            before_data JSON NULL, after_data JSON NULL, metadata JSON NULL, ip_address VARCHAR(45) NULL,
            user_agent_hash CHAR(64) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_audit_entity (entity_type, entity_id),
            KEY idx_audit_actor_date (actor_id, created_at), KEY idx_audit_action_date (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['contas_pagar', 'contas_receber', 'produtos', 'usuarios'] as $table) {
            if (!$ctx->tableExists($table)) continue;
            $ctx->addColumn($table, 'deleted_at', 'DATETIME NULL');
            $ctx->addColumn($table, 'deleted_by', 'INT NULL');
            $ctx->addIndex($table, 'idx_' . $table . '_deleted_at', 'INDEX idx_' . $table . '_deleted_at (deleted_at)');
        }
    },
];


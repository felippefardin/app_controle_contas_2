<?php
require_once '../database.php';
require_once '../includes/session_init.php';

/*
-------------------------------------------------------------
🧩 MIGRAÇÃO COMPLETA DAS TABELAS DE ASSINATURAS (MULTI-TENANT)
Este script:
  ✅ Garante que cada tenant tenha:
     - Colunas: email, plano, valor, status, data_criacao
     - Chave estrangeira para usuarios(id)
     - Charset utf8mb4 e collation utf8mb4_0900_ai_ci
-------------------------------------------------------------
*/

echo "<pre>";
echo "🔄 Iniciando migração completa das tabelas de assinaturas...\n\n";

$connMaster = getMasterConnection();
if (!$connMaster) {
    die("❌ Erro: não foi possível conectar ao banco master.\n");
}

$query = "SHOW DATABASES LIKE 'tenant_%'";
$result = $connMaster->query($query);

if ($result->num_rows === 0) {
    die("⚠️ Nenhum banco tenant encontrado.\n");
}

while ($row = $result->fetch_array()) {
    $dbName = $row[0];
    echo "🔹 Verificando banco: {$dbName} ... ";

    try {
        $tenantConn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $dbName);
        $tenantConn->set_charset("utf8mb4");

        // 🔍 Verifica se a tabela 'assinaturas' existe
        $checkTable = $tenantConn->query("SHOW TABLES LIKE 'assinaturas'");
        if ($checkTable->num_rows === 0) {
            echo "⚠️ Tabela 'assinaturas' não encontrada, ignorando.\n";
            continue;
        }

        // 🔍 Obtém colunas existentes
        $columnsRes = $tenantConn->query("SHOW COLUMNS FROM assinaturas");
        $columns = [];
        while ($col = $columnsRes->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        $alterations = [];

        // ✅ Adiciona colunas ausentes
        if (!in_array('email', $columns)) {
            $alterations[] = "ADD COLUMN email VARCHAR(255) NOT NULL AFTER id_usuario";
        }
        if (!in_array('plano', $columns)) {
            $alterations[] = "ADD COLUMN plano VARCHAR(50) NOT NULL AFTER email";
        }
        if (!in_array('valor', $columns)) {
            $alterations[] = "ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER plano";
        }
        if (!in_array('status', $columns)) {
            $alterations[] = "ADD COLUMN status VARCHAR(50) DEFAULT 'pendente' AFTER valor";
        }
        if (!in_array('data_criacao', $columns)) {
            $alterations[] = "ADD COLUMN data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP AFTER mp_preapproval_id";
        }

        // 🔧 Aplica as alterações de estrutura
        if (!empty($alterations)) {
            $alterSQL = "ALTER TABLE assinaturas " . implode(', ', $alterations);
            $tenantConn->query($alterSQL);
            echo "🆕 Colunas adicionadas com sucesso!\n";
        } else {
            echo "✅ Estrutura já atualizada.\n";
        }

        // 🔗 Verifica e adiciona chave estrangeira (se faltar)
        $fkCheck = $tenantConn->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME='assinaturas' 
              AND TABLE_SCHEMA='{$dbName}' 
              AND REFERENCED_TABLE_NAME='usuarios'
        ");
        if ($fkCheck->num_rows === 0) {
            $tenantConn->query("
                ALTER TABLE assinaturas 
                ADD CONSTRAINT fk_assinaturas_usuario 
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
            ");
            echo "🔗 Chave estrangeira adicionada.\n";
        }

        // 🧠 Ajusta charset e collation da tabela
        $tenantConn->query("ALTER TABLE assinaturas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        echo "✨ Charset e collation atualizados.\n";

        $tenantConn->close();

    } catch (Exception $e) {
        echo "❌ Erro no banco {$dbName}: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Migração completa concluída!\n";
echo "</pre>";
?>

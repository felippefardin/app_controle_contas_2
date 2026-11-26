<?php
require_once '../database.php';
require_once '../includes/session_init.php';

require_super_admin();

// ---------------------------------------------
// SCRIPT: Migração automática da coluna 'valor'
// Adiciona o campo 'valor' na tabela 'assinaturas'
// em todos os bancos tenant que ainda não o possuem.
// ---------------------------------------------

echo "<pre>";
echo "🔄 Iniciando migração da coluna 'valor' em todos os tenants...\n\n";

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

        // Verifica se a tabela assinaturas existe
        $checkTable = $tenantConn->query("SHOW TABLES LIKE 'assinaturas'");
        if ($checkTable->num_rows === 0) {
            echo "⚠️ Tabela 'assinaturas' não encontrada, ignorando.\n";
            continue;
        }

        // Verifica se a coluna 'valor' existe
        $columnsRes = $tenantConn->query("SHOW COLUMNS FROM assinaturas");
        $columns = [];
        while ($col = $columnsRes->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        if (!in_array('valor', $columns)) {
            $tenantConn->query("ALTER TABLE assinaturas ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER plano");
            echo "🆕 Coluna 'valor' adicionada com sucesso!\n";
        } else {
            echo "✅ Coluna 'valor' já existe.\n";
        }

        $tenantConn->close();
    } catch (Exception $e) {
        echo "❌ Erro no banco {$dbName}: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Migração concluída!\n";
echo "</pre>";
?>

<?php
// Arquivo: actions/atualizar_tenants.php
// Objetivo: Criar a coluna 'codigo' em TODOS os bancos de dados dos clientes

// Ajuste o caminho se necessário para encontrar o database.php
require_once '../includes/session_init.php';
require_once '../database.php'; 

require_super_admin();

// 1. Conecta ao banco principal (Master) usando a função do seu sistema
$connMain = getMasterConnection();

if ($connMain->connect_error) {
    die("Erro ao conectar no banco principal: " . $connMain->connect_error);
}

// 2. Busca todos os tenants (clientes)
$sqlTenants = "SELECT * FROM tenants";
$result = $connMain->query($sqlTenants);

echo "<h2>Iniciando atualização dos bancos de dados...</h2>";
echo "<p>Procurando por clientes cadastrados...</p>";

$count = 0;
while ($tenant = $result->fetch_assoc()) {
    $dbName = $tenant['db_database'];
    $dbUser = $tenant['db_user'];
    $dbPass = $tenant['db_password'];
    $dbHost = $tenant['db_host'];

    echo "<hr>Verificando cliente: <strong>" . htmlspecialchars($tenant['nome']) . "</strong> (Banco: $dbName)... <br>";

    // Conecta no banco do tenant específico
    try {
        $connTenant = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        
        if ($connTenant->connect_error) {
            echo "<span style='color:red'>Erro de conexão: " . $connTenant->connect_error . "</span><br>";
            continue;
        }

        // Verifica se a coluna 'codigo' já existe na tabela 'produtos'
        $checkSql = "SHOW COLUMNS FROM produtos LIKE 'codigo'";
        $checkResult = $connTenant->query($checkSql);

        if ($checkResult && $checkResult->num_rows == 0) {
            // Se não existe, cria a coluna
            $alterSql = "ALTER TABLE `produtos` 
                         ADD COLUMN `codigo` VARCHAR(50) DEFAULT NULL AFTER `id_usuario`,
                         ADD INDEX `idx_codigo` (`codigo`)";
            
            if ($connTenant->query($alterSql)) {
                echo "<span style='color:green'><strong>SUCESSO:</strong> Coluna 'codigo' criada.</span><br>";
                $count++;
            } else {
                echo "<span style='color:red'>ERRO SQL: " . $connTenant->error . "</span><br>";
            }
        } else {
            echo "<span style='color:blue'>OK: Coluna já existia.</span><br>";
        }

        $connTenant->close();

    } catch (Exception $e) {
        echo "<span style='color:red'>Erro crítico ao tentar conectar: " . $e->getMessage() . "</span><br>";
    }
}

echo "<hr><h3>Atualização concluída!</h3>";
echo "<p>Total de bancos atualizados agora: $count</p>";
echo "<p>Agora você pode tentar cadastrar o produto novamente.</p>";

$connMain->close();
?>

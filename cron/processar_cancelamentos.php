<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Somente CLI.\n");
}
// cron/processar_cancelamentos.php
// Deve ser executado diariamente via CRON JOB do servidor (ex: 00:01)

require_once __DIR__ . '/../includes/config/config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../actions/enviar_email.php';

$conn = getMasterConnection();
$hoje = date('Y-m-d');

// Busca TENANTS com assinatura vencida E com cancelamento agendado
// Nota: Assumindo que 'data_renovacao' é a data limite
$sql = "SELECT * FROM tenants 
        WHERE data_renovacao < ? 
        AND tipo_cancelamento IS NOT NULL 
        AND status_assinatura != 'suspenso'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $hoje);
$stmt->execute();
$result = $stmt->get_result();

while ($tenant = $result->fetch_assoc()) {
    
    $tenant_id = $tenant['tenant_id'];
    $admin_email = $tenant['admin_email'];
    $nome_empresa = $tenant['nome_empresa'];

    if ($tenant['tipo_cancelamento'] == 'desativar') {
        // ==========================================
        // OPÇÃO 1: APENAS SUSPENDER (Mantém dados)
        // ==========================================
        
        // Atualiza Tenant
        $upd = $conn->prepare("UPDATE tenants SET status_assinatura = 'suspenso', tipo_cancelamento = NULL WHERE id = ?");
        $upd->bind_param("i", $tenant['id']);
        $upd->execute();
        $upd->close();

        // Atualiza Usuários vinculados para inativo/suspenso (opcional, dependendo da sua lógica de login)
        $updUser = $conn->prepare("UPDATE usuarios SET status = 'suspenso' WHERE tenant_id = ?");
        $updUser->bind_param("s", $tenant_id);
        $updUser->execute();
        $updUser->close();

        $assunto = "Conta Suspensa - Período Encerrado";
        $msg = "Olá,<br>O período de vigência da conta <strong>$nome_empresa</strong> encerrou. O acesso foi suspenso conforme solicitado. Seus dados estão salvos.";
        enviarEmail($admin_email, $nome_empresa, $assunto, $msg);

    } elseif ($tenant['tipo_cancelamento'] == 'excluir') {
        // ==========================================
        // OPÇÃO 2: EXCLUSÃO TOTAL (Banco + Registros)
        // ==========================================
        
        $db_name = $tenant['db_database'];
        $db_user = $tenant['db_user'];

        // 1. Dropar o Banco de Dados do Tenant
        if (!empty($db_name)) {
            // Conecta como root ou usuário com permissão de DROP
            $db_host_master = $_ENV['DB_HOST'] ?? 'localhost';
            $db_user_master = $_ENV['DB_USER'] ?? 'root';
            $db_pass_master = $_ENV['DB_PASSWORD'] ?? '';

            $connDrop = new mysqli($db_host_master, $db_user_master, $db_pass_master);
            
            if (!$connDrop->connect_error) {
                $connDrop->query("DROP DATABASE IF EXISTS `$db_name`");
                // Tenta remover usuário do MySQL se existir lógica para isso
                try {
                    $connDrop->query("DROP USER IF EXISTS '$db_user'@'localhost'");
                } catch (Exception $e) { /* ignora */ }
                $connDrop->close();
            }
        }

        // 2. Apagar usuários vinculados na tabela master
        $delUsers = $conn->prepare("DELETE FROM usuarios WHERE tenant_id = ?");
        $delUsers->bind_param("s", $tenant_id);
        $delUsers->execute();
        $delUsers->close();

        // 3. Apagar o Tenant
        $delTenant = $conn->prepare("DELETE FROM tenants WHERE id = ?");
        $delTenant->bind_param("i", $tenant['id']);
        
        if ($delTenant->execute()) {
            $assunto = "Conta Encerrada e Excluída";
            $msg = "A conta <strong>$nome_empresa</strong> e todos os seus dados foram excluídos permanentemente conforme agendamento.";
            enviarEmail($admin_email, $nome_empresa, $assunto, $msg);
        }
        $delTenant->close();
    }
}

$stmt->close();
$conn->close();
?>

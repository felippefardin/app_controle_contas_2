<?php
require_once '../includes/session_init.php';
include('../database.php');

// 1. Verifica se é Super Admin
if (!isset($_SESSION['super_admin'])) {
    session_write_close();
    header('Location: ../pages/login.php?erro=sessao_expirada');
    exit;
}

if (isset($_GET['tenant_id'])) {
    $tenant_id = (int)$_GET['tenant_id'];

    // 2. Backup da sessão Admin
    $backup_super_admin = $_SESSION['super_admin'];

    $master_conn = getMasterConnection();
    
    // Busca dados do tenant
    $sql = "SELECT tenant_id, db_host, db_database, db_user, db_password FROM tenants WHERE id = ?";
    $stmt = $master_conn->prepare($sql);
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    $tenant_info = $stmt->get_result()->fetch_assoc();
    
    if ($tenant_info) {
        // Testa conexão com o banco do tenant
        try {
            $tenant_conn = new mysqli(
                $tenant_info['db_host'],
                $tenant_info['db_user'],
                $tenant_info['db_password'],
                $tenant_info['db_database']
            );
            $tenant_conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            header('Location: ../pages/admin/dashboard.php?erro=conexao_tenant');
            exit;
        }
        
        // --- INÍCIO DA VERIFICAÇÃO DE SEGURANÇA (DEBUG) ---
        $check_table = $tenant_conn->query("SHOW TABLES LIKE 'usuarios'");
        if ($check_table->num_rows == 0) {
            $res = $tenant_conn->query("SHOW TABLES");
            $tabelas = $res->fetch_all();
            die("<strong>Erro Fatal:</strong> A tabela 'usuarios' não foi encontrada no banco '{$tenant_info['db_database']}'.<br>
                 <strong>Tabelas existentes no banco:</strong> <pre>" . print_r($tabelas, true) . "</pre>");
        }
        // --- FIM DA VERIFICAÇÃO ---

        // Busca o proprietário do tenant para impersonar
        $sql_user = "SELECT * FROM usuarios WHERE nivel_acesso = 'proprietario' LIMIT 1";
        $user_result = $tenant_conn->query($sql_user);

        if ($proprietario = $user_result->fetch_assoc()) {
            
            // Limpa a sessão atual para evitar conflitos
            session_unset();
            
            // Restaura o backup do admin e define dados do tenant
            $_SESSION['super_admin_original'] = $backup_super_admin;
            $_SESSION['super_admin'] = $backup_super_admin; 
            
            $_SESSION['tenant_id'] = $tenant_info['tenant_id'];
            $_SESSION['tenant_id_master'] = $tenant_id;
            $_SESSION['tenant_db'] = $tenant_info;
            
            $_SESSION['usuario_logado'] = true; 
            
            $_SESSION['usuario_id']     = $proprietario['id'];
            $_SESSION['dados_usuario_id'] = $proprietario['id'];
            $_SESSION['nome']           = $proprietario['nome'];
            $_SESSION['email']          = $proprietario['email'];
            $_SESSION['nivel_acesso']   = 'proprietario';
            
            $tenant_conn->close();
            
            session_write_close();
            
            header('Location: ../pages/home.php');
            exit;
        } else {
            header('Location: ../pages/admin/dashboard.php?erro=sem_proprietario');
            exit;
        }
    }
}

header('Location: ../pages/admin/dashboard.php?erro=tenant_nao_encontrado');
exit;
?>

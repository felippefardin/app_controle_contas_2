<?php
require_once '../includes/session_init.php';
include('../database.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}
require_csrf();

// Configuração do Super Admin

// Verifica se há uma sessão de super admin original guardada
if (isset($_SESSION['super_admin_original'])) {
    $admin_data = $_SESSION['super_admin_original'];

    // Verifica segurança extra: o e-mail guardado é o do super admin?
    $isMaster = ($admin_data['role'] ?? '') === 'super_admin'
        && ($admin_data['nivel_acesso'] ?? '') === 'master'
        && (int)($admin_data['is_master'] ?? 0) === 1;
    if ($isMaster) {
        
        // Limpa a sessão atual (do tenant/usuário impersonado)
        session_unset();
        
        // Restaura a sessão do super admin
        $_SESSION['super_admin'] = $admin_data;
        session_regenerate_id(true);
        
        session_write_close();

        // Redireciona de volta para o dashboard
        header('Location: ../pages/admin/dashboard.php');
        exit;
    }
}

// Se algo der errado ou não for o super admin, manda pro login ou home
session_unset();
session_destroy();
header('Location: ../pages/login.php');
exit;
?>

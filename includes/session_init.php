<?php
// includes/session_init.php
// NÃO PODE HAVER NENHUMA LINHA OU ESPAÇO ANTES DE <?php

// --- Inicia sessão global para todo o domínio ---
if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,     // Sessão dura até fechar o navegador
        'path' => '/',       // Importante: sessão visível em todas as pastas (/actions, /pages, /includes)
        'domain' => '',      // Domínio atual
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true
    ]);

    session_start();
}

// ------------------------------
// 🔐 VERIFICAÇÃO DE ACESSO
// ------------------------------
// AVISO: Não use mais $_SESSION['usuario_logado'] como array!
// Agora usamos:
//   $_SESSION['usuario_logado'] = true/false
//   $_SESSION['nivel_acesso']   = 'admin' | 'padrao' | 'master' (para contatotech)
//   $_SESSION['usuario_id']     = id do usuário dentro do tenant
// ------------------------------

/**
 * Verifica se o usuário é ADMIN (do tenant) ou MASTER (super admin)
 */
function verificar_acesso_admin() {
    if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
        header("Location: ../pages/login.php?erro=nao_logado");
        exit;
    }

    // --- MODIFICAÇÃO AQUI ---
    // Permite acesso se o nível for 'admin' (do tenant) OU 'master' (super admin)
    $nivel_acesso = $_SESSION['nivel_acesso'] ?? 'padrao';

    if ($nivel_acesso !== 'admin' && $nivel_acesso !== 'master') {
        // Se não for nenhum dos dois, nega o acesso
        header("Location: ../pages/home.php?erro=sem_permissao");
        exit;
    }
    // --- FIM DA MODIFICAÇÃO ---
}


// ------------------------------
// 🔰 CSRF PROTECTION
// ------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/**
 * Retorna o proprietario dos dados compartilhados da empresa.
 * O usuario_id continua identificando quem esta operando o sistema.
 */
if (!function_exists('get_data_owner_id')) {
    function get_data_owner_id(): int
    {
        return (int) (
            $_SESSION['dados_usuario_id']
            ?? $_SESSION['proprietario_id_original']
            ?? $_SESSION['usuario_id']
            ?? 0
        );
    }
}
?>

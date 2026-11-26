<?php
// 1. Inclui o 'session_init.php', que configura o session_set_cookie_params()
// e inicia a sessão (a sessão do "tenant" personificado).
require_once '../includes/session_init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}
require_csrf();

// 2. Verifica se há um backup da sessão do admin (prova de que estamos no modo de personificação)
if (isset($_SESSION['super_admin_original'])) {

    // 3. Salva o backup dos dados do admin
    $admin_session = $_SESSION['super_admin_original'];

    // 4. Limpa e destrói COMPLETAMENTE a sessão atual (a sessão do tenant)
    session_unset();
    $_SESSION = [];
    session_destroy(); // Isso destrói o arquivo da sessão no servidor.

    // --- INÍCIO DA CORREÇÃO ---
    // 5. Inicia uma NOVA sessão.
    // Após session_destroy(), é OBRIGATÓRIO iniciar uma nova sessão
    // para poder gravar os dados do admin. O 'session_init.php' no topo
    // já configurou os parâmetros (como o path '/'), então
    // este session_start() usará as configurações corretas.
    if (session_status() === PHP_SESSION_NONE) {
         session_start();
    }
    // --- FIM DA CORREÇÃO ---

    // 6. Restaura a sessão do super_admin na NOVA sessão
    $_SESSION['super_admin'] = $admin_session;
    session_regenerate_id(true);
    $_SESSION['session_regenerated_at'] = time();

    // 7. Gera um novo token CSRF para a sessão de admin
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // 8. Salva a sessão e redireciona para o dashboard do admin
    session_write_close();
    header('Location: ../pages/admin/dashboard.php');
    exit;
}

// Se não houver backup (acesso direto ou sessão perdida), manda para o login
session_write_close();
header('Location: ../pages/login.php');
exit;
?>

<?php
// includes/session_init.php
// NÃO PODE HAVER NENHUMA LINHA OU ESPAÇO ANTES DE <?php

// Todo horário exibido e registrado pela aplicação usa o horário oficial de Brasília.
date_default_timezone_set('America/Sao_Paulo');

// --- Inicia sessão global para todo o domínio ---
if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.gc_maxlifetime', (string) max(2400, (int) ini_get('session.gc_maxlifetime')));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,     // Sessão dura até fechar o navegador
        'path' => '/',       // Importante: sessão visível em todas as pastas (/actions, /pages, /includes)
        'domain' => '',      // Domínio atual
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Páginas que usam sessão podem conter dados privados. Impede que o navegador
// reutilize uma cópia delas pelo botão Voltar depois que o usuário fizer logout.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}


const SESSION_IDLE_SECONDS = 2400;

$now = time();
$isActivityRequest = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'atividade_sessao.php';
if (is_authenticated()) {
    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? $now);
    if ($now - $lastActivity >= SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $params['expires'] = $now - 3600;
            unset($params['lifetime']);
            setcookie(session_name(), '', $params);
        }
        session_destroy();

        if (!$isActivityRequest && PHP_SAPI !== 'cli') {
            $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
            if (str_contains($accept, 'text/html')) {
                header('Location: ' . session_idle_root() . 'pages/login.php?erro=inatividade', true, 303);
            } else {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'msg' => 'Sessão encerrada por inatividade.']);
            }
            exit;
        }
    } else {
        $_SESSION['last_activity_at'] = $lastActivity;
        if (!$isActivityRequest && $now - (int) ($_SESSION['session_regenerated_at'] ?? 0) > 900) {
            session_regenerate_id(true);
            $_SESSION['session_regenerated_at'] = $now;
        }
    }
}

function session_idle_root(): string
{
    $project = str_replace('\\', '/', dirname(__DIR__)) . '/';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (strncasecmp($script, $project, strlen($project)) !== 0) {
        return '';
    }

    return str_repeat('../', substr_count(substr($script, strlen($project)), '/'));
}

function session_idle_script(): void
{
    if (!is_authenticated()) {
        return;
    }

    $path = session_idle_root();
    $remaining = max(0, SESSION_IDLE_SECONDS - (time() - (int) $_SESSION['last_activity_at']));
    printf(
        '<script defer src="%sassets/js/session-idle.js" data-remaining="%d" data-csrf="%s"></script>',
        $path,
        $remaining,
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
    );
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
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'] ?? '';

function csrf_token(): string
{
    return (string) ($_SESSION['csrf_token'] ?? '');
}

function csrf_is_valid(?string $token): bool
{
    $sessionToken = csrf_token();
    return $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
}

function require_csrf(bool $json = false): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (csrf_is_valid(is_string($token) ? $token : null)) return;
    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Sua sessão expirou. Atualize a página e tente novamente.']);
    } else {
        http_response_code(403);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
    exit;
}

function is_authenticated(): bool
{
    return ($_SESSION['usuario_logado'] ?? false) === true || !empty($_SESSION['super_admin']);
}

function is_super_admin(): bool
{
    return !empty($_SESSION['super_admin']) && is_array($_SESSION['super_admin']);
}

function require_authenticated(bool $json = false): void
{
    if (is_authenticated()) return;
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Autenticação necessária.']);
    } else {
        header('Location: ../pages/login.php?erro=nao_logado');
    }
    exit;
}

function require_super_admin(bool $json = false): void
{
    if (is_super_admin()) return;
    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Acesso administrativo negado.']);
    } else {
        http_response_code(403);
        exit('Acesso negado.');
    }
    exit;
}

function is_tenant_admin(): bool
{
    return in_array((string) ($_SESSION['nivel_acesso'] ?? ''), ['admin', 'proprietario', 'master'], true);
}

function require_tenant_admin(bool $json = false): void
{
    require_authenticated($json);
    if (is_tenant_admin() || is_super_admin()) return;
    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Você não possui permissão administrativa.']);
    } else {
        http_response_code(403);
        exit('Acesso negado.');
    }
    exit;
}

function has_page_permission(string $permission): bool
{
    if (is_tenant_admin() || is_super_admin()) return true;
    $permissions = $_SESSION['permissoes'] ?? [];
    if (!is_array($permissions)) $permissions = [];
    $aliases = ['lembrete.php' => 'lembretes.php', 'lembretes.php' => 'lembrete.php'];
    return in_array($permission, $permissions, true)
        || (isset($aliases[$permission]) && in_array($aliases[$permission], $permissions, true));
}

function require_page_permission(string $permission, bool $json = false): void
{
    require_authenticated($json);
    if (has_page_permission($permission)) return;
    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Você não possui permissão para esta função.']);
    } else {
        http_response_code(403);
        exit('Você não possui permissão para esta função.');
    }
    exit;
}

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

// Controle de acesso no servidor. Esconder um menu não é autorização.
$pagePermissions = [
    'contas_pagar.php' => 'contas_pagar.php',
    'contas_pagar_baixadas.php' => 'contas_pagar_baixadas.php',
    'contas_receber.php' => 'contas_receber.php',
    'contas_receber_baixadas.php' => 'contas_receber_baixadas.php',
    'lembrete.php' => 'lembrete.php',
    'lancamento_caixa.php' => 'lancamento_caixa.php',
    'vendas_periodo.php' => 'vendas_periodo.php',
    'controle_estoque.php' => 'controle_estoque.php',
    'vendas.php' => 'vendas.php',
    'compras.php' => 'compras.php',
    'cadastrar_pessoa_fornecedor.php' => 'cadastrar_pessoa_fornecedor.php',
    'banco_cadastro.php' => 'banco_cadastro.php',
    'categorias.php' => 'categorias.php',
    'relatorios.php' => 'relatorios.php',
    'configuracao_fiscal.php' => 'configuracao_fiscal.php',
];

$actionPermissions = [
    'add_conta_pagar.php' => 'contas_pagar.php',
    'editar_conta_pagar.php' => 'contas_pagar.php',
    'excluir_conta_pagar.php' => 'contas_pagar.php',
    'baixar_conta.php' => 'contas_pagar.php',
    'estornar_conta_pagar.php' => 'contas_pagar_baixadas.php',
    'repetir_conta_pagar.php' => 'contas_pagar.php',
    'add_conta_receber.php' => 'contas_receber.php',
    'editar_conta_receber.php' => 'contas_receber.php',
    'excluir_conta_receber.php' => 'contas_receber.php',
    'baixar_conta_receber.php' => 'contas_receber.php',
    'estornar_conta_receber.php' => 'contas_receber_baixadas.php',
    'repetir_conta_receber.php' => 'contas_receber.php',
    'cadastrar_produto_action.php' => 'controle_estoque.php',
    'editar_produto_action.php' => 'controle_estoque.php',
    'excluir_produto_action.php' => 'controle_estoque.php',
    'registrar_venda.php' => 'vendas.php',
    'set_meta_vendas.php' => 'vendas.php',
    'emitir_nfce.php' => 'vendas.php',
    'registrar_compra.php' => 'compras.php',
    'cadastrar_pessoa_fornecedor_action.php' => 'cadastrar_pessoa_fornecedor.php',
    'editar_pessoa_fornecedor_action.php' => 'cadastrar_pessoa_fornecedor.php',
    'excluir_pessoa_fornecedor.php' => 'cadastrar_pessoa_fornecedor.php',
    'cadastrar_banco_action.php' => 'banco_cadastro.php',
    'editar_banco_action.php' => 'banco_cadastro.php',
    'excluir_banco.php' => 'banco_cadastro.php',
    'salvar_categoria.php' => 'categorias.php',
    'excluir_categoria.php' => 'categorias.php',
    'add_caixa_diario.php' => 'lancamento_caixa.php',
    'excluir_caixa_diario.php' => 'lancamento_caixa.php',
    'salvar_lembrete.php' => 'lembrete.php',
    'excluir_lembrete.php' => 'lembrete.php',
    'salvar_configuracao_fiscal.php' => 'configuracao_fiscal.php',
];

$executingFile = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
$executingDirectory = basename(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));

// O direito ao módulo fiscal vem do plano atual da empresa.
if (($executingDirectory === 'pages' && $executingFile === 'configuracao_fiscal.php')
    || ($executingDirectory === 'actions' && in_array($executingFile, ['salvar_configuracao_fiscal.php', 'emitir_nfce.php', 'testar_emissao_mock.php'], true))) {
    require_once __DIR__ . '/../database.php';
    $fiscalTenantId = (string) ($_SESSION['tenant_id'] ?? '');
    $fiscalTenant = $fiscalTenantId === '' ? null : getTenantById($fiscalTenantId, getMasterConnection());
    if (!in_array($fiscalTenant['plano_atual'] ?? null, ['plus', 'essencial'], true)) {
        http_response_code(403);
        if ($executingDirectory === 'actions' && $executingFile !== 'salvar_configuracao_fiscal.php') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'O módulo fiscal está disponível nos planos Plus e Essencial.']);
        } else {
            echo 'O módulo fiscal está disponível nos planos Plus e Essencial.';
        }
        exit;
    }
}

if ($executingDirectory === 'pages' && isset($pagePermissions[$executingFile])) {
    require_page_permission($pagePermissions[$executingFile]);
} elseif ($executingDirectory === 'actions' && isset($actionPermissions[$executingFile])) {
    $expectsJson = str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    require_page_permission($actionPermissions[$executingFile], $expectsJson);
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        require_csrf($expectsJson);
    }
}
?>

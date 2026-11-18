<?php
require_once '../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once '../includes/utils.php'; // Importa o sistema de Flash Messages

// Captura dados do formulário
$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

// --- NOVA FUNÇÃO: Redireciona salvando o input antigo ---
function redirect_with_error($msg, $email_input) {
    set_flash_message('danger', $msg);
    $_SESSION['old_email'] = $email_input; // Salva o e-mail digitado na sessão
    header("Location: ../pages/login.php");
    exit;
}

function register_login_failure(string $email): void {
    $key = hash('sha256', strtolower(trim($email)));
    $attempt = $_SESSION['login_attempt'] ?? ['key' => $key, 'count' => 0, 'blocked_until' => 0];
    if (($attempt['key'] ?? '') !== $key) $attempt = ['key' => $key, 'count' => 0, 'blocked_until' => 0];
    $attempt['count'] = (int)$attempt['count'] + 1;
    if ($attempt['count'] >= 5) $attempt['blocked_until'] = time() + min(300, 30 * ($attempt['count'] - 4));
    $_SESSION['login_attempt'] = $attempt;
}
// --------------------------------------------------------

// Validação básica
if (!$email || !$senha) {
    // Se faltar dados, redireciona mantendo o email (se tiver sido digitado)
    redirect_with_error('Preencha o e-mail e a senha para entrar.', $email);
}

$loginAttempt = $_SESSION['login_attempt'] ?? null;
if (is_array($loginAttempt)
    && ($loginAttempt['key'] ?? '') === hash('sha256', strtolower($email))
    && (int)($loginAttempt['blocked_until'] ?? 0) > time()) {
    redirect_with_error('Muitas tentativas. Aguarde alguns instantes e tente novamente.', $email);
}

try {
    $connMaster = getMasterConnection();

    // 1. Busca Usuário no Master
    $stmt = $connMaster->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $userMaster = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verifica se usuário existe
    if (!$userMaster) {
        $connMaster->close();
        register_login_failure($email);
        redirect_with_error('E-mail ou senha inválidos.', $email);
    }

    // Verifica a senha
    if (!password_verify($senha, $userMaster['senha'])) {
        $connMaster->close();
        register_login_failure($email);
        redirect_with_error('E-mail ou senha inválidos.', $email);
    }

    if (($userMaster['status'] ?? 'ativo') !== 'ativo') {
        $connMaster->close();
        redirect_with_error('Esta conta está inativa. Se ela foi pausada temporariamente, use “Reativar Minha Conta” nesta tela.', $email);
    }

    unset($_SESSION['login_attempt']);

    // ====================================================
    // LOGIN BEM SUCEDIDO
    // ====================================================

    // Limpa o old_email pois o login deu certo
    unset($_SESSION['old_email']);

    // 2. Lógica Super Admin
    $isSuperAdmin = ($userMaster['role'] ?? '') === 'super_admin'
        && ($userMaster['nivel_acesso'] ?? '') === 'master'
        && (int)($userMaster['is_master'] ?? 0) === 1;
    if ($isSuperAdmin) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated_at'] = time();
        $_SESSION['super_admin'] = $userMaster;
        
        // Gatilho para mensagem home (caso o admin acesse a home.php)
        $_SESSION['acabou_de_logar'] = true;

        $connMaster->close();
        header('Location: ../pages/admin/dashboard.php');
        exit;
    }

    // 3. Recupera Tenant vinculado
    $tenantId = $userMaster['tenant_id'] ?? null;
    $tenant = null;

    if ($tenantId) {
        $stmt = $connMaster->prepare("SELECT * FROM tenants WHERE tenant_id = ? LIMIT 1");
        $stmt->bind_param("s", $tenantId);
        $stmt->execute();
        $tenant = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($tenant) {
            $_SESSION['plano'] = $tenant['plano_atual'] ?? 'basico';
        }
    }

    // 4. Validação de Status e Trial do Tenant
    if ($tenant) {
        $status = $tenant['status_assinatura'] ?? 'padrao';
        $is_trial = ($status === 'trial');
        $expired = false;

        // Verifica validade do Trial
        if ($is_trial) {
            $dias_teste = ($tenant['plano_atual'] === 'essencial') ? 30 : 15;
            $data_inicio = new DateTime($tenant['data_inicio_teste'] ?? $tenant['data_criacao']);
            $data_fim = clone $data_inicio;
            $data_fim->modify("+$dias_teste days");
            
            if (new DateTime() > $data_fim) {
                $expired = true;
                $connMaster->query("UPDATE tenants SET status_assinatura = 'trial_expired' WHERE id = " . $tenant['id']);
            }
        }

        $bloqueados = ['vencido', 'cancelado', 'trial_expired', 'pendente', 'desativado_temporario', 'exclusao_em_processamento'];

        // Se estiver bloqueado, redireciona para página de assinatura
        if ($expired || in_array($status, $bloqueados)) {
            $_SESSION['usuario_id']      = $userMaster['id'];
            $_SESSION['tenant_id']       = $tenantId;
            $_SESSION['email']           = $userMaster['email'];
            $_SESSION['usuario_logado']  = true;
            $_SESSION['nivel_acesso']    = 'proprietario';
            $_SESSION['erro_assinatura'] = "Seu período gratuito acabou ou sua assinatura está pendente.";
            
            // Mesmo bloqueado, marcamos que logou (para eventuais avisos na tela de bloqueio, se houver)
            $_SESSION['acabou_de_logar'] = true; 

            $connMaster->close();
            header("Location: ../pages/assinar.php");
            exit;
        }
    }

    $connMaster->close();

    // 5. Configura Sessão do Tenant (banco específico)
    $idUsuarioTenant = null;
    $idProprietarioDados = null;
    $nivelAcessoTenant = 'padrao';
    $permissoesTenant = [];

    if ($tenant) {
        $_SESSION['tenant_db'] = [
            "db_host"     => $tenant['db_host'],
            "db_user"     => $tenant['db_user'],
            "db_password" => $tenant['db_password'],
            "db_database" => $tenant['db_database']
        ];

        $tenantConn = getTenantConnection();
        if ($tenantConn) {
            $stmtTenant = $tenantConn->prepare("SELECT * FROM usuarios WHERE email = ? AND deleted_at IS NULL LIMIT 1");
            $stmtTenant->bind_param("s", $email);
            $stmtTenant->execute();
            $userTenant = $stmtTenant->get_result()->fetch_assoc();
            $stmtTenant->close();

            if ($userTenant) {
                if (($userTenant['status'] ?? 'ativo') !== 'ativo') {
                    $tenantConn->close();
                    redirect_with_error('Esta conta está inativa. Entre em contato com o administrador.', $email);
                }
                $idUsuarioTenant = $userTenant['id'];
                $nivelAcessoTenant = $userTenant['nivel_acesso'];
                $decodedPermissions = json_decode((string)($userTenant['permissoes'] ?? '[]'), true);
                $permissoesTenant = is_array($decodedPermissions) ? $decodedPermissions : [];
            }

            $ownerResult = $tenantConn->query("SELECT id FROM usuarios WHERE deleted_at IS NULL AND (is_master = 1 OR nivel_acesso IN ('proprietario','master')) ORDER BY is_master DESC, id ASC LIMIT 1");
            if ($ownerResult && $ownerRow = $ownerResult->fetch_assoc()) {
                $idProprietarioDados = (int)$ownerRow['id'];
            }
            $tenantConn->close();
        }
    }

    // 6. Define Variáveis de Sessão Finais
    session_regenerate_id(true);
    $_SESSION['session_regenerated_at'] = time();
    $_SESSION['usuario_id']        = $idUsuarioTenant ?? $userMaster['id'];
    $_SESSION['dados_usuario_id']  = $idProprietarioDados ?? $idUsuarioTenant ?? $userMaster['id'];
    $_SESSION['usuario_id_master'] = $userMaster['id'];
    $_SESSION['nome']              = $userMaster['nome'];
    $_SESSION['email']             = $userMaster['email'];
    $_SESSION['tenant_id']         = $tenantId;
    $_SESSION['nivel_acesso']      = $nivelAcessoTenant;
    $_SESSION['permissoes']        = $permissoesTenant;
    $_SESSION['usuario_logado']    = true;

    $_SESSION['acabou_de_logar'] = true;

    header("Location: ../pages/home.php");
    exit;

} catch (Exception $e) {
    die("Erro detalhado: " . $e->getMessage());
    
    error_log("Erro Login: " . $e->getMessage());
    redirect_with_error("Erro interno no servidor. Tente novamente mais tarde.", $email);
}
?>

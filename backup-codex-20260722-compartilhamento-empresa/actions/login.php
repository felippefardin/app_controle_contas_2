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
// --------------------------------------------------------

// Validação básica
if (!$email || !$senha) {
    // Se faltar dados, redireciona mantendo o email (se tiver sido digitado)
    redirect_with_error('Preencha o e-mail e a senha para entrar.', $email);
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
        redirect_with_error('Conta não encontrada. Verifique o e-mail.', $email);
    }

    // Verifica a senha
    if (!password_verify($senha, $userMaster['senha'])) {
        $connMaster->close();
        redirect_with_error('Senha incorreta. Tente novamente.', $email);
    }

    // ====================================================
    // LOGIN BEM SUCEDIDO
    // ====================================================

    // Limpa o old_email pois o login deu certo
    unset($_SESSION['old_email']);

    // 2. Lógica Super Admin
    $emails_admin = ['contatotech.tecnologia@gmail.com', 'contatotech.tecnologia@gmail.com.br'];
    if (in_array($userMaster['email'], $emails_admin)) {
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

        $bloqueados = ['vencido', 'cancelado', 'trial_expired', 'pendente'];

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
    $nivelAcessoTenant = 'padrao';

    if ($tenant) {
        $_SESSION['tenant_db'] = [
            "db_host"     => $tenant['db_host'],
            "db_user"     => $tenant['db_user'],
            "db_password" => $tenant['db_password'],
            "db_database" => $tenant['db_database']
        ];

        $tenantConn = getTenantConnection();
        if ($tenantConn) {
            $stmtTenant = $tenantConn->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
            $stmtTenant->bind_param("s", $email);
            $stmtTenant->execute();
            $userTenant = $stmtTenant->get_result()->fetch_assoc();
            $stmtTenant->close();

            if ($userTenant) {
                $idUsuarioTenant = $userTenant['id'];
                $nivelAcessoTenant = $userTenant['nivel_acesso'];
            }
            $tenantConn->close();
        }
    }

    // 6. Define Variáveis de Sessão Finais
    $_SESSION['usuario_id']        = $idUsuarioTenant ?? $userMaster['id'];
    $_SESSION['usuario_id_master'] = $userMaster['id'];
    $_SESSION['nome']              = $userMaster['nome'];
    $_SESSION['email']             = $userMaster['email'];
    $_SESSION['tenant_id']         = $tenantId;
    $_SESSION['nivel_acesso']      = $nivelAcessoTenant;
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
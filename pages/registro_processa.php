<?php
// pages/registro_processa.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database.php'; 
require_once __DIR__ . '/../includes/utils.php'; // Importa sistema Flash Message

use Dotenv\Dotenv;
use Dompdf\Dompdf; // Importando Dompdf

// --- 1. CONFIGURAÇÃO TURNSTILE (CAPTCHA ANTI-ROBÔ) ---
// Substitua pela sua SECRET KEY do Cloudflare Turnstile
define('TURNSTILE_SECRET_KEY', '0x4AAAAAACDq-BZPjGJTF8DhHDOEQ6NrWBw');

// --- CONEXÃO COM BANCO (Necessária para validação AJAX e Registro) ---
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$conn = getMasterConnection();

// --- [NOVO] VERIFICAÇÃO AJAX DE DUPLICIDADE (CHAMADO PELO JS DO REGISTRO.PHP) ---
if (isset($_POST['ajax_check']) && $_POST['ajax_check'] === '1') {
    // Limpa qualquer buffer de saída para garantir que apenas o JSON seja retornado
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    $email_check = trim($_POST['email'] ?? '');
    $doc_check = trim($_POST['documento'] ?? '');
    
    // Verifica E-mail
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email_check);
    $stmt->execute();
    $email_exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    // Verifica Documento
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE documento = ? LIMIT 1");
    $stmt->bind_param("s", $doc_check);
    $stmt->execute();
    $doc_exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'email_exists' => $email_exists,
        'doc_exists' => $doc_exists
    ]);
    exit; // Encerra execução aqui para não rodar o resto do script
}
// -----------------------------------------------------------------------------

function verificarTurnstile($token) {
    if (!$token) return false;
    
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $data = [
        'secret' => TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);

    return $response->success;
}

// Captura dados Pessoais do POST normal
$nome        = trim($_POST['nome'] ?? '');
$email       = trim($_POST['email'] ?? '');
$senha       = trim($_POST['senha'] ?? '');
$tipo_pessoa = trim($_POST['tipo_pessoa'] ?? 'fisica');
$documento   = trim($_POST['documento'] ?? '');
$telefone    = trim($_POST['telefone'] ?? '');
$plano_post  = trim($_POST['plano'] ?? 'basico');
$aceite_lgpd = isset($_POST['aceite_lgpd']) ? trim($_POST['aceite_lgpd']) : '0';
$turnstileToken = $_POST['cf-turnstile-response'] ?? ''; 

$cupom_codigo = isset($_POST['cupom']) && !empty($_POST['cupom']) ? strtoupper(trim($_POST['cupom'])) : null;
$codigo_indicacao_recebido = isset($_POST['codigo_indicacao']) && !empty($_POST['codigo_indicacao']) ? strtoupper(trim($_POST['codigo_indicacao'])) : null;

// --- ARMAZENAMENTO DE DADOS ANTIGOS EM CASO DE ERRO ---
$form_data = [
    'nome' => $nome,
    'email' => $email,
    'tipo_pessoa' => $tipo_pessoa,
    'documento' => $documento,
    'telefone' => $telefone,
    'plano' => $plano_post,
    'cupom' => $_POST['cupom'] ?? '',
    'codigo_indicacao' => $_POST['codigo_indicacao'] ?? ''
];

function return_error($msg, $data) {
    global $conn;
    if(isset($conn)) $conn->close();
    $_SESSION['form_data'] = $data; 
    set_flash_message('danger', $msg);
    header("Location: ../pages/registro.php");
    exit;
}

// 2. Validação Anti-Robô (Turnstile)
if (!verificarTurnstile($turnstileToken)) {
    return_error("Verificação de segurança falhou (Captcha). Por favor, tente novamente.", $form_data);
}

// Validação do Aceite LGPD
if ($aceite_lgpd !== '1') {
    return_error("É obrigatório aceitar os Termos de Uso e Política de Privacidade (LGPD) para se registrar.", $form_data);
}

// Regras de Plano
if ($plano_post === 'essencial') {
    $dias_teste = 30;
    $plano_escolhido = 'essencial';
} elseif ($plano_post === 'plus') {
    $dias_teste = 15;
    $plano_escolhido = 'plus';
} else {
    $dias_teste = 15;
    $plano_escolhido = 'basico';
}

if (!$nome || !$email || !$senha || !$documento) {
    return_error("Preencha todos os campos obrigatórios.", $form_data);
}

// 3. Validação de Duplicidade (Server-side final check)
$stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE email = ? OR documento = ? LIMIT 1");
$stmtCheck->bind_param("ss", $email, $documento);
$stmtCheck->execute();
if ($stmtCheck->get_result()->num_rows > 0) {
    $stmtCheck->close();
    return_error("Este E-mail ou CPF/CNPJ já está cadastrado no sistema.", $form_data);
}
$stmtCheck->close();

// Gerar Código Único de Indicação
$codigo_novo_usuario = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$checkCode = $conn->prepare("SELECT id FROM usuarios WHERE codigo_indicacao = ?");
$checkCode->bind_param("s", $codigo_novo_usuario);
$checkCode->execute();
while ($checkCode->get_result()->num_rows > 0) {
    $codigo_novo_usuario = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $checkCode->bind_param("s", $codigo_novo_usuario);
    $checkCode->execute();
}
$checkCode->close();

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);
$conn->begin_transaction();

try {
    // 1. Insert Master User
    $stmtUser = $conn->prepare("
        INSERT INTO usuarios (nome, email, senha, tipo_pessoa, documento, telefone, nivel_acesso, perfil, tipo, status, is_master, codigo_indicacao)
        VALUES (?, ?, ?, ?, ?, ?, 'proprietario', 'admin', 'admin', 'ativo', 1, ?)
    ");
    $stmtUser->bind_param("sssssss", $nome, $email, $senha_hash, $tipo_pessoa, $documento, $telefone, $codigo_novo_usuario);
    $stmtUser->execute();
    $new_usuario_id = $conn->insert_id;
    $stmtUser->close();

    // ---------------------------------------------------------
    // GERAÇÃO DO PDF DE CONSENTIMENTO LGPD
    // ---------------------------------------------------------
    if ($new_usuario_id) {
        $ipUsuario = $_SERVER['REMOTE_ADDR'];
        $dataAceite = date('d/m/Y H:i:s');
        
        $htmlContrato = "
        <div style='font-family: Arial, sans-serif; font-size: 12px;'>
            <h1 style='text-align:center;'>Termo de Consentimento e Privacidade (LGPD)</h1>
            <p>Este documento certifica que o usuário abaixo identificado leu e aceitou os termos de uso e a política de privacidade da plataforma <strong>App Controle Contas</strong>.</p>
            
            <table style='width: 100%; border: 1px solid #ddd; border-collapse: collapse; margin-top: 20px;'>
                <tr><td style='padding: 8px; background: #f9f9f9;'><strong>Nome do Titular:</strong></td><td style='padding: 8px;'>$nome</td></tr>
                <tr><td style='padding: 8px; background: #f9f9f9;'><strong>CPF/CNPJ:</strong></td><td style='padding: 8px;'>$documento</td></tr>
                <tr><td style='padding: 8px; background: #f9f9f9;'><strong>E-mail:</strong></td><td style='padding: 8px;'>$email</td></tr>
                <tr><td style='padding: 8px; background: #f9f9f9;'><strong>Data do Aceite:</strong></td><td style='padding: 8px;'>$dataAceite</td></tr>
                <tr><td style='padding: 8px; background: #f9f9f9;'><strong>IP de Origem:</strong></td><td style='padding: 8px;'>$ipUsuario</td></tr>
            </table>

            <br>
            <h3>Direitos e Tratamento de Dados (Lei nº 13.709/2018)</h3>
            <p>1. O Titular autoriza o tratamento dos dados pessoais inseridos na plataforma para fins exclusivos de gestão financeira e operacionalização do sistema.</p>
            <p>2. A plataforma se compromete a manter a confidencialidade e segurança dos dados, não os compartilhando com terceiros não autorizados.</p>
            <p>3. O Titular pode solicitar a qualquer momento a exclusão ou portabilidade de seus dados através do suporte.</p>
            <p>4. Em caso de exclusão da conta, este documento de comprovação de consentimento será mantido em arquivo seguro por um período de 5 (cinco) anos para fins de auditoria legal, sendo excluído automaticamente após este prazo.</p>
            
            <br><br><br>
            <p style='text-align: center; color: #888;'>Assinado digitalmente via aceite eletrônico.</p>
        </div>
        ";

        $dompdf = new Dompdf();
        $dompdf->loadHtml($htmlContrato);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $outputPdf = $dompdf->output();

        $dirDestino = __DIR__ . '/../assets/uploads/contratos_lgpd/';
        if (!is_dir($dirDestino)) {
            mkdir($dirDestino, 0755, true);
        }

        $nomeArquivoPdf = 'lgpd_' . $new_usuario_id . '_' . time() . '.pdf';
        $caminhoCompletoPdf = $dirDestino . $nomeArquivoPdf;
        $caminhoRelativoPdf = 'assets/uploads/contratos_lgpd/' . $nomeArquivoPdf;

        file_put_contents($caminhoCompletoPdf, $outputPdf);

        $stmtLgpd = $conn->prepare("INSERT INTO termos_consentimento (usuario_id, caminho_arquivo, ip_usuario) VALUES (?, ?, ?)");
        $stmtLgpd->bind_param("iss", $new_usuario_id, $caminhoRelativoPdf, $ipUsuario);
        $stmtLgpd->execute();
        $stmtLgpd->close();
    }

    // 2. Tenant Setup
    $tenantId = 'T' . substr(md5(uniqid($email, true)), 0, 32);
    $dbHost     = $_ENV['DB_HOST'] ?? 'localhost';
    $dbDatabase = 'tenant_db_' . $new_usuario_id;
    $dbUser     = 'dbuser_' . $new_usuario_id;
    $dbPassword = bin2hex(random_bytes(16));
    $nome_empresa = $nome; 

    $stmtTenant = $conn->prepare("
        INSERT INTO tenants (
            tenant_id, usuario_id, nome, nome_empresa, admin_email, senha, 
            status_assinatura, data_inicio_teste, plano_atual, 
            db_host, db_database, db_user, db_password,
            cupom_registro, msg_cupom_visto, msg_indicacao_visto
        ) VALUES (?, ?, ?, ?, ?, ?, 'trial', NOW(), ?, ?, ?, ?, ?, ?, 0, 0)
    ");

    $stmtTenant->bind_param(
        "sissssssssss", 
        $tenantId, $new_usuario_id, $nome, $nome_empresa, $email, $senha_hash,
        $plano_escolhido,
        $dbHost, $dbDatabase, $dbUser, $dbPassword,
        $cupom_codigo
    );
    $stmtTenant->execute();
    $stmtTenant->close();

    // 3. Processar Indicação
    if ($codigo_indicacao_recebido) {
        $sqlInd = "SELECT id FROM usuarios WHERE codigo_indicacao = ? LIMIT 1";
        $stmtInd = $conn->prepare($sqlInd);
        $stmtInd->bind_param("s", $codigo_indicacao_recebido);
        $stmtInd->execute();
        $resInd = $stmtInd->get_result();
        
        if ($resInd->num_rows > 0) {
            $indicador = $resInd->fetch_assoc();
            $id_indicador = $indicador['id'];
            
            if ($id_indicador != $new_usuario_id) {
                $stmtInsInd = $conn->prepare("INSERT INTO indicacoes (id_indicador, id_indicado) VALUES (?, ?)");
                $stmtInsInd->bind_param("ii", $id_indicador, $new_usuario_id);
                $stmtInsInd->execute();
                $stmtInsInd->close();
            }
        }
        $stmtInd->close();
    }

    // 4. Update User Tenant ID
    $conn->query("UPDATE usuarios SET tenant_id = '$tenantId' WHERE id = $new_usuario_id");

    // 5. Create Tenant DB & Schema
    $rootConn = new mysqli($dbHost, $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    $safeDbName = $rootConn->real_escape_string($dbDatabase);
    $safeDbUser = $rootConn->real_escape_string($dbUser);
    $safeDbPass = $rootConn->real_escape_string($dbPassword);

 // Cria o banco de dados se não existir
    $rootConn->query("CREATE DATABASE IF NOT EXISTS `$safeDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Cria o usuário apenas se ele não existir (evita o erro fatal)
    $rootConn->query("CREATE USER IF NOT EXISTS '$safeDbUser'@'localhost' IDENTIFIED BY '$safeDbPass'");
    
    // Atualiza a senha para garantir que o sistema tem a senha correta gerada agora
    $rootConn->query("ALTER USER '$safeDbUser'@'localhost' IDENTIFIED BY '$safeDbPass'");
    
    // Dá as permissões
    $rootConn->query("GRANT ALL PRIVILEGES ON `$safeDbName`.* TO '$safeDbUser'@'localhost'");
    $rootConn->query("FLUSH PRIVILEGES");

    $schemaPath = __DIR__ . '/../schema.sql';
    if (file_exists($schemaPath)) {
        $tenantConn = new mysqli($dbHost, $dbUser, $dbPassword, $dbDatabase);
        $schemaSql = file_get_contents($schemaPath);
        if ($tenantConn->multi_query($schemaSql)) {
            do { if ($res = $tenantConn->store_result()) $res->free(); } while ($tenantConn->more_results() && $tenantConn->next_result());
        }
        
        $stmtTI = $tenantConn->prepare("INSERT INTO usuarios (nome, email, senha, tipo_pessoa, documento, telefone, nivel_acesso, perfil, status, is_master, tenant_id) VALUES (?, ?, ?, ?, ?, ?, 'proprietario', 'admin', 'ativo', 1, ?)");
        $stmtTI->bind_param("sssssss", $nome, $email, $senha_hash, $tipo_pessoa, $documento, $telefone, $tenantId);
        $stmtTI->execute();
        $stmtTI->close();
        $tenantConn->close();
    }
    $rootConn->close();

    $conn->commit();

    unset($_SESSION['form_data']);

    set_flash_message('success', "Cadastro realizado com sucesso!<br>Termo LGPD gerado.<br>Teste Grátis de $dias_teste dias ativado.");
    header("Location: ../pages/login.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("Erro técnico: " . $e->getMessage() . " - Arquivo: " . $e->getFile() . " Linha: " . $e->getLine());
    return_error("Erro ao registrar: " . $e->getMessage(), $form_data);
}
?>
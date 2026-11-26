<?php
// 1. Iniciar a sessão
require_once '../includes/session_init.php'; 

// 2. Carregar o banco de dados
require_once '../database.php';
require_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

// 3. Define o fuso horário
date_default_timezone_set('America/Sao_Paulo');

// 4. Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 5. VERIFICAR A SESSÃO E DADOS BÁSICOS
// Verifica se o login é válido (boolean) e se temos o ID
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true || !isset($_SESSION['usuario_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

// ✅ CORREÇÃO CRÍTICA 1: PEGAR O E-MAIL DO GET (URL)
// Isso resolve o erro "Invalid address" pois garante que o e-mail venha do clique do botão
$email_usuario = null;
$id_usuario = $_SESSION['usuario_id']; // ID do usuário logado

// 6. Pegar a conexão
$conn = getTenantConnection(); 
if ($conn === null) {
    $_SESSION['erro_selecao'] = 'Falha de conexão com o Banco de Dados.';
    header('Location: ../pages/selecionar_usuario.php');
    exit;
}

// 7. PEGAR DADOS DO TENANT (Nome do Banco)
// Verifica se existe na sessão, senão tenta recuperar do contexto atual
$tenant_db_name = $_SESSION['tenant_db_name'] ?? $_SESSION['tenant_db']['db_database'] ?? null;

if (empty($tenant_db_name)) {
     $_SESSION['erro_selecao'] = 'Erro: Nome do banco de dados não encontrado na sessão.';
     header('Location: ../pages/selecionar_usuario.php');
     exit;
}

// 8. BUSCAR NOME DO USUÁRIO (Para o e-mail ficar bonito)
$nome_usuario = 'Usuário'; // Valor padrão
$stmt_nome = $conn->prepare("SELECT nome,email FROM usuarios WHERE id = ?");
$stmt_nome->bind_param("i", $id_usuario);
if ($stmt_nome->execute()) {
    $res_nome = $stmt_nome->get_result();
    if ($row = $res_nome->fetch_assoc()) {
        $nome_usuario = $row['nome'];
        $email_usuario = $row['email'];
    }
}
$stmt_nome->close();
if (empty($email_usuario)) {
    $_SESSION['erro_selecao'] = 'E-mail não identificado para envio.';
    header('Location: ../pages/selecionar_usuario.php');
    exit;
}

// 9. Gerar e salvar o token
$token = bin2hex(random_bytes(32));
$expiracao = date('Y-m-d H:i:s', strtotime('+1 hour')); 

$stmt = $conn->prepare("UPDATE usuarios SET token_reset = ?, token_expira_em = ? WHERE id = ?");
$stmt->bind_param("ssi", $token, $expiracao, $id_usuario);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    $_SESSION['erro_selecao'] = 'Erro ao gerar token de recuperação.';
    header('Location: ../pages/selecionar_usuario.php');
    exit;
}
$stmt->close();

// 10. Enviar o e-mail
$mail = new PHPMailer(true);

try {
    // Configurações do Servidor (Do .env ou config)
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME'];
    $mail->Password   = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION']; 
    $mail->Port       = (int)$_ENV['MAIL_PORT']; 

    // Remetente e Destinatário
    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($email_usuario, $nome_usuario); // ✅ Agora $email_usuario não está vazio
    $mail->CharSet = 'UTF-8';

    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = 'Redefinição de senha (Solicitado via Perfil)';
    
    $appUrl = rtrim($_ENV['APP_URL'], '/'); 
    
    // Payload seguro
    $payload = base64_encode(json_encode([
        'token' => $token,
        'tenant' => $tenant_db_name
    ]));
    
    $link = $appUrl . "/pages/resetar_senha_usuario.php?payload=" . urlencode($payload);
    
    $mail->Body = "Olá <strong>$nome_usuario</strong>,<br><br>
                   Você solicitou a redefinição de sua senha através da tela de seleção de usuários.<br>
                   Clique no link abaixo para definir uma nova senha:<br><br>
                   <a href='$link' style='background-color:#00bfff; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>Redefinir Minha Senha</a><br><br>
                   Ou copie o link: $link<br><br>
                   Este link expira em 1 hora.";

    $mail->send();
    $conn->close();
    
    // ✅ CORREÇÃO CRÍTICA 3: REDIRECIONAR COM STATUS PARA O ALERTA FLUTUANTE
    // Passamos 'status=email_enviado' e o email para o script JS na outra página capturar
    header('Location: ../pages/selecionar_usuario.php?status=email_enviado&email=' . urlencode($email_usuario));
    exit;

} catch (Exception $e) {
    $conn->close();
    $_SESSION['erro_selecao'] = 'Erro ao enviar e-mail: ' . $mail->ErrorInfo;
    header('Location: ../pages/selecionar_usuario.php');
    exit;
}
?>

<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../includes/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'msg' => 'Método não permitido.']);
    exit;
}

require_csrf(true);

$lastRequestAt = (int) ($_SESSION['last_external_support_at'] ?? 0);
if ($lastRequestAt > 0 && (time() - $lastRequestAt) < 30) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'msg' => 'Aguarde alguns segundos antes de enviar outra mensagem.']);
    exit;
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));
$emailUsuario = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));

if ($nome === '') $nome = 'Usuário do sistema';
if (mb_strlen($nome) > 100 || !filter_var($emailUsuario, FILTER_VALIDATE_EMAIL) || mb_strlen($emailUsuario) > 190) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'Revise o nome e informe um e-mail válido.']);
    exit;
}
if ($whatsapp !== '') {
    $telefoneNumeros = preg_replace('/\D+/', '', $whatsapp);
    if (strlen($telefoneNumeros) < 10 || strlen($telefoneNumeros) > 15) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'msg' => 'Informe um WhatsApp válido com DDD ou deixe o campo vazio.']);
        exit;
    }
}
if (mb_strlen($mensagem) < 10 || mb_strlen($mensagem) > 3000) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'A mensagem deve ter entre 10 e 3.000 caracteres.']);
    exit;
}

$conn = null;
$supportId = 0;
$protocolo = 'SUP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
$canalPreferido = $whatsapp !== '' ? 'whatsapp' : 'email';
$anonimo = 0;

try {
    $conn = getMasterConnection();
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO suporte_login (protocolo, nome, whatsapp, email, descricao, anonimo, canal_preferido, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')");
    $stmt->bind_param('sssssis', $protocolo, $nome, $whatsapp, $emailUsuario, $mensagem, $anonimo, $canalPreferido);
    $stmt->execute();
    $supportId = (int) $stmt->insert_id;
    $stmt->close();

    $historico = 'Chamado aberto pela página pública de suporte.';
    $stmtHistorico = $conn->prepare("INSERT INTO suporte_historico (suporte_id, mensagem, tipo) VALUES (?, ?, 'sistema')");
    $stmtHistorico->bind_param('is', $supportId, $historico);
    $stmtHistorico->execute();
    $stmtHistorico->close();
    $conn->commit();
    $_SESSION['last_external_support_at'] = time();
} catch (Throwable $e) {
    if ($conn instanceof mysqli) $conn->rollback();
    error_log('Falha ao registrar suporte: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'Não foi possível registrar o chamado. Tente novamente.']);
    exit;
}

$emailAvisoEnviado = false;
$erroEmail = null;
$mail = null;
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['MAIL_USERNAME'];
    $mail->Password = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
    $mail->Port = (int) $_ENV['MAIL_PORT'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress('contatotech.tecnologia@gmail.com', 'Contato Tech Suporte');
    $mail->addReplyTo($emailUsuario, $nome);

    $nomeHtml = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $whatsappHtml = htmlspecialchars($whatsapp !== '' ? $whatsapp : 'Não informado', ENT_QUOTES, 'UTF-8');
    $emailHtml = htmlspecialchars($emailUsuario, ENT_QUOTES, 'UTF-8');
    $mensagemHtml = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));
    $protocoloHtml = htmlspecialchars($protocolo, ENT_QUOTES, 'UTF-8');
    $mail->isHTML(true);
    $mail->Subject = "Novo chamado {$protocolo} - App Controle de Contas";
    $mail->Body = "<h2>Novo chamado registrado</h2><p><strong>Protocolo:</strong> {$protocoloHtml}</p><p><strong>Nome:</strong> {$nomeHtml}</p><p><strong>WhatsApp:</strong> {$whatsappHtml}</p><p><strong>E-mail:</strong> {$emailHtml}</p><hr><p><strong>Mensagem:</strong><br>{$mensagemHtml}</p>";
    $mail->AltBody = "Protocolo: {$protocolo}\nNome: {$nome}\nWhatsApp: " . ($whatsapp !== '' ? $whatsapp : 'Não informado') . "\nE-mail: {$emailUsuario}\nMensagem:\n{$mensagem}";
    $mail->send();
    $emailAvisoEnviado = true;
} catch (Throwable $e) {
    $detalheEmail = ($mail instanceof PHPMailer && $mail->ErrorInfo)
        ? $mail->ErrorInfo
        : $e->getMessage();
    $erroEmail = mb_substr($detalheEmail, 0, 500);
    error_log('Chamado registrado, mas aviso por e-mail falhou: ' . $erroEmail);
}

try {
    $statusEnvio = $emailAvisoEnviado ? 'enviado' : 'erro';
    $stmtEnvio = $conn->prepare('UPDATE suporte_login SET ultimo_envio_status = ?, ultimo_envio_erro = ? WHERE id = ?');
    $stmtEnvio->bind_param('ssi', $statusEnvio, $erroEmail, $supportId);
    $stmtEnvio->execute();
    $stmtEnvio->close();
    $conn->close();
} catch (Throwable $ignored) {
    error_log('Não foi possível atualizar o status do aviso do chamado ' . $protocolo);
}

echo json_encode([
    'status' => 'success',
    'msg' => 'Chamado registrado com sucesso.',
    'protocolo' => $protocolo,
], JSON_UNESCAPED_UNICODE);

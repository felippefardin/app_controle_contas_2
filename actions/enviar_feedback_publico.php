<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
header('Content-Type: application/json; charset=utf-8');

function feedbackResponse(int $httpStatus, string $status, string $message): never
{
    http_response_code($httpStatus);
    echo json_encode(['status' => $status, 'msg' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') feedbackResponse(405, 'error', 'Método não permitido.');
require_csrf(true);

$agora = time();
if ($agora - (int)($_SESSION['ultimo_feedback_publico'] ?? 0) < 30) feedbackResponse(429, 'error', 'Aguarde alguns segundos antes de enviar outro feedback.');

$anonimo = isset($_POST['anonimo']) && (string)$_POST['anonimo'] === '1' ? 1 : 0;
$nome = trim((string)($_POST['nome'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
$descricao = trim((string)($_POST['descricao'] ?? ''));
$pontuacao = (int)($_POST['pontuacao'] ?? 5);

if ($anonimo) { $nome = 'Anônimo'; $email = ''; $whatsapp = ''; }
elseif ($nome === '') $nome = 'Visitante';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) feedbackResponse(422, 'error', 'Informe um e-mail válido.');
if (mb_strlen($descricao) < 10 || mb_strlen($descricao) > 1200) feedbackResponse(422, 'error', 'O comentário deve ter entre 10 e 1.200 caracteres.');
if ($pontuacao < 1 || $pontuacao > 5) feedbackResponse(422, 'error', 'Selecione uma avaliação entre 1 e 5 estrelas.');

try {
    $conn = getMasterConnection();
    $stmt = $conn->prepare('INSERT INTO feedbacks (nome, email, whatsapp, descricao, pontuacao, anonimo, aprovado, lido) VALUES (?, ?, ?, ?, ?, ?, 0, 0)');
    $stmt->bind_param('ssssii', $nome, $email, $whatsapp, $descricao, $pontuacao, $anonimo);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    $_SESSION['ultimo_feedback_publico'] = $agora;
    feedbackResponse(201, 'success', 'Feedback enviado! Ele ficará pendente até a aprovação do usuário Master.');
} catch (Throwable $error) {
    error_log('Falha ao salvar feedback público: ' . $error->getMessage());
    feedbackResponse(500, 'error', 'Não foi possível enviar o feedback agora. Tente novamente mais tarde.');
}

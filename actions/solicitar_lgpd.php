<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'msg' => 'Método não permitido.']);
    exit;
}

require_csrf(true);

$lastRequestAt = (int) ($_SESSION['last_lgpd_request_at'] ?? 0);
if ($lastRequestAt > 0 && (time() - $lastRequestAt) < 30) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'msg' => 'Aguarde alguns segundos antes de enviar outra solicitação.']);
    exit;
}

$tipo = trim((string) ($_POST['tipo'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? ''));
$tiposPermitidos = ['acesso', 'correcao', 'exclusao'];

if (!in_array($tipo, $tiposPermitidos, true)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'Selecione o tipo da solicitação.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'Informe um e-mail de cadastro válido.']);
    exit;
}

$telefoneNumeros = preg_replace('/\D+/', '', $telefone);
if (strlen($telefoneNumeros) < 10 || strlen($telefoneNumeros) > 15) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'Informe um telefone ou WhatsApp válido com DDD.']);
    exit;
}

if (mb_strlen($descricao) < 10 || mb_strlen($descricao) > 2000) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'Descreva a solicitação usando entre 10 e 2.000 caracteres.']);
    exit;
}

try {
    $conn = getMasterConnection();
    $protocolo = 'LGPD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $usuarioId = !empty($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    $tenantId = trim((string) ($_SESSION['tenant_id'] ?? '')) ?: null;
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . csrf_token()) : null;

    $stmt = $conn->prepare(
        'INSERT INTO solicitacoes_lgpd
        (protocolo, tipo, email, telefone, descricao, usuario_id, tenant_id, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssssiss', $protocolo, $tipo, $email, $telefone, $descricao, $usuarioId, $tenantId, $ipHash);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    $_SESSION['last_lgpd_request_at'] = time();
    echo json_encode([
        'status' => 'success',
        'msg' => 'Solicitação registrada com sucesso.',
        'protocolo' => $protocolo,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Erro ao registrar solicitação LGPD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'Não foi possível registrar a solicitação. Tente novamente.']);
}

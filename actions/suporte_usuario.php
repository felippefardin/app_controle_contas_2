<?php
require_once '../includes/session_init.php';
require_once '../database.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    http_response_code(401);
    echo json_encode(['erro' => 'Sessão expirada']);
    exit;
}

$tenant_id = (string)($_SESSION['tenant_id'] ?? '');
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$conn = getMasterConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true) ?: [];
    $id_chamado = (int)($dados['id_chamado'] ?? 0);
    $mensagem = trim((string)($dados['mensagem'] ?? ''));
    $stmt = $conn->prepare("SELECT status FROM chamados_suporte WHERE id = ? AND tenant_id = ? AND usuario_id = ? LIMIT 1");
    $stmt->bind_param('isi', $id_chamado, $tenant_id, $usuario_id);
    $stmt->execute();
    $chamado = $stmt->get_result()->fetch_assoc();
    if (!$chamado || in_array($chamado['status'], ['concluido', 'finalizado'], true) || $mensagem === '') {
        http_response_code(422);
        echo json_encode(['erro' => 'Chamado inválido ou encerrado']);
        exit;
    }
    $autor = (string)($_SESSION['usuario_nome'] ?? 'Usuário');
    $stmt = $conn->prepare("INSERT INTO chamados_historico (chamado_id, autor_tipo, autor_nome, mensagem) VALUES (?, 'usuario', ?, ?)");
    $stmt->bind_param('iss', $id_chamado, $autor, $mensagem);
    $stmt->execute();
    echo json_encode(['ok' => true]);
    exit;
}

$id_chamado = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT id FROM chamados_suporte WHERE id = ? AND tenant_id = ? AND usuario_id = ? LIMIT 1");
$stmt->bind_param('isi', $id_chamado, $tenant_id, $usuario_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['erro' => 'Chamado não encontrado']);
    exit;
}
$stmt = $conn->prepare("SELECT autor_tipo, autor_nome, mensagem, criado_em FROM chamados_historico WHERE chamado_id = ? ORDER BY criado_em ASC, id ASC");
$stmt->bind_param('i', $id_chamado);
$stmt->execute();
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);

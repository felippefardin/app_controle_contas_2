<?php
require_once '../includes/session_init.php';
require_once '../database.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['super_admin'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Sessão master expirada']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['erro' => 'Chamado inválido']);
    exit;
}

$conn = getMasterConnection();
$stmt = $conn->prepare("SELECT autor_tipo, autor_nome, mensagem, criado_em FROM chamados_historico WHERE chamado_id = ? ORDER BY criado_em ASC, id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);

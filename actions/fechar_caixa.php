<?php
require_once '../includes/session_init.php';
require_once '../database.php';

if (empty($_SESSION['usuario_logado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit('Acesso negado.');
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    exit('Sessão expirada. Atualize a página e tente novamente.');
}

$data = (string)($_POST['data'] ?? '');
$dataValida = DateTime::createFromFormat('Y-m-d', $data);
if (!$dataValida || $dataValida->format('Y-m-d') !== $data || $data > date('Y-m-d')) {
    exit('Data de fechamento inválida.');
}

$conn = getTenantConnection();
$donoDados = get_data_owner_id();
$operador = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $stmt = $conn->prepare('SELECT COUNT(*) AS quantidade,COALESCE(SUM(valor_total),0) AS total,COALESCE(MAX(id),0) AS ultimo_id FROM vendas WHERE id_usuario=? AND DATE(data_venda)=?');
    $stmt->bind_param('is', $donoDados, $data);
    $stmt->execute();
    $resumo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$resumo['quantidade'] === 0) exit('Não existem vendas nessa data para fechar.');

    $sql = "INSERT INTO fechamentos_caixa (id_usuario,data_caixa,quantidade_vendas,valor_total,ultimo_venda_id,fechado_por,fechado_em)
            VALUES (?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE quantidade_vendas=VALUES(quantidade_vendas),valor_total=VALUES(valor_total),ultimo_venda_id=VALUES(ultimo_venda_id),fechado_por=VALUES(fechado_por),fechado_em=NOW()";
    $stmt = $conn->prepare($sql);
    $quantidade = (int)$resumo['quantidade'];
    $total = (float)$resumo['total'];
    $ultimoId = (int)$resumo['ultimo_id'];
    $stmt->bind_param('isidii', $donoDados, $data, $quantidade, $total, $ultimoId, $operador);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_caixa'] = 'Caixa de '.date('d/m/Y', strtotime($data)).' fechado com sucesso.';
    header('Location: ../pages/fechamento_caixa.php?data='.urlencode($data));
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao fechar o caixa: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

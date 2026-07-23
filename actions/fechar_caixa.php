<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/caixa_service.php';

if (empty($_SESSION['usuario_logado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit('Acesso negado.');
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    exit('Sessão expirada. Atualize a página e tente novamente.');
}

$data = (string)($_POST['data'] ?? '');
if (!caixa_data_valida($data) || $data !== date('Y-m-d')) {
    exit('Somente o caixa aberto de hoje pode ser fechado.');
}

$conn = getTenantConnection();
$ownerId = get_data_owner_id();
$operatorId = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $conn->begin_transaction();
    $sessao = caixa_buscar_sessao($conn, $ownerId, $data, true);
    if (!$sessao || $sessao['status'] !== 'aberto') {
        throw new RuntimeException('Não existe um caixa aberto para fechar hoje.');
    }

    $stmt = $conn->prepare('SELECT COUNT(*) quantidade,COALESCE(SUM(valor_total),0) total,COALESCE(MAX(id),0) ultimo_id FROM vendas WHERE id_usuario=? AND DATE(data_venda)=?');
    $stmt->bind_param('is', $ownerId, $data);
    $stmt->execute();
    $vendas = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $resumo = caixa_resumo($conn, $ownerId, $data);

    $sql = 'INSERT INTO fechamentos_caixa
        (id_usuario,data_caixa,quantidade_vendas,valor_total,ultimo_venda_id,fechado_por,fechado_em,abertura_id,valor_abertura,total_dinheiro,total_outros,saldo_dinheiro_esperado)
        VALUES (?,?,?,?,?,?,NOW(),?,?,?,?,?)
        ON DUPLICATE KEY UPDATE quantidade_vendas=VALUES(quantidade_vendas),valor_total=VALUES(valor_total),
        ultimo_venda_id=VALUES(ultimo_venda_id),fechado_por=VALUES(fechado_por),fechado_em=NOW(),
        abertura_id=VALUES(abertura_id),valor_abertura=VALUES(valor_abertura),total_dinheiro=VALUES(total_dinheiro),
        total_outros=VALUES(total_outros),saldo_dinheiro_esperado=VALUES(saldo_dinheiro_esperado)';
    $stmt = $conn->prepare($sql);
    $quantidade = (int)$vendas['quantidade'];
    $total = (float)$vendas['total'];
    $ultimoId = (int)$vendas['ultimo_id'];
    $aberturaId = (int)$sessao['id'];
    $valorAbertura = (float)$resumo['valor_abertura'];
    $totalDinheiro = (float)$resumo['total_dinheiro'];
    $totalOutros = (float)$resumo['total_outros'];
    $saldoEsperado = (float)$resumo['saldo_dinheiro_esperado'];
    $stmt->bind_param('isidiiidddd', $ownerId, $data, $quantidade, $total, $ultimoId, $operatorId, $aberturaId, $valorAbertura, $totalDinheiro, $totalOutros, $saldoEsperado);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE aberturas_caixa SET status='fechado',fechado_por=?,fechado_em=NOW() WHERE id=? AND status='aberto'");
    $stmt->bind_param('ii', $operatorId, $aberturaId);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) throw new RuntimeException('O caixa já foi fechado por outro usuário.');
    $stmt->close();
    $conn->commit();

    $_SESSION['flash_caixa'] = 'Caixa de ' . date('d/m/Y', strtotime($data)) . ' fechado com sucesso.';
    header('Location: ../pages/fechamento_caixa.php?data=' . urlencode($data));
    exit;
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    http_response_code(500);
    echo 'Erro ao fechar o caixa: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

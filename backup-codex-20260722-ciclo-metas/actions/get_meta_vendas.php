<?php
require_once '../includes/session_init.php';
require_once '../database.php'; // Carrega a função getTenantConnection()

if (!isset($_SESSION['usuario_logado'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Usuário não logado.']);
    exit;
}

// ✅ 1. PEGA A CONEXÃO CORRETA DO TENANT
$conn = getTenantConnection(); 
if ($conn === null) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com o banco de dados do cliente.']);
    exit;
}

// Força o mysqli a lançar Exceptions em caso de erro (ex: tabela não existe)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$ano_mes_atual = date('Y_n'); // Ex: 2025_11
$chave_meta = "meta_vendas_" . $ano_mes_atual;
$valor_meta = 0.00;
$valor_atual = 0.00;

try {
    // 2. Buscar a meta
    // Esta query irá falhar se 'configuracoes_tenant' não existir no banco do TENANT
    $stmt_meta = $conn->prepare("SELECT valor FROM configuracoes_tenant WHERE chave = ?");
    $stmt_meta->bind_param("s", $chave_meta);
    $stmt_meta->execute();
    $result_meta = $stmt_meta->get_result();
    if ($row_meta = $result_meta->fetch_assoc()) {
        $valor_meta = (float)$row_meta['valor'];
    }
    $stmt_meta->close();

    // 3. Calcular o total de vendas (do banco do TENANT)
    $stmt_atual = $conn->prepare("
        SELECT SUM(valor_total) as total_mes 
        FROM vendas 
        WHERE MONTH(data_venda) = MONTH(CURRENT_DATE())
          AND YEAR(data_venda) = YEAR(CURRENT_DATE())
    ");
    $stmt_atual->execute();
    $result_atual = $stmt_atual->get_result();
    if ($row_atual = $result_atual->fetch_assoc()) {
        $valor_atual = (float)$row_atual['total_mes'];
    }
    $stmt_atual->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'meta' => $valor_meta,
        'atual' => $valor_atual,
        'meta_formatada' => number_format($valor_meta, 2, ',', '.'),
        'atual_formatado' => number_format($valor_atual, 2, ',', '.')
    ]);

} catch (Exception $e) {
    // Se a tabela não existir, o erro será capturado e enviado como JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
exit;
?>
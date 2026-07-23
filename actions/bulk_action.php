<?php
// actions/bulk_action.php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/audit.php';

if (!isset($_SESSION['usuario_logado'])) {
    die(json_encode(['status' => 'error', 'message' => 'Não autorizado']));
}
require_csrf(true);

$conn = getTenantConnection();
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['ids'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum item selecionado.']);
    exit;
}

$ids = $data['ids']; // Array de IDs
$tipo = $data['tipo']; // 'pagar', 'receber'
$acao = $data['acao']; // 'baixar', 'excluir'
if (!in_array($tipo, ['pagar', 'receber'], true) || !in_array($acao, ['baixar', 'excluir'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Ação inválida.']);
    exit;
}
$permission = $tipo === 'pagar' ? 'contas_pagar.php' : 'contas_receber.php';
require_page_permission($permission, true);
$ids_sanitized = implode(',', array_map('intval', $ids));

// CONFIGURAÇÃO DA QUERY
if ($acao === 'baixar') {
    // Para Baixar (Dar baixa)
    $tabela = ($tipo === 'pagar') ? 'contas_pagar' : 'contas_receber';
    $data_baixa = $data['data_baixa'] ?? date('Y-m-d');
    $forma = $data['forma_pagamento'] ?? 'dinheiro';
    
    // Atualiza status e insere data/forma
    $sql = "UPDATE $tabela SET status = 'baixada', data_baixa = ?, forma_pagamento = ? WHERE id IN ($ids_sanitized) AND usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $data_baixa, $forma, get_data_owner_id());
    
    // NOTA: Aqui idealmente você também inseriria no caixa_diario, 
    // mas para manter simples e não alterar o fluxo complexo, focamos no status.
    
} elseif ($acao === 'excluir') {
    $tabela = ($tipo === 'pagar') ? 'contas_pagar' : 'contas_receber';
    $ownerId = get_data_owner_id();
    $changed = 0;
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id > 0 && soft_delete_record($conn, $tabela, $id, $ownerId)) $changed++;
    }
    echo json_encode(['status' => 'success', 'message' => $changed . ' registro(s) movido(s) para a lixeira.']);
    exit;
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Ação em massa realizada com sucesso!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao processar banco de dados.']);
}
?>

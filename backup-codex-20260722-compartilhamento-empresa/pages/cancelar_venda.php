<?php
require_once '../includes/session_init.php';
require_once '../database.php';

header('Content-Type: application/json');

// 1. Validação de segurança: verifica se o usuário está logado e se o ID existe
// CORREÇÃO: Verifica se usuario_logado é true e se usuario_id está definido
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true || !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida. Por favor, faça login novamente.']);
    exit;
}

// 2. Obtém a conexão com o banco de dados do tenant
$conn = getTenantConnection();
if ($conn === null) {
    echo json_encode(['success' => false, 'message' => 'Falha ao conectar ao banco de dados.']);
    exit;
}

// CORREÇÃO: Pega o ID da variável direta, não do array
$id_usuario = $_SESSION['usuario_id']; 

$venda_id = filter_input(INPUT_POST, 'venda_id', FILTER_VALIDATE_INT);

// 3. Validação do ID da venda
if (!$venda_id) {
    echo json_encode(['success' => false, 'message' => 'ID de venda inválido.']);
    exit;
}

// Inicia uma transação para garantir a integridade dos dados
$conn->begin_transaction();

try {
    // 4. Busca os itens da venda para saber o que devolver ao estoque
    $stmt_itens = $conn->prepare("SELECT id_produto, quantidade FROM venda_items WHERE id_venda = ?");
    $stmt_itens->bind_param("i", $venda_id);
    $stmt_itens->execute();
    $itens_result = $stmt_itens->get_result();

    // Verifica se a venda existe mesmo se não tiver itens (segurança extra)
    if ($itens_result->num_rows === 0) {
        $stmt_venda_check = $conn->prepare("SELECT id FROM vendas WHERE id = ? AND id_usuario = ?");
        $stmt_venda_check->bind_param("ii", $venda_id, $id_usuario);
        $stmt_venda_check->execute();
        $venda_exists = $stmt_venda_check->get_result()->fetch_assoc();
        if (!$venda_exists) {
            throw new Exception("Venda não encontrada ou já cancelada.");
        }
    }

    $itens_para_devolver = [];
    while ($item = $itens_result->fetch_assoc()) {
        $itens_para_devolver[] = $item;
    }

    // 5. Devolve os itens ao estoque
    foreach ($itens_para_devolver as $item) {
        $stmt_estoque = $conn->prepare("UPDATE produtos SET quantidade_estoque = quantidade_estoque + ? WHERE id = ? AND id_usuario = ?");
        $stmt_estoque->bind_param("iii", $item['quantidade'], $item['id_produto'], $id_usuario);
        $stmt_estoque->execute();
    }

    // 6. Remove os lançamentos financeiros associados
    // Remove do caixa diário usando o id_venda
    $stmt_caixa = $conn->prepare("DELETE FROM caixa_diario WHERE id_venda = ? AND usuario_id = ?");
    $stmt_caixa->bind_param("ii", $venda_id, $id_usuario);
    $stmt_caixa->execute();

    // Remove das contas a receber (caso a venda tenha sido a prazo) usando o id_venda
    // Nota: Certifique-se que sua tabela contas_receber tem a coluna id_venda, senão isso dará erro.
    // Se não tiver id_venda na tabela contas_receber, remova ou comente o bloco abaixo.
    $coluna_check = $conn->query("SHOW COLUMNS FROM contas_receber LIKE 'id_venda'");
    if ($coluna_check && $coluna_check->num_rows > 0) {
        $stmt_receber = $conn->prepare("DELETE FROM contas_receber WHERE id_venda = ? AND usuario_id = ?");
        $stmt_receber->bind_param("ii", $venda_id, $id_usuario);
        $stmt_receber->execute();
    }

    // 7. Remove os itens da venda da tabela 'venda_items'
    $stmt_delete_itens = $conn->prepare("DELETE FROM venda_items WHERE id_venda = ?");
    $stmt_delete_itens->bind_param("i", $venda_id);
    $stmt_delete_itens->execute();

    // 8. Remove o registro principal da venda
    $stmt_delete_venda = $conn->prepare("DELETE FROM vendas WHERE id = ? AND id_usuario = ?");
    $stmt_delete_venda->bind_param("ii", $venda_id, $id_usuario);
    $stmt_delete_venda->execute();
    
    // Se a venda foi deletada com sucesso (afetou uma linha)
    if ($stmt_delete_venda->affected_rows > 0) {
        // Confirma a transação
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Venda #' . $venda_id . ' cancelada com sucesso! O estoque foi restaurado.']);
    } else {
        // Se a venda principal não foi encontrada para este usuário
        throw new Exception("A venda não foi encontrada ou você não tem permissão para cancelá-la.");
    }

} catch (Exception $e) {
    // Se qualquer etapa falhar, desfaz todas as alterações
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Erro ao cancelar a venda: ' . $e->getMessage()]);
}
?>
<?php
// Configuração de erro para retornar JSON limpo
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/session_init.php';
require_once '../database.php';

try {
    // 1. VERIFICA LOGIN
    if (!isset($_SESSION['usuario_logado'])) {
        throw new Exception('Sessão expirada. Faça login novamente.');
    }
    
    // --- CÓDIGO NOVO: BLOQUEIO DO PLANO BÁSICO ---
    // Isso garante que NINGUÉM no plano básico (nem admin) consiga finalizar uma venda.
    $plano = $_SESSION['plano'] ?? 'basico';
    if ($plano === 'basico') {
        throw new Exception('Seu plano atual (Básico) não permite registrar vendas. Faça um upgrade para continuar.');
    }
    // ------------------------------------------------

    $conn = getTenantConnection();
    if ($conn === null) {
        throw new Exception('Falha de conexão com o banco de dados.');
    }

    $id_usuario = $_SESSION['usuario_id'];

    // 2. RECEBE E VALIDA DADOS
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $itens_venda_json = $_POST['itens'] ?? '[]';
    $itens_venda = json_decode($itens_venda_json, true);
    $desconto = !empty($_POST['desconto']) ? (float)str_replace(',', '.', $_POST['desconto']) : 0.00;
    $tipo_finalizacao = $_POST['tipo_finalizacao'] ?? 'recibo';

    if (empty($itens_venda) || !is_array($itens_venda)) {
        throw new Exception('Nenhum item válido na venda.');
    }
    if (empty($cliente_id)) {
        throw new Exception('Cliente não selecionado.');
    }

    // INICIA TRANSAÇÃO
    $conn->begin_transaction();

    // 3. VERIFICA ESTOQUE
    foreach ($itens_venda as $item) {
        $id_produto = (int)$item['id'];
        $quantidade_pedida = (float)$item['quantidade'];

        $stmt = $conn->prepare("SELECT nome, quantidade_estoque FROM produtos WHERE id = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_produto, $id_usuario);
        $stmt->execute();
        $res = $stmt->get_result();
        $produto = $res->fetch_assoc();
        $stmt->close();

        if (!$produto) throw new Exception("Produto ID $id_produto não encontrado.");
        if ($produto['quantidade_estoque'] < $quantidade_pedida) {
            throw new Exception("Estoque insuficiente para '{$produto['nome']}'.");
        }
    }

    // 4. CALCULA TOTAIS
    $total_venda_bruto = 0;
    foreach ($itens_venda as $item) {
        $total_venda_bruto += $item['quantidade'] * $item['preco'];
    }
    
    if ($desconto > $total_venda_bruto) {
        throw new Exception("Desconto maior que o total da venda.");
    }
    
    $total_venda_liquido = $total_venda_bruto - $desconto;

    // 5. INSERE VENDA
    $stmt_venda = $conn->prepare("INSERT INTO vendas (id_usuario, id_cliente, valor_total, desconto, forma_pagamento, data_venda) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt_venda->bind_param("iidds", $id_usuario, $cliente_id, $total_venda_liquido, $desconto, $forma_pagamento);
    
    if (!$stmt_venda->execute()) {
        throw new Exception("Erro ao salvar venda: " . $stmt_venda->error);
    }
    $venda_id = $conn->insert_id;
    $stmt_venda->close();

    // 6. INSERE ITENS E BAIXA ESTOQUE
    $stmt_item = $conn->prepare("INSERT INTO venda_items (id_venda, id_produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stmt_estoque = $conn->prepare("UPDATE produtos SET quantidade_estoque = quantidade_estoque - ? WHERE id = ? AND id_usuario = ?");

    foreach ($itens_venda as $item) {
        $subtotal = $item['quantidade'] * $item['preco'];
        
        $stmt_item->bind_param("iiidd", $venda_id, $item['id'], $item['quantidade'], $item['preco'], $subtotal);
        if (!$stmt_item->execute()) throw new Exception("Erro ao salvar item da venda.");

        $stmt_estoque->bind_param("dii", $item['quantidade'], $item['id'], $id_usuario);
        if (!$stmt_estoque->execute()) throw new Exception("Erro ao atualizar estoque.");
    }
    $stmt_item->close();
    $stmt_estoque->close();

    // 7. LANÇAMENTO FINANCEIRO (CONTAS A RECEBER)
    // Busca ou Cria Categoria 'Venda de Caixa'
    $stmt_cat = $conn->prepare("SELECT id FROM categorias WHERE nome = 'Venda de Caixa' AND id_usuario = ? AND tipo = 'receita'");
    $stmt_cat->bind_param("i", $id_usuario);
    $stmt_cat->execute();
    $res_cat = $stmt_cat->get_result()->fetch_assoc();
    $stmt_cat->close();

    if ($res_cat) {
        $categoria_id = $res_cat['id'];
    } else {
        // Cria a categoria se não existir
        $stmt_new_cat = $conn->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, 'Venda de Caixa', 'receita')");
        $stmt_new_cat->bind_param("i", $id_usuario);
        $stmt_new_cat->execute();
        $categoria_id = $conn->insert_id;
        $stmt_new_cat->close();
    }

    // Descrição inclui o ID da venda para rastreamento
    $descricao = "Venda #$venda_id - PDV";
    $hoje = date('Y-m-d');

    // Configuração de Status
    if ($forma_pagamento !== 'receber') {
        $status = 'baixada';
        $data_baixa = $hoje;
        $data_vencimento = $hoje;
    } else {
        $status = 'pendente';
        $data_baixa = null;
        $data_vencimento = date('Y-m-d', strtotime('+30 days'));
    }

    $stmt_fin = $conn->prepare("INSERT INTO contas_receber (usuario_id, id_pessoa_fornecedor, id_categoria, descricao, valor, data_vencimento, status, data_baixa, forma_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt_fin) {
        throw new Exception("Erro na estrutura da tabela financeira: " . $conn->error);
    }

    $stmt_fin->bind_param("iiisdssss", 
        $id_usuario, 
        $cliente_id, 
        $categoria_id, 
        $descricao, 
        $total_venda_liquido, 
        $data_vencimento, 
        $status, 
        $data_baixa, 
        $forma_pagamento
    );
    
    if (!$stmt_fin->execute()) {
        throw new Exception("Erro ao lançar no financeiro: " . $stmt_fin->error);
    }
    $stmt_fin->close();

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Venda realizada com sucesso!',
        'venda_id' => $venda_id
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        try { $conn->rollback(); } catch (Throwable $t) {}
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
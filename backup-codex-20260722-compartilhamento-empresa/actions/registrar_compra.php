<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa o sistema de Flash Message

// 1. VERIFICA SE O USUÁRIO ESTÁ LOGADO
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

$conn = getTenantConnection();
if ($conn === null) {
    die("Falha ao obter a conexão com o banco de dados do cliente.");
}

$id_usuario = $_SESSION['usuario_id'];

// 3. PROCESSA O POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_fornecedor = (int)$_POST['id_fornecedor'];
    $valor_total = (float)$_POST['valor_total'];
    $observacao = trim($_POST['observacao']);
    $produtos = $_POST['produtos'] ?? [];

    // VALIDAÇÕES
    if (empty($id_fornecedor) || empty($produtos) || $valor_total <= 0) {
        set_flash_message('danger', "Fornecedor ou produtos inválidos.");
        header('Location: ../pages/compras.php');
        exit;
    }

    // Inicia transação
    $conn->begin_transaction();

    try {
        // 4. INSERE A COMPRA
        $stmt_compra = $conn->prepare("
            INSERT INTO compras (
                id_usuario, id_fornecedor, valor_total, observacao
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt_compra->bind_param("iids", $id_usuario, $id_fornecedor, $valor_total, $observacao);
        $stmt_compra->execute();

        $id_compra = $conn->insert_id;
        $descricao_conta = "Compra #" . $id_compra;

        // 5. PROCESSA CADA PRODUTO
        foreach ($produtos as $produto) {

            $id_produto = (int)$produto['id'];
            $quantidade = (int)$produto['quantidade'];
            $preco = (float)$produto['preco'];

            // Validação mínima
            if ($id_produto <= 0 || $quantidade <= 0 || $preco <= 0) {
                throw new Exception("Produto inválido detectado.");
            }

            // 5a. Insere item da compra
            $stmt_item = $conn->prepare("
                INSERT INTO compra_items (id_compra, id_produto, quantidade, preco_unitario)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_item->bind_param("iiid", $id_compra, $id_produto, $quantidade, $preco);
            $stmt_item->execute();

            // 5b. Atualiza estoque
            $stmt_update = $conn->prepare("
                UPDATE produtos 
                SET quantidade_estoque = quantidade_estoque + ? 
                WHERE id = ? AND id_usuario = ?
            ");
            $stmt_update->bind_param("iii", $quantidade, $id_produto, $id_usuario);
            $stmt_update->execute();

            // 5c. Movimento de estoque
            $obs_mov = "Compra #" . $id_compra;
            $stmt_mov = $conn->prepare("
                INSERT INTO movimento_estoque (
                    id_produto, id_usuario, id_pessoa_fornecedor, tipo, quantidade, observacao
                ) VALUES (?, ?, ?, 'entrada', ?, ?)
            ");
            $stmt_mov->bind_param("iiiis", $id_produto, $id_usuario, $id_fornecedor, $quantidade, $obs_mov);
            $stmt_mov->execute();
        }

        // 6. GERA CONTA A PAGAR
        $data_vencimento = date('Y-m-d');

        $stmt_pagar = $conn->prepare("
            INSERT INTO contas_pagar (
                usuario_id, id_pessoa_fornecedor, descricao, valor, data_vencimento, status
            ) VALUES (?, ?, ?, ?, ?, 'pendente')
        ");
        $stmt_pagar->bind_param("iisds", $id_usuario, $id_fornecedor, $descricao_conta, $valor_total, $data_vencimento);
        $stmt_pagar->execute();

        // Finaliza transação
        $conn->commit();

        set_flash_message('success', "Compra #$id_compra registrada com sucesso!");
        header('Location: ../pages/compras.php');
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        set_flash_message('danger', "Erro ao registrar compra: " . $e->getMessage());
        header('Location: ../pages/compras.php');
        exit;
    }
}
?>
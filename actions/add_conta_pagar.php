<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // <-- IMPORTANTE: Incluir o novo arquivo

// 1. Validação de Acesso
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. Recebendo dados do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    
    // Dados básicos com sanitização simples
    $fornecedor_id = !empty($_POST['fornecedor_id']) ? intval($_POST['fornecedor_id']) : null;
    $nome_fornecedor_novo = trim($_POST['fornecedor_nome'] ?? '');
    $descricao = trim($_POST['descricao']);
    $numero_doc = trim($_POST['numero']);
    $categoria_id = intval($_POST['id_categoria']);
    
    // --- CORREÇÃO 1: Tratamento de Valor (BRL -> Float) ---
    // Agora o usuário pode digitar "1.200,50" ou "1200.50" que vai funcionar
    $valor = brl_to_float($_POST['valor']);

    // --- CORREÇÃO 2: Tratamento de Data ---
    $vencimento = data_para_iso($_POST['data_vencimento']);

    // Validação Backend
    if (empty($descricao) || $valor <= 0 || empty($vencimento)) {
        set_flash_message('danger', 'Preencha a descrição, uma data válida e um valor maior que zero.');
        header('Location: ../pages/contas_pagar.php');
        exit;
    }

    $conn = getTenantConnection();
    if (!$conn) {
        set_flash_message('danger', 'Erro de conexão com o banco.');
        header('Location: ../pages/contas_pagar.php');
        exit;
    }

    // Lógica para Fornecedor (Se não selecionou ID, mas digitou nome, cria um novo)
    if (!$fornecedor_id && !empty($nome_fornecedor_novo)) {
        // Verifica se já existe pelo nome
        $stmtCheck = $conn->prepare("SELECT id FROM pessoas_fornecedores WHERE nome = ? AND id_usuario = ? AND tipo = 'fornecedor'");
        $stmtCheck->bind_param("si", $nome_fornecedor_novo, $usuario_id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        
        if ($row = $resCheck->fetch_assoc()) {
            $fornecedor_id = $row['id'];
        } else {
            // Cadastra novo
            $stmtNew = $conn->prepare("INSERT INTO pessoas_fornecedores (id_usuario, nome, tipo) VALUES (?, ?, 'fornecedor')");
            $stmtNew->bind_param("is", $usuario_id, $nome_fornecedor_novo);
            if ($stmtNew->execute()) {
                $fornecedor_id = $stmtNew->insert_id;
            }
            $stmtNew->close();
        }
        $stmtCheck->close();
    }

    // 3. Inserção no Banco
    $sql = "INSERT INTO contas_pagar (usuario_id, id_pessoa_fornecedor, id_categoria, numero, descricao, valor, data_vencimento, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
            
    $stmt = $conn->prepare($sql);
    // Tipos: i (int), i (int), i (int), s (string), s (string), d (double/float), s (string)
    $stmt->bind_param("iiissds", $usuario_id, $fornecedor_id, $categoria_id, $numero_doc, $descricao, $valor, $vencimento);

    if ($stmt->execute()) {
        // --- CORREÇÃO 3: Feedback visual elegante ---
        set_flash_message('success', "Conta '{$descricao}' adicionada com sucesso!");
    } else {
        set_flash_message('danger', "Erro ao salvar: " . $stmt->error);
    }

    $stmt->close();
    
    // Redireciona (o flash message aparecerá na tela de destino)
    header('Location: ../pages/contas_pagar.php');
    exit;
}
?>
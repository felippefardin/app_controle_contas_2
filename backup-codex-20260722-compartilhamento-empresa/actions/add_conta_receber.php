<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Certifique-se de ter criado este arquivo no passo anterior

// 1. VERIFICA O LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php?error=not_logged_in');
    exit;
}

// 2. VERIFICA SE O MÉTODO É POST E O TOKEN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica CSRF (Segurança)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido. Tente novamente.');
        header('Location: ../pages/contas_receber.php');
        exit;
    }

    $conn = getTenantConnection();
    if ($conn === null) {
        set_flash_message('danger', 'Falha na conexão com o banco de dados.');
        header('Location: ../pages/contas_receber.php');
        exit;
    }

    $usuario_id = $_SESSION['usuario_id'] ?? null;

    if (!$usuario_id) {
        set_flash_message('danger', 'Sessão expirada. Faça login novamente.');
        header('Location: ../pages/login.php');
        exit;
    }

    // Pega dados do formulário
    $id_pessoa_fornecedor = !empty($_POST['pessoa_id']) ? (int)$_POST['pessoa_id'] : null;
    $numero = trim($_POST['numero'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    
    // --- Validação e Tratamento de Dados ---
    // Usa funções do utils.php para garantir que R$ 1.000,00 vire 1000.00 e datas fiquem certas
    $valor = brl_to_float($_POST['valor']); 
    $data_vencimento = data_para_iso($_POST['data_vencimento']); 
    $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;

    // Validação básica
    if ($valor <= 0 || empty($data_vencimento) || empty($id_categoria) || empty($descricao)) {
        set_flash_message('danger', 'Preencha a descrição, categoria, data e um valor válido.');
        header('Location: ../pages/contas_receber.php');
        exit;
    }

    // 3. INSERE OS DADOS
    $sql = "INSERT INTO contas_receber (
                id_pessoa_fornecedor, 
                numero,
                descricao,
                valor, 
                data_vencimento, 
                usuario_id, 
                id_categoria, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
            
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("issdsii", 
            $id_pessoa_fornecedor, 
            $numero,
            $descricao, 
            $valor, 
            $data_vencimento, 
            $usuario_id, 
            $id_categoria
        );

        if ($stmt->execute()) {
            set_flash_message('success', 'Receita adicionada com sucesso!');
        } else {
            set_flash_message('danger', 'Erro ao salvar no banco: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        set_flash_message('danger', 'Erro técnico ao preparar consulta.');
    }

    header('Location: ../pages/contas_receber.php');
    exit;
}
?>
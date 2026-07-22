<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// 1. Verifica login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: ../pages/login.php");
    exit;
}

// 2. Valida POST e CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido.');
        header("Location: ../pages/controle_estoque.php");
        exit;
    }

    $conn = getTenantConnection();
    if (!$conn) {
        set_flash_message('danger', 'Erro de conexão com o banco de dados.');
        header("Location: ../pages/controle_estoque.php");
        exit;
    }

    // Função local
    function formatarMoedaLocal($valor) {
        if (empty($valor)) return 0;
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        return floatval($valor);
    }

    // --- CAPTURA DE DADOS (AGORA COM CÓDIGO/SKU) ---
    $codigo             = trim($_POST['codigo'] ?? ''); // <--- CAMPO NOVO
    $nome               = trim($_POST['nome'] ?? '');
    $descricao          = trim($_POST['descricao'] ?? '');
    $quantidade         = intval($_POST['quantidade_estoque'] ?? 0);
    $quantidade_minima  = intval($_POST['quantidade_minima'] ?? 0);
    $preco_compra       = formatarMoedaLocal($_POST['preco_compra'] ?? '0');
    $preco_venda        = formatarMoedaLocal($_POST['preco_venda'] ?? '0');
    $ncm                = trim($_POST['ncm'] ?? '');
    $cfop               = trim($_POST['cfop'] ?? '');
    $id_usuario         = $_SESSION['usuario_id'] ?? null;

    if (!$nome || !$id_usuario) {
        set_flash_message('warning', 'Preencha o nome do produto.');
        header("Location: ../pages/controle_estoque.php");
        exit;
    }

    try {
        // Query atualizada para incluir a coluna 'codigo'
        $query = "INSERT INTO produtos 
            (codigo, nome, descricao, quantidade_estoque, quantidade_minima, preco_compra, preco_venda, ncm, cfop, id_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        
        // Bind atualizado: 's' extra no início para o código
        $stmt->bind_param(
            "sssiiddssi",
            $codigo,
            $nome,
            $descricao,
            $quantidade,
            $quantidade_minima,
            $preco_compra,
            $preco_venda,
            $ncm,
            $cfop,
            $id_usuario
        );

        $stmt->execute();
        $stmt->close();

        set_flash_message('success', 'Produto cadastrado com sucesso!');

    } catch (Exception $e) {
        set_flash_message('danger', 'Erro ao cadastrar: ' . $e->getMessage());
    }
    
    header("Location: ../pages/controle_estoque.php");
    exit;
}

header("Location: ../pages/controle_estoque.php");
exit;
?>
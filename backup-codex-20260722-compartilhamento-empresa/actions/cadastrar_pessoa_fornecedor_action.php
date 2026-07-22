<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// 1. VERIFICA LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. VERIFICA POST E CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido.');
        header("Location: ../pages/cadastrar_pessoa_fornecedor.php");
        exit;
    }

    $conn = getTenantConnection();
    if ($conn === null) {
        set_flash_message('danger', "Falha na conexão com o banco.");
        header("Location: ../pages/cadastrar_pessoa_fornecedor.php");
        exit;
    }

    $id_usuario = $_SESSION['usuario_id']; 

    // Dados do formulário
    $nome = trim($_POST['nome']);
    $cpf_cnpj = trim($_POST['cpf_cnpj']);
    $endereco = trim($_POST['endereco']);
    $contato = trim($_POST['contato']);
    $email = trim($_POST['email']);
    $tipo = $_POST['tipo'];

    $stmt = $conn->prepare(
        "INSERT INTO pessoas_fornecedores 
        (id_usuario, nome, cpf_cnpj, endereco, contato, email, tipo) 
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issssss", $id_usuario, $nome, $cpf_cnpj, $endereco, $contato, $email, $tipo);

    if ($stmt->execute()) {
        set_flash_message('success', "Cadastro realizado com sucesso!");
    } else {
        set_flash_message('danger', "Erro ao cadastrar: " . $stmt->error);
    }

    $stmt->close();
    header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    exit;

} else {
    header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    exit;
}
?>
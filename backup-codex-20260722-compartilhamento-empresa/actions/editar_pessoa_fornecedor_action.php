<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido.');
        header("Location: ../pages/cadastrar_pessoa_fornecedor.php");
        exit;
    }

    $conn = getTenantConnection();
    if ($conn === null) {
        set_flash_message('danger', 'Erro de conexão.');
        header("Location: ../pages/cadastrar_pessoa_fornecedor.php");
        exit;
    }

    $id_registro = (int)$_POST['id'];
    $id_usuario = $_SESSION['usuario_id'];
    
    $nome = $_POST['nome'];
    $cpf_cnpj = $_POST['cpf_cnpj'];
    $endereco = $_POST['endereco'];
    $contato = $_POST['contato'];
    $email = $_POST['email'];
    $tipo = $_POST['tipo'];

    // Atualiza apenas se pertencer ao usuário logado
    $sql = "UPDATE pessoas_fornecedores SET nome = ?, cpf_cnpj = ?, endereco = ?, contato = ?, email = ?, tipo = ? WHERE id = ? AND id_usuario = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("ssssssii", $nome, $cpf_cnpj, $endereco, $contato, $email, $tipo, $id_registro, $id_usuario);
    
    if ($stmt->execute()) {
        set_flash_message('success', 'Cadastro atualizado com sucesso!');
        header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    } else {
        set_flash_message('danger', 'Erro ao atualizar: ' . $stmt->error);
        header("Location: ../pages/editar_pessoa_fornecedor.php?id=" . $id_registro);
    }
    $stmt->close();
    exit;
} else {
    header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    exit;
}
?>
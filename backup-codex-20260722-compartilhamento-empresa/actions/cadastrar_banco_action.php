<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token inválido.');
        header('Location: ../pages/banco_cadastro.php');
        exit;
    }

    $conn = getTenantConnection();
    if ($conn === null) {
        set_flash_message('danger', 'Erro de conexão.');
        header('Location: ../pages/banco_cadastro.php');
        exit;
    }

    $id_usuario = $_SESSION['usuario_id'];
    
    $nome_banco = $_POST['nome_banco'] ?? '';
    $agencia = $_POST['agencia'] ?? '';
    $conta = $_POST['conta'] ?? '';
    $tipo_conta = $_POST['tipo_conta'] ?? '';
    $chave_pix = $_POST['chave_pix'] ?? '';

    $stmt = $conn->prepare("INSERT INTO contas_bancarias (id_usuario, nome_banco, agencia, conta, tipo_conta, chave_pix) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("isssss", $id_usuario, $nome_banco, $agencia, $conta, $tipo_conta, $chave_pix);
        
        if ($stmt->execute()) {
            set_flash_message('success', 'Banco cadastrado com sucesso!');
        } else {
            set_flash_message('danger', 'Erro ao cadastrar.');
        }
        $stmt->close();
    } else {
        set_flash_message('danger', 'Erro técnico.');
    }
    
    $conn->close();
    header('Location: ../pages/banco_cadastro.php');
    exit;
} else {
    header('Location: ../pages/banco_cadastro.php');
    exit;
}
?>
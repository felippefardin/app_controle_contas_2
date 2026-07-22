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
    
    $id_usuario = $_SESSION['usuario_id'];
    $id = $_POST['id'] ?? 0;
    $nome_banco = $_POST['nome_banco'] ?? '';
    $agencia = $_POST['agencia'] ?? '';
    $conta = $_POST['conta'] ?? '';
    $tipo_conta = $_POST['tipo_conta'] ?? '';
    $chave_pix = $_POST['chave_pix'] ?? '';

    if (!empty($id) && !empty($id_usuario)) {
        $stmt = $conn->prepare("UPDATE contas_bancarias SET nome_banco=?, agencia=?, conta=?, tipo_conta=?, chave_pix=? WHERE id=? AND id_usuario=?");
        
        if ($stmt) {
            $stmt->bind_param("sssssii", $nome_banco, $agencia, $conta, $tipo_conta, $chave_pix, $id, $id_usuario);
            
            if ($stmt->execute()) {
                set_flash_message('success', 'Banco atualizado!');
                header('Location: ../pages/banco_cadastro.php');
            } else {
                set_flash_message('danger', 'Erro ao atualizar.');
                header("Location: ../pages/editar_banco.php?id=$id");
            }
            $stmt->close();
        } else {
            set_flash_message('danger', 'Erro técnico.');
            header("Location: ../pages/editar_banco.php?id=$id");
        }
    }
    $conn->close();
    exit;
} else {
    header('Location: ../pages/banco_cadastro.php');
    exit;
}
?>
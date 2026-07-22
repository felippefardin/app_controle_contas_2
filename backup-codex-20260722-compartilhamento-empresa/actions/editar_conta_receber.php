<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// 1. VERIFICA O LOGIN E PEGA A CONEXÃO
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php?error=not_logged_in');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getTenantConnection();
    if ($conn === null) {
        header("Location: ../pages/contas_receber.php?erro=db_connection");
        exit;
    }

    $id_usuario = $_SESSION['usuario_logado']['id'];
    $id = intval($_POST['id']);
    $responsavel = trim($_POST['responsavel']);
    $numero = trim($_POST['numero']);
    $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    $data_vencimento = $_POST['data_vencimento'];

    if ($id > 0 && !empty($responsavel) && is_numeric($valor)) {
        // 2. ATUALIZA A CONTA COM SEGURANÇA
        $stmt = $conn->prepare("UPDATE contas_receber SET responsavel = ?, numero = ?, valor = ?, data_vencimento = ? WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ssdsii", $responsavel, $numero, $valor, $data_vencimento, $id, $id_usuario);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Conta editada com sucesso!";
            header("Location: ../pages/contas_receber.php");
        } else {
            $_SESSION['error_message'] = "Erro ao atualizar a conta.";
            header("Location: ../pages/editar_conta_receber.php?id={$id}");
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Dados inválidos.";
        header("Location: ../pages/editar_conta_receber.php?id={$id}");
    }
    exit;
} else {
    header("Location: ../pages/contas_receber.php");
    exit;
}
?>
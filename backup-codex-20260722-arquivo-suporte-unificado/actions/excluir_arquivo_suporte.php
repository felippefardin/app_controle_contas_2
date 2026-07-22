<?php
// actions/excluir_arquivo_suporte.php

require_once '../includes/session_init.php';
require_once '../database.php';

// Verifica se a requisição é POST e se o ID foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $conn = getMasterConnection();
    $id = intval($_POST['id']);

    // Verifica se o usuário tem permissão (opcional, dependendo da sua lógica de session_init)
    // if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') { 
    //     header("Location: ../pages/admin/arquivos_suportes.php?msg=erro_permissao");
    //     exit;
    // }

    // 1. Excluir mensagens associadas a este chat (para evitar erros de integridade se não houver CASCADE)
    $sqlMessages = "DELETE FROM chat_messages WHERE chat_session_id = ?";
    $stmtMsg = $conn->prepare($sqlMessages);
    
    if ($stmtMsg) {
        $stmtMsg->bind_param("i", $id);
        $stmtMsg->execute();
        $stmtMsg->close();
    }

    // 2. Excluir a sessão de chat (o arquivo de suporte em si)
    $sqlSession = "DELETE FROM chat_sessions WHERE id = ?";
    $stmt = $conn->prepare($sqlSession);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Sucesso
            header("Location: ../pages/admin/arquivos_suportes.php?msg=sucesso_exclusao");
        } else {
            // Erro na execução
            header("Location: ../pages/admin/arquivos_suportes.php?msg=erro_bd");
        }
        $stmt->close();
    } else {
        // Erro na preparação da query
        header("Location: ../pages/admin/arquivos_suportes.php?msg=erro_prepare");
    }

    $conn->close();
} else {
    // Se tentar acessar direto via URL sem POST
    header("Location: ../pages/admin/arquivos_suportes.php");
}
exit;
?>
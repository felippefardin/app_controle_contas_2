<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// Verifica se é Super Admin
if (!isset($_SESSION['super_admin'])) {
    die("Acesso negado.");
}

$connMaster = getMasterConnection();
$admin_nome = $_SESSION['super_admin']['nome'] ?? 'Suporte Master';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        if ($acao === 'salvar_notas') {
            $nova_mensagem = trim($_POST['nova_mensagem'] ?? '');
            
            if (!empty($nova_mensagem)) {
                // 1. Insere a nova interação no histórico
                $stmtHist = $connMaster->prepare("INSERT INTO chamados_historico (chamado_id, autor_tipo, autor_nome, mensagem) VALUES (?, 'admin', ?, ?)");
                $stmtHist->bind_param("iss", $id, $admin_nome, $nova_mensagem);
                $stmtHist->execute();
                $stmtHist->close();

                // 2. Atualiza o status do chamado principal se estiver 'aberto'
                $stmtUpdate = $connMaster->prepare("UPDATE chamados_suporte SET status = IF(status='aberto', 'em_atendimento', status) WHERE id = ?");
                $stmtUpdate->bind_param("i", $id);
                $stmtUpdate->execute();
                
                $_SESSION['msg_suporte'] = "Atualização registrada com sucesso!";
            }
        }
        
        elseif ($acao === 'resolver') {
            // Marca como concluído e adiciona uma nota automática no histórico
            $stmt = $connMaster->prepare("UPDATE chamados_suporte SET status = 'concluido' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Opcional: Registrar no histórico que foi resolvido
            $msg_resolvido = "Chamado marcado como resolvido pelo suporte.";
            $stmtHist = $connMaster->prepare("INSERT INTO chamados_historico (chamado_id, autor_tipo, autor_nome, mensagem) VALUES (?, 'admin', 'Sistema', ?)");
            $stmtHist->bind_param("is", $id, $msg_resolvido);
            $stmtHist->execute();

            $_SESSION['msg_suporte'] = "Chamado marcado como resolvido!";
        }
        
        elseif ($acao === 'excluir') {
            // O DELETE CASCADE no banco cuidará do histórico
            $stmt = $connMaster->prepare("DELETE FROM chamados_suporte WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $_SESSION['msg_suporte'] = "Chamado excluído com sucesso!";
        }
    }
}

// Redireciona de volta
$origem = $_POST['origem'] ?? 'dashboard';
if ($origem === 'resolvidos') {
    header('Location: ../pages/admin/chamados_resolvidos.php');
} else {
    header('Location: ../pages/admin/dashboard.php');
}
exit;
?>
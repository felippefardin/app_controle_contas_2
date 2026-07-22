<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// ---------------------------------------------------------
// CORREÇÃO DE PERMISSÃO
// ---------------------------------------------------------
// Verifica se existe a sessão de Super Admin OU se o nível de acesso é permitido
$is_super_admin = isset($_SESSION['super_admin']);
$is_nivel_permitido = isset($_SESSION['nivel_acesso']) && in_array($_SESSION['nivel_acesso'], ['admin', 'master']);

if (!$is_super_admin && !$is_nivel_permitido) {
    // Se não for nenhum dos dois, nega
    echo json_encode(['status' => 'error', 'msg' => 'Acesso negado. Nível insuficiente.']);
    exit;
}
// ---------------------------------------------------------

header('Content-Type: application/json');
$conn = getMasterConnection();
$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'atualizar_status') {
        $id = intval($_POST['id']);
        $novoStatus = $_POST['status']; // 'em_andamento', 'resolvido', 'fechado'
        
        $sql = "UPDATE suporte_login SET status = ?";
        if ($novoStatus === 'resolvido' || $novoStatus === 'fechado') {
            $sql .= ", resolvido_em = NOW()";
        }
        $sql .= " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $novoStatus, $id);
        
        if($stmt->execute()) {
            // Log no histórico
            $msg = "Status alterado para: " . ucfirst(str_replace('_', ' ', $novoStatus));
            $conn->query("INSERT INTO suporte_historico (suporte_id, mensagem, tipo) VALUES ($id, '$msg', 'sistema')");
            echo json_encode(['status' => 'success']);
        } else {
            throw new Exception($stmt->error);
        }

    } elseif ($acao === 'adicionar_historico') {
        $id = intval($_POST['id']);
        $mensagem = trim($_POST['mensagem']);
        
        if (!empty($mensagem)) {
            $stmt = $conn->prepare("INSERT INTO suporte_historico (suporte_id, mensagem, tipo) VALUES (?, ?, 'suporte')");
            $stmt->bind_param("is", $id, $mensagem);
            $stmt->execute();
        }
        echo json_encode(['status' => 'success']);

    } elseif ($acao === 'buscar_detalhes') {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("SELECT * FROM suporte_login WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $chamado = $stmt->get_result()->fetch_assoc();
        
        if(!$chamado) throw new Exception("Chamado não encontrado.");

        $resHist = $conn->query("SELECT * FROM suporte_historico WHERE suporte_id = $id ORDER BY criado_em ASC");
        $historico = $resHist->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['status' => 'success', 'dados' => $chamado, 'historico' => $historico]);

    } elseif ($acao === 'excluir') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM suporte_login WHERE id = $id");
        // O CASCADE no banco cuida do histórico, mas por segurança:
        $conn->query("DELETE FROM suporte_historico WHERE suporte_id = $id");
        echo json_encode(['status' => 'success']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
?>
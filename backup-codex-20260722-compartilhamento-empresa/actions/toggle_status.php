<?php
// actions/toggle_status.php
require_once '../includes/session_init.php';
require_once '../database.php';

// Verifica se o usuário está logado e tem permissão
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

$nivel = $_SESSION['nivel_acesso'] ?? 'padrao';
if ($nivel !== 'admin' && $nivel !== 'master' && $nivel !== 'proprietario') {
    header('Location: ../pages/usuarios.php?erro=1&msg=Sem permissão');
    exit;
}

$id_usuario_alvo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$id_usuario_logado = $_SESSION['usuario_id'];

if (!$id_usuario_alvo) {
    header('Location: ../pages/usuarios.php?erro=1&msg=ID inválido');
    exit;
}

// Impede auto-inativação
if ($id_usuario_alvo == $id_usuario_logado) {
    header('Location: ../pages/usuarios.php?erro=1&msg=Você não pode inativar seu próprio usuário');
    exit;
}

$conn = getTenantConnection();

try {
    // Busca status atual
    $stmt = $conn->prepare("SELECT status FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_usuario_alvo);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Inverte o status
        $novo_status = ($user['status'] === 'ativo') ? 'inativo' : 'ativo';
        
        $update = $conn->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
        $update->bind_param("si", $novo_status, $id_usuario_alvo);
        
        if ($update->execute()) {
            $msg = ($novo_status === 'ativo') ? 'Usuário ativado com sucesso' : 'Usuário inativado com sucesso';
            header("Location: ../pages/usuarios.php?sucesso=1&msg=$msg");
        } else {
            throw new Exception("Erro ao atualizar status");
        }
        $update->close();
    } else {
        header('Location: ../pages/usuarios.php?erro=1&msg=Usuário não encontrado');
    }

} catch (Exception $e) {
    header('Location: ../pages/usuarios.php?erro=1&msg=' . $e->getMessage());
}

$conn->close();
exit;
?>
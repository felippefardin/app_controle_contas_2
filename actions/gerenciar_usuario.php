<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/audit.php';
require_tenant_admin();
require_csrf();

header('Content-Type: application/json');

// 1. Verifica Login e Permissões
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

$nivel = $_SESSION['nivel_acesso'] ?? 'padrao';
if (!in_array($nivel, ['admin', 'master', 'proprietario'], true)) {
    echo json_encode(['success' => false, 'message' => 'Permissão negada.']);
    exit;
}

$conn = getTenantConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco.']);
    exit;
}

$acao = $_POST['acao'] ?? '';
$id_usuario_atual = $_SESSION['usuario_id'];
$ownerId = get_data_owner_id();

try {
    // --- LÓGICA DE SALVAR (INSERIR OU EDITAR) ---
    if ($acao === 'salvar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $tipo = $_POST['tipo'] === 'admin' ? 'admin' : 'padrao';
        $senha = $_POST['senha'];

        // Validações básicas
        if (empty($nome) || empty($email)) {
            throw new Exception("Nome e E-mail são obrigatórios.");
        }

        // Verifica se e-mail já existe (excluindo o próprio usuário na edição)
        $sql_check = "SELECT id FROM usuarios WHERE email = ? AND id != ? AND deleted_at IS NULL";
        $check_id = $id ? $id : 0;
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("si", $email, $check_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Este e-mail já está em uso.");
        }

        if ($id && $id === $ownerId) {
            throw new Exception('O usuário proprietário não pode ser alterado por esta tela.');
        }
        if ($id) {
            // --- ATUALIZAR EXISTENTE ---
            if (!empty($senha)) {
                // Se forneceu senha nova
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, tipo=?, senha=? WHERE id=? AND deleted_at IS NULL");
                $stmt->bind_param("ssssi", $nome, $email, $tipo, $hash, $id);
            } else {
                // Mantém a senha antiga
                $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, tipo=? WHERE id=? AND deleted_at IS NULL");
                $stmt->bind_param("sssi", $nome, $email, $tipo, $id);
            }
            $msg = "Usuário atualizado com sucesso!";
        } else {
            // --- INSERIR NOVO ---
            if (empty($senha)) {
                throw new Exception("A senha é obrigatória para novos usuários.");
            }
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            // Define campos padrão
            $status = 'ativo';
            $perfil = 'padrao'; // Perfil geral, o 'tipo' define se é admin do sistema
            
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, perfil, status, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            // Assume que o criador é o 'owner' dos sub-usuários
            $stmt->bind_param("ssssssi", $nome, $email, $hash, $tipo, $perfil, $status, $id_usuario_atual);
            $msg = "Usuário criado com sucesso!";
        }

        if (!$stmt->execute()) {
            throw new Exception("Erro ao salvar no banco: " . $stmt->error);
        }

        echo json_encode(['success' => true, 'message' => $msg]);
    }

    // --- LÓGICA DE EXCLUIR ---
    elseif ($acao === 'excluir') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id == $id_usuario_atual || $id == $ownerId) {
            throw new Exception("ID inválido ou tentativa de excluir a si mesmo.");
        }

        if (soft_delete_record($conn, 'usuarios', (int) $id)) {
            echo json_encode(['success' => true, 'message' => 'Usuário movido para a lixeira.']);
        } else {
            throw new Exception("Erro ao mover usuário para a lixeira.");
        }
    }

    // --- LÓGICA DE TOGGLE STATUS (ATIVAR/DESATIVAR) ---
    elseif ($acao === 'toggle_status') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id == $id_usuario_atual || $id == $ownerId) {
            throw new Exception("Operação inválida.");
        }

        // Inverte o status
        $sql = "UPDATE usuarios SET status = IF(status='ativo', 'inativo', 'ativo') WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status alterado.']);
        } else {
            throw new Exception("Erro ao alterar status.");
        }
    } else {
        throw new Exception("Ação desconhecida.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

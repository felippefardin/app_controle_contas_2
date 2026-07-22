<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importante para Flash Messages

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// Verifica permissão
$nivel = $_SESSION['nivel_acesso'] ?? 'padrao';
if ($nivel !== 'admin' && $nivel !== 'master' && $nivel !== 'proprietario') {
    set_flash_message('danger', 'Você não tem permissão para adicionar usuários.');
    header('Location: ../pages/usuarios.php');
    exit;
}

$conn = getTenantConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $senha = $_POST['senha'];
    $senha_conf = $_POST['senha_confirmar'];
    $nivel_novo = $_POST['nivel'] === 'admin' ? 'admin' : 'padrao';
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    $criador = $_SESSION['usuario_id'];

    // Tratamento das permissões
    $json_permissoes = null;
    if ($nivel_novo === 'padrao' && isset($_POST['permissoes']) && is_array($_POST['permissoes'])) {
        $json_permissoes = json_encode($_POST['permissoes']);
    }

    // Validações
    if ($senha !== $senha_conf) {
        set_flash_message('warning', 'As senhas não conferem.');
        header('Location: ../pages/add_usuario.php');
        exit;
    }

    // Verifica email duplicado
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        set_flash_message('danger', 'Este e-mail já está cadastrado.');
        header('Location: ../pages/add_usuario.php');
        exit;
    }

    // Insere
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO usuarios (nome, email, cpf, senha, nivel_acesso, status, tenant_id, criado_por_usuario_id, permissoes) VALUES (?, ?, ?, ?, ?, 'ativo', ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("ssssssis", $nome, $email, $cpf, $senha_hash, $nivel_novo, $tenant_id, $criador, $json_permissoes);

    if ($stmt->execute()) {
        set_flash_message('success', "Usuário <b>$nome</b> criado com sucesso!");
        header('Location: ../pages/usuarios.php');
    } else {
        set_flash_message('danger', 'Erro ao salvar no banco: ' . $conn->error);
        header('Location: ../pages/add_usuario.php');
    }
    exit;
}
?>
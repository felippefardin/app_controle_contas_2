<?php
require_once '../includes/session_init.php';
include('../database.php');
require_csrf();

// ❗️ CORREÇÃO 1: Verificar se o usuário está logado (se a sessão é 'true')
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
$_SESSION['login_erro'] = "Sessão de usuário não encontrada. Faça o login novamente.";
header('Location: ../pages/login.php');
 exit;
}

// Obtém a conexão com o banco de dados do tenant.
$conn = getTenantConnection();
if ($conn === null) {
 die("Falha ao obter a conexão com o banco de dados do cliente.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id']) && isset($_POST['senha'])) {
 $usuario_selecionado_id = (int)$_POST['usuario_id'];
 $senha_fornecida = $_POST['senha'];

// Busca o usuário selecionado no banco de dados do tenant.
// Garantir que estamos pegando todos os campos necessários
$sql = "SELECT * FROM usuarios WHERE id = ?"; 
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $usuario_selecionado_id);
$stmt->execute();
$result = $stmt->get_result();

if ($usuario_selecionado = $result->fetch_assoc()) {
// Verifica se a senha fornecida corresponde à do banco de dados.
if (password_verify($senha_fornecida, $usuario_selecionado['senha'])) {
// ❗️ CORREÇÃO 2: Salvar os dados do usuário ATUAL (antes da troca)
 if (!isset($_SESSION['usuario_principal'])) {
 $_SESSION['usuario_principal'] = [
 'id'  => $_SESSION['usuario_id'],
 'nome'  => $_SESSION['nome'],
 'email'=> $_SESSION['email'],
 'nivel_acesso' => $_SESSION['nivel_acesso']
];

// Esta sessão ativa o banner no 'header.php'
 $_SESSION['proprietario_id_original'] = $_SESSION['usuario_id'];
}
 
// ❗️ CORREÇÃO 3: Atualizar as chaves de sessão individuais (A CORREÇÃO PRINCIPAL)
            // Nós NÃO mexemos em $_SESSION['usuario_logado'] (que deve permanecer 'true').
            // Nós atualizamos as chaves individuais que o home.php espera.
 $_SESSION['usuario_id'] = $usuario_selecionado['id'];
 $_SESSION['nome']  = $usuario_selecionado['nome'];
 $_SESSION['email']= $usuario_selecionado['email']; // O e-mail do usuário do tenant
 $_SESSION['nivel_acesso'] = $usuario_selecionado['nivel_acesso'];
 $permissoes = json_decode((string)($usuario_selecionado['permissoes'] ?? '[]'), true);
 $_SESSION['permissoes'] = is_array($permissoes) ? $permissoes : [];
 session_regenerate_id(true);
 $_SESSION['session_regenerated_at'] = time();
            // $_SESSION['usuario_logado'] continua a ser 'true' (como definido no login.php)

 header('Location: ../pages/home.php');
 exit;
 }
 }
}

// Se a autenticação falhar, redireciona de volta com uma mensagem de erro.
$_SESSION['erro_selecao'] = "Dados incorretos, não foi possível trocar de usuário.";
header('Location: ../pages/selecionar_usuario.php');
exit;
?>

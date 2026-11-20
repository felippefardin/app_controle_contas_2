<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// Define o fuso horário para comparar datas
date_default_timezone_set('America/Sao_Paulo');

$mensagem_erro = '';
$token = '';
$tenant_db_name = '';
$usuario = null;
$conn = null;

// ✅ **INÍCIO DA LÓGICA DE VALIDAÇÃO (NOVA)** 
if (empty($_GET['payload'])) {
    $mensagem_erro = 'Link inválido ou incompleto (cód 1).';
} else {
    // Decodifica o payload
    $payload = json_decode(base64_decode(urldecode($_GET['payload'])), true);

    if (empty($payload['token']) || empty($payload['tenant'])) {
        $mensagem_erro = 'Link inválido ou incompleto (cód 2).';
    } else {
        $token = $payload['token'];
        $tenant_db_name = $payload['tenant'];

        // Tenta conectar ao banco do tenant usando a nova função
        $conn = getTenantConnectionByName($tenant_db_name);

        if ($conn === null) {
            $mensagem_erro = 'Não foi possível conectar ao banco de dados. Contate o suporte.';
        } else {
            // Verifica o token no banco do tenant
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE token_reset = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $usuario = $result->fetch_assoc();
            $stmt->close();

            if (!$usuario) {
                $mensagem_erro = 'Token de redefinição inválido.';
            } else {
                // Verifica a expiração
                $agora = new DateTime();
                $expiracao = new DateTime($usuario['token_expira_em']);

                if ($agora > $expiracao) {
                    $mensagem_erro = 'Este link de redefinição expirou. Solicite um novo.';
                    // Limpa o token expirado
                    $conn->query("UPDATE usuarios SET token_reset = NULL, token_expira_em = NULL WHERE id = " . $usuario['id']);
                    $usuario = null; // Impede a exibição do formulário
                }
            }
        }
    }
}

// Processar o formulário de redefinição (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario && $conn) {
    $senha = $_POST['senha'];
    $senha_confirmar = $_POST['senha_confirmar'];

    if ($senha !== $senha_confirmar) {
        $mensagem_erro = 'As senhas não conferem.';
    } elseif (strlen($senha) < 6) {
        $mensagem_erro = 'A senha deve ter pelo menos 6 caracteres.';
    } else {
        // Tudo certo, atualiza a senha
        $nova_senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE usuarios SET senha = ?, token_reset = NULL, token_expira_em = NULL WHERE id = ?");
        $stmt->bind_param("si", $nova_senha_hash, $usuario['id']);
        
        if ($stmt->execute()) {
            $conn->close();
            // Redireciona para o login com mensagem de sucesso
            $_SESSION['sucesso_login'] = 'Senha redefinida com sucesso! Você já pode fazer login.';
            header('Location: login.php');
            exit;
        } else {
            $mensagem_erro = 'Erro ao atualizar a senha. Tente novamente.';
        }
        $stmt->close();
    }
    if ($conn) $conn->close();
}
// ✅ **FIM DA LÓGICA DE VALIDAÇÃO (NOVA)**
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
    <meta charset="UTF-8">
    <title>Redefinir Senha</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #121212; color: #eee; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .reset-container { padding: 40px; background-color: #1e1e1e; border-radius: 10px; box-shadow: 0 0 20px rgba(0, 191, 255, 0.2); text-align: center; width: 100%; max-width: 400px; }
        h2 { color: #00bfff; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; text-align: left; position: relative; }
        label { display: block; margin-bottom: 8px; color: #bbb; }
        input[type="password"] { width: 100%; padding: 12px; padding-right: 40px; border-radius: 5px; border: 1px solid #444; background-color: #333; color: #eee; box-sizing: border-box; }
        .toggle-password { position: absolute; top: 37px; right: 12px; color: #aaa; cursor: pointer; }
        button { width: 100%; padding: 12px; border: none; border-radius: 5px; background-color: #00bfff; color: #121212; font-weight: bold; cursor: pointer; transition: background-color 0.3s ease; }
        button:hover { background-color: #0095cc; }
        .mensagem-erro { background-color: #dc3545; padding: 10px; border-radius: 5px; margin-bottom: 20px; color: white; }
        .link-login { color: #00bfff; display: block; margin-top: 20px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Redefinir Senha</h2>

        <?php if ($mensagem_erro): ?>
            <div class="mensagem-erro"><?= htmlspecialchars($mensagem_erro); ?></div>
        <?php endif; ?>

        <?php // Só exibe o formulário se o token for válido e o usuário encontrado ?>
        <?php if ($usuario): ?>
            <p style="color: #bbb;">Olá, <?= htmlspecialchars($usuario['nome']) ?>. Defina sua nova senha.</p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="senha">Nova Senha:</label>
                    <input type="password" name="senha" id="senha" required>
                    <i class="fas fa-eye toggle-password" id="toggleSenha"></i>
                </div>
                <div class="form-group">
                    <label for="senha_confirmar">Confirmar Nova Senha:</label>
                    <input type="password" name="senha_confirmar" id="senha_confirmar" required>
                    <i class="fas fa-eye toggle-password" id="toggleSenhaConfirmar"></i>
                </div>
                <button type="submit">Salvar Nova Senha</button>
            </form>
        <?php else: ?>
            <a href="login.php" class="link-login">Voltar para o Login</a>
        <?php endif; ?>
    </div>

    <script>
        // Script para alternar visibilidade da senha (opcional, mas bom)
        function setupToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            if (toggle && input) {
                toggle.addEventListener('click', () => {
                    const tipo = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', tipo);
                    toggle.classList.toggle('fa-eye');
                    toggle.classList.toggle('fa-eye-slash');
                });
            }
        }
        setupToggle('toggleSenha', 'senha');
        setupToggle('toggleSenhaConfirmar', 'senha_confirmar');
    </script>
</body>
</html>
<?php
require_once '../../includes/session_init.php';
require_once '../../database.php';

// 🔒 Verifica se é super admin logado
// Lógica ajustada para permitir qualquer admin mestre, não apenas o email hardcoded
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$mensagem = '';
$classeMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $senhaAtual = trim($_POST['senha_atual']);
    $novaSenha = trim($_POST['nova_senha']);
    $confirmar = trim($_POST['confirmar_senha']);

    $master = getMasterConnection();
    // Usa o email da sessão atual
    $email = $_SESSION['super_admin']['email']; 

    // Busca o admin no banco
    $stmt = $master->prepare("SELECT senha FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (!$admin || !password_verify($senhaAtual, $admin['senha'])) {
        $mensagem = 'Senha atual incorreta.';
        $classeMsg = 'erro';
    } elseif (strlen($novaSenha) < 6) {
        $mensagem = 'A nova senha deve ter pelo menos 6 caracteres.';
        $classeMsg = 'erro';
    } elseif ($novaSenha !== $confirmar) {
        $mensagem = 'A confirmação não corresponde à nova senha.';
        $classeMsg = 'erro';
    } else {
        // Atualiza senha no banco
        $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $update = $master->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $update->bind_param("ss", $novoHash, $email);
        
        if($update->execute()) {
            $mensagem = 'Senha atualizada com sucesso!';
            $classeMsg = 'sucesso';
        } else {
            $mensagem = 'Erro ao atualizar no banco de dados.';
            $classeMsg = 'erro';
        }
        $update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<?php session_idle_script(); ?>
<link rel="stylesheet" href="../../assets/css/universal-theme.css?v=20260813-admin">
<script src="../../assets/js/global-theme.js?v=20260813-admin"></script>
    <?php require __DIR__ . '/../../includes/csrf_client.php'; ?>
    <meta charset="UTF-8">
    <title>Redefinir Senha - Super Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #121212; color: #eee; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin:0; }
        form { background-color: #1f1f1f; padding: 25px; border-radius: 10px; box-shadow: 0 0 15px #00bfff; width: 400px; display: flex; flex-direction: column; }
        h2 { text-align: center; color: #00bfff; margin-bottom: 20px; }
        label { margin-top: 10px; font-weight: bold; margin-bottom: 5px; }
        
        /* Estilos do Input e Toggle */
        .input-group {
            position: relative;
            width: 100%;
            margin-top: 5px;
        }
        .input-group input {
            width: 100%;
            padding: 10px;
            padding-right: 40px; /* Espaço para o ícone */
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            background: #2c2c2c;
            color: #fff;
            box-sizing: border-box; /* Garante que o padding não quebre a largura */
        }
        .input-group input:focus {
            outline: 2px solid #00bfff;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #00bfff;
        }

        button { margin-top: 25px; padding: 12px; background-color: #00bfff; border: none; border-radius: 5px; color: white; font-weight: bold; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; }
        button:hover { background-color: #008ccc; }
        .mensagem { padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        .erro { background-color: #cc4444; color: white; }
        .sucesso { background-color: #4CAF50; color: white; }
        a { color: #00bfff; text-decoration: none; text-align: center; margin-top: 15px; display:block; }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Redefinir Senha Master</h2>
        <p style="text-align: center; font-size: 0.9em; color: #aaa;">
            Logado como: <?= htmlspecialchars($_SESSION['super_admin']['email']) ?>
        </p>

        <?php if ($mensagem): ?>
            <div class="mensagem <?= $classeMsg ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <label for="senha_atual">Senha Atual</label>
        <div class="input-group">
            <input type="password" id="senha_atual" name="senha_atual" required placeholder="Digite sua senha atual">
            <i class="fas fa-eye toggle-password" onclick="togglePassword('senha_atual', this)"></i>
        </div>

        <label for="nova_senha">Nova Senha</label>
        <div class="input-group">
            <input type="password" id="nova_senha" name="nova_senha" required placeholder="Mínimo 6 caracteres">
            <i class="fas fa-eye toggle-password" onclick="togglePassword('nova_senha', this)"></i>
        </div>

        <label for="confirmar_senha">Confirmar Nova Senha</label>
        <div class="input-group">
            <input type="password" id="confirmar_senha" name="confirmar_senha" required placeholder="Repita a nova senha">
            <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmar_senha', this)"></i>
        </div>

        <button type="submit">Atualizar Senha</button>

        <a href="dashboard.php">← Voltar ao Painel</a>
    </form>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>

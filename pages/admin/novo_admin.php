<?php
require_once '../../includes/session_init.php';
require_once '../../database.php';

// 🔒 Apenas Super Admin pode criar outro
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$mensagem = '';
$classeMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    if (strlen($senha) < 6) {
        $mensagem = 'A senha deve ter pelo menos 6 caracteres.';
        $classeMsg = 'erro';
    } else {
        $master = getMasterConnection();
        
        // Verifica se email já existe
        $check = $master->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensagem = 'Este e-mail já está cadastrado.';
            $classeMsg = 'erro';
        } else {
            // Cria o novo admin
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            
            // Configurações para SUPER ADMIN baseadas no seu schema.sql:
            // is_master = 1, perfil = 'admin', tipo = 'admin'
            $sql = "INSERT INTO usuarios (nome, email, senha, perfil, tipo, role, is_master, criado_em) 
                    VALUES (?, ?, ?, 'admin', 'admin', 'super_admin', 1, NOW())";
            
            $stmt = $master->prepare($sql);
            $stmt->bind_param("sss", $nome, $email, $senhaHash);

            if ($stmt->execute()) {
                $mensagem = 'Novo Super Admin criado com sucesso!';
                $classeMsg = 'sucesso';
            } else {
                $mensagem = 'Erro ao criar usuário: ' . $master->error;
                $classeMsg = 'erro';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="stylesheet" href="../../assets/css/universal-theme.css?v=20260813-admin">
<script src="../../assets/js/global-theme.js?v=20260813-admin"></script>
    <?php require __DIR__ . '/../../includes/csrf_client.php'; ?>
    <meta charset="UTF-8">
    <title>Novo Super Admin</title>
    <style>
        body { background-color: #121212; color: #eee; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        form { background-color: #1f1f1f; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0, 191, 255, 0.2); width: 400px; display: flex; flex-direction: column; }
        h2 { text-align: center; color: #00bfff; margin-bottom: 20px; }
        label { margin-top: 15px; font-weight: bold; color: #ccc; }
        input { padding: 12px; margin-top: 5px; border: 1px solid #333; background: #2c2c2c; color: white; border-radius: 5px; font-size: 1rem; }
        input:focus { outline: 1px solid #00bfff; }
        button { margin-top: 25px; padding: 12px; background-color: #28a745; border: none; border-radius: 5px; color: white; font-weight: bold; font-size: 1rem; cursor: pointer; transition: 0.2s; }
        button:hover { background-color: #218838; }
        .mensagem { padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        .erro { background-color: #cc4444; color: white; }
        .sucesso { background-color: #4CAF50; color: white; }
        a { color: #aaa; text-decoration: none; text-align: center; margin-top: 20px; font-size: 0.9rem; }
        a:hover { color: #fff; }
    </style>
</head>
<body>
    <form method="POST">
        <h2><i class="fas fa-user-plus"></i> Novo Super Admin</h2>
        
        <?php if ($mensagem): ?>
            <div class="mensagem <?= $classeMsg ?>"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <label for="nome">Nome Completo</label>
        <input type="text" id="nome" name="nome" required placeholder="Ex: Fulano Admin">

        <label for="email">E-mail de Acesso</label>
        <input type="email" id="email" name="email" required placeholder="admin@exemplo.com">

        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required placeholder="Mínimo 6 caracteres">

        <button type="submit">Cadastrar Admin</button>

        <a href="dashboard.php">Cancelar e Voltar</a>
    </form>
</body>
</html>

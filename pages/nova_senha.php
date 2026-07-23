<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../vendor/autoload.php';
require_once '../includes/utils.php'; // Importa Flash Messages

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('America/Sao_Paulo');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token_valido = false;
$email_usuario = '';

$conn = getMasterConnection();
if (!$conn) {
    die("Erro crítico: Não foi possível conectar ao banco de dados principal.");
}

// 1. VERIFICA TOKEN
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, nome, email, tenant_id FROM usuarios WHERE token_reset = ? AND token_expira_em > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $token_valido = true;
        $stmt->bind_result($id_usuario, $nome_usuario, $email_usuario, $tenant_id_str);
        $stmt->fetch();
    } else {
        // Token inválido ou expirado
        set_flash_message('danger', 'Este link de redefinição expirou ou é inválido.');
        header('Location: login.php');
        exit;
    }
    $stmt->close();
} else {
    set_flash_message('danger', 'Token não encontrado.');
    header('Location: login.php');
    exit;
}

// 2. PROCESSA TROCA DE SENHA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    // --- AJUSTE AQUI: Adicionado trim() para remover espaços ---
    $senha = trim($_POST['senha'] ?? '');
    $senha_confirmar = trim($_POST['senha_confirmar'] ?? '');
    // ---------------------------------------------------------

    if (empty($senha) || empty($senha_confirmar)) {
        set_flash_message('warning', 'Preencha todos os campos.');
    } elseif ($senha !== $senha_confirmar) {
        set_flash_message('warning', 'As senhas não coincidem.');
    } elseif (strlen($senha) < 6) {
        set_flash_message('warning', 'A senha deve ter pelo menos 6 caracteres.');
    } else {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // A. Atualiza Master
        $stmt_up = $conn->prepare("UPDATE usuarios SET senha = ?, token_reset = NULL, token_expira_em = NULL WHERE id = ?");
        $stmt_up->bind_param("si", $senha_hash, $id_usuario);

        if ($stmt_up->execute()) {
            
            // B. Atualiza Tenant (Sincronia)
            if (!empty($tenant_id_str)) {
                $stmt_creds = $conn->prepare("SELECT db_host, db_user, db_password, db_database FROM tenants WHERE tenant_id = ?");
                $stmt_creds->bind_param("s", $tenant_id_str);
                $stmt_creds->execute();
                $res_creds = $stmt_creds->get_result();
                
                if ($tenant_data = $res_creds->fetch_assoc()) {
                    try {
                        $connTenant = new mysqli($tenant_data['db_host'], $tenant_data['db_user'], $tenant_data['db_password'], $tenant_data['db_database']);
                        if (!$connTenant->connect_error) {
                            $stmt_t = $connTenant->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
                            $stmt_t->bind_param("ss", $senha_hash, $email_usuario);
                            $stmt_t->execute();
                            $stmt_t->close();
                            $connTenant->close();
                        }
                    } catch (Exception $e) {}
                }
                $stmt_creds->close();
            }

            // C. Email Confirmação
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $_ENV['MAIL_HOST'] ?? '';
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
                $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
                $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
                $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? '', $_ENV['MAIL_FROM_NAME'] ?? 'App Controle');
                $mail->addAddress($email_usuario);
                $mail->isHTML(true);
                $mail->Subject = 'Senha Alterada';
                $mail->Body = "<p>Sua senha foi alterada com sucesso.</p>";
                $mail->send();
            } catch (Exception $e) {}

            set_flash_message('success', 'Senha alterada com sucesso! Faça login agora.');
            header('Location: login.php');
            exit;
        } else {
            set_flash_message('danger', 'Erro ao atualizar senha.');
        }
        $stmt_up->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
<meta charset="UTF-8" />
<title>Nova Senha</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body {
        display: flex; justify-content: center; align-items: center;
        min-height: 100vh; background-color: #121212; color: #eee;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0;
    }
    form {
        padding: 40px; background-color: #1e1e1e; border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 191, 255, 0.2); width: 100%; max-width: 400px;
    }
    h2 { color: #00bfff; margin-bottom: 25px; text-align: center; }
    p.info { text-align: center; color: #aaa; margin-bottom: 20px; }
    label { display: block; margin-bottom: 8px; color: #bbb; }
    input[type="password"] {
        width: 100%; padding: 12px; border-radius: 5px; border: 1px solid #444;
        background-color: #333; color: #eee; box-sizing: border-box; margin-bottom: 15px; transition: border 0.3s;
    }
    input[type="password"]:focus { border-color: #00bfff; outline: none; }
    .password-container { position: relative; }
    .toggle-password { position: absolute; top: 12px; right: 12px; color: #aaa; cursor: pointer; }
    .senha-forca { height: 6px; background: #444; border-radius: 4px; margin-top: -10px; margin-bottom: 15px; overflow: hidden; }
    .senha-forca > div { height: 100%; width: 0; transition: width 0.3s ease, background-color 0.3s ease; }
    button {
        width: 100%; padding: 12px; border: none; border-radius: 5px;
        background-color: #00bfff; color: #121212; font-weight: bold; cursor: pointer; margin-top: 10px;
    }
    button:hover { background-color: #0095cc; }
    .center-link { text-align: center; display: block; margin-top: 15px; }
    a { color: #00bfff; text-decoration: none; }
</style>
</head>
<body>

<?php display_flash_message(); ?>

<div style="width: 100%; max-width: 400px;">
    <form method="POST" novalidate>
        <h2>Redefinir Senha</h2>
        <p class="info">Defina uma nova senha para <strong><?= htmlspecialchars($email_usuario) ?></strong></p>

        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <label for="senha">Nova senha:</label>
        <div class="password-container">
            <input type="password" id="senha" name="senha" required placeholder="Mínimo 6 caracteres">
            <i class="fas fa-eye toggle-password" onclick="toggleSenha('senha')"></i>
        </div>
        <div class="senha-forca"><div id="barra-forca"></div></div>

        <label for="senha_confirmar">Confirmar senha:</label>
        <div class="password-container">
            <input type="password" id="senha_confirmar" name="senha_confirmar" required placeholder="Repita a nova senha">
            <i class="fas fa-eye toggle-password" onclick="toggleSenha('senha_confirmar')"></i>
        </div>

        <button type="submit">Salvar Nova Senha</button>
        <div class="center-link"><a href="login.php">Cancelar</a></div>
    </form>
</div>

<script>
  function toggleSenha(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling;
    input.type = input.type === "password" ? "text" : "password";
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
  }

  const senhaInput = document.getElementById('senha');
  const barraForca = document.getElementById('barra-forca');

  if(senhaInput) {
      senhaInput.addEventListener('input', function() {
        const valor = senhaInput.value;
        let forca = 0;
        if (valor.length >= 6) forca++;
        if (/[A-Z]/.test(valor)) forca++;
        if (/[0-9]/.test(valor)) forca++;
        if (/[\W]/.test(valor)) forca++;

        if (forca === 0) barraForca.style.width = '0';
        else if (forca <= 2) { barraForca.style.width = '33%'; barraForca.style.backgroundColor = '#dc3545'; }
        else if (forca === 3) { barraForca.style.width = '66%'; barraForca.style.backgroundColor = '#ffc107'; }
        else { barraForca.style.width = '100%'; barraForca.style.backgroundColor = '#28a745'; }
      });
  }
</script>

</body>
</html>
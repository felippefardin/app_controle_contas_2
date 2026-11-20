<?php
session_start();

// --- INÍCIO DA CORREÇÃO ---
// Carrega o autoload do Composer para Dotenv
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    die("ERRO CRÍTICO: O arquivo vendor/autoload.php não foi encontrado.");
}

// Carrega as variáveis de ambiente do arquivo .env que está na raiz do projeto
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die("ERRO CRÍTICO: O arquivo .env não foi encontrado.");
}
// --- FIM DA CORREÇÃO ---

$erro = '';
$sucesso = '';

// Verifica se o token foi passado na URL
$token = $_GET['token'] ?? '';

if (!$token) {
    die("Token inválido.");
}

// --- INÍCIO DA CORREÇÃO ---
// Conexão com o banco usando variáveis de ambiente
$servername = $_ENV['DB_HOST'] ?? 'localhost';
$username   = $_ENV['DB_USER'] ?? 'root';
$password   = $_ENV['DB_PASSWORD'] ?? ''; // Corrigido
$database   = "app_controle_contas";
// --- FIM DA CORREÇÃO ---

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// --- INÍCIO DA CORREÇÃO DE LÓGICA ---
// Verifica se o token existe E FAZ O JOIN COM A TABELA 'tenants'
// Assumindo que 'rs.usuario_id' agora guarda o 'tenant_id'
$stmt = $conn->prepare("SELECT rs.id, t.id AS tenant_id, t.nome_empresa, t.admin_email 
                         FROM recuperacao_senha rs
                         JOIN tenants t ON rs.usuario_id = t.id
                         WHERE rs.token = ? AND rs.usado = 0 AND rs.expira_em >= NOW()");
// --- FIM DA CORREÇÃO DE LÓGICA ---

$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    die("Token inválido ou expirado."); // O erro que você estava vendo
}

$stmt->bind_result($rec_id, $tenant_id, $nome, $email);
$stmt->fetch();
$stmt->close();

// Processa o envio do novo formulário de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = $_POST['senha'] ?? '';
    $confirma = $_POST['confirma_senha'] ?? '';

    if (!$senha || !$confirma) {
        $erro = "Preencha todos os campos.";
    } elseif ($senha !== $confirma) {
        $erro = "As senhas não coincidem.";
    } else {
        // Atualiza a senha no banco de dados
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // --- INÍCIO DA CORREÇÃO DE LÓGICA ---
        // Atualiza a nova senha na tabela 'tenants'
        $stmtUpdate = $conn->prepare("UPDATE tenants SET senha = ? WHERE id = ?");
        $stmtUpdate->bind_param("si", $senha_hash, $tenant_id);
        // --- FIM DA CORREÇÃO DE LÓGICA ---
        
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // Marca o token como usado
        $stmtToken = $conn->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE id = ?");
        $stmtToken->bind_param("i", $rec_id);
        $stmtToken->execute();
        $stmtToken->close();

        $sucesso = "Senha redefinida com sucesso! Você já pode <a href='login.php'>fazer login</a>.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Redefinir senha</title>
<style>
    /* --- GERAL --- */
    * {
        box-sizing: border-box;
    }
    body { 
        background-color: #121212; 
        color: #eee; 
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        display: flex; 
        justify-content: center; 
        align-items: center; 
        height: 100vh; 
        margin: 0;
        font-size: 16px;
        line-height: 1.5;
    }
    
    /* --- FORMULÁRIO --- */
    form { 
        background: #1e1e1e; 
        padding: 2.5rem 2rem; 
        border-radius: 12px; 
        width: 90%;
        max-width: 400px;
        display: flex; 
        flex-direction: column;
        border: 1px solid #333;
        box-shadow: 0 8px 30px rgba(0, 123, 255, 0.1);
    }
    h2 { 
        text-align: center; 
        color: #00bfff; 
        margin-top: 0;
        margin-bottom: 2rem; 
        font-weight: 600;
    }
    
    /* --- LABELS --- */
    label {
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #ccc;
    }
    
    /* --- INPUT WRAPPER (para o ícone) --- */
    .input-wrapper {
        position: relative;
        width: 100%;
        margin-bottom: 1.25rem;
    }
    
    /* --- INPUTS --- */
    input[type="password"], 
    input[type="text"] {
        width: 100%;
        padding: 12px;
        padding-right: 45px; /* Espaço para o ícone */
        border: 1px solid #444;
        border-radius: 6px;
        font-size: 1rem;
        background-color: #2a2a2a;
        color: #eee;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    input:focus { 
        outline: none; 
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
    }
    
    /* --- ÍCONE DE TOGGLE --- */
    .toggle-password {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .toggle-password:hover {
        color: #eee;
    }
    .toggle-password svg {
        width: 100%;
        height: 100%;
    }

    /* --- BOTÃO --- */
    button { 
        margin-top: 1rem; 
        padding: 12px; 
        border: none; 
        border-radius: 6px; 
        background: #007bff; 
        color: #fff; 
        font-weight: bold; 
        font-size: 1rem;
        cursor: pointer; 
        transition: background-color 0.3s;
    }
    button:hover { 
        background: #0056b3; 
    }
    
    /* --- MENSAGENS --- */
    .mensagem { 
        text-align: center; 
        padding: 12px; 
        border-radius: 6px; 
        margin-bottom: 1.5rem; 
        font-weight: 500;
        font-size: 0.95rem;
    }
    .erro { 
        background: #5a1a1a; 
        color: #ffc4c4;
        border: 1px solid #a03030;
    }
    .sucesso { 
        background: #1a5a3a; 
        color: #c4ffc4;
        border: 1px solid #30a050;
        line-height: 1.6;
    }
    .sucesso a {
        color: #fff;
        font-weight: bold;
        text-decoration: underline;
    }
    .sucesso a:hover {
        color: #eee;
    }
</style>
</head>
<body>
<form method="POST">
    <h2>Redefinir senha</h2>

    <?php if ($erro): ?>
        <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <!-- Links dentro de 'sucesso' são permitidos via 'echo' -->
        <div class="mensagem sucesso"><?= $sucesso ?></div>
    <?php else: ?>
    
        <!-- --- CAMPO NOVA SENHA COM TOGGLE --- -->
        <label for="senha">Nova senha</label>
        <div class="input-wrapper">
            <input type="password" id="senha" name="senha" required autofocus>
            <span class="toggle-password" data-target="senha">
                <!-- O ícone será inserido pelo JavaScript -->
            </span>
        </div>

        <!-- --- CAMPO CONFIRMAR SENHA COM TOGGLE --- -->
        <label for="confirma_senha">Confirme a nova senha</label>
        <div class="input-wrapper">
            <input type="password" id="confirma_senha" name="confirma_senha" required>
            <span class="toggle-password" data-target="confirma_senha">
                <!-- O ícone será inserido pelo JavaScript -->
            </span>
        </div>

        <button type="submit">Redefinir senha</button>
    <?php endif; ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Definições dos ícones SVG
    const iconEye = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>`;
                    
    const iconEyeOff = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>`;

    const toggles = document.querySelectorAll('.toggle-password');

    toggles.forEach(toggle => {
        // Define o ícone inicial (olho fechado)
        toggle.innerHTML = iconEye;

        toggle.addEventListener('click', function() {
            // Pega o ID do input alvo a partir do atributo 'data-target'
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);

            if (!targetInput) {
                return;
            }

            // Alterna o tipo do input e o ícone
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                this.innerHTML = iconEyeOff;
            } else {
                targetInput.type = 'password';
                this.innerHTML = iconEye;
            }
        });
    });
});
</script>

</body>
</html>

<?php
require_once __DIR__ . '/../includes/profile_avatar.php';
require_once '../includes/session_init.php';
require_once '../database.php'; 

// ✅ 1. VERIFICA SE O USUÁRIO ESTÁ LOGADO
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: login.php');
    exit;
}
$conn = getTenantConnection();
if ($conn === null) {
    die("Falha ao obter a conexão com o banco de dados do cliente.");
}

// ✅ 2. PEGA OS DADOS DO USUÁRIO
$id_usuario_atual = $_SESSION['usuario_id'] ?? null; 
$email = $_SESSION['email'] ?? null; 

// ❗️❗️ CORREÇÃO CRÍTICA 1: Se o e-mail não estiver na sessão, busca no banco ❗️❗️
if (empty($email) && $id_usuario_atual) {
    $stmt_email = $conn->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt_email->bind_param("i", $id_usuario_atual);
    $stmt_email->execute();
    $res_email = $stmt_email->get_result();
    if ($row_email = $res_email->fetch_assoc()) {
        $email = $row_email['email'];
        // Opcional: Atualiza a sessão para as próximas vezes
        $_SESSION['email'] = $email;
    }
    $stmt_email->close();
}

// ✅ 3. BUSCA TODOS OS USUÁRIOS DO CLIENTE
$sql = "SELECT id, nome, nivel_acesso, foto FROM usuarios WHERE deleted_at IS NULL ORDER BY nome ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result_usuarios = $stmt->get_result();

// ✅ 4. VERIFICA MENSAGENS DE FEEDBACK (Url Parameters)
$msg_email_sucesso = '';
if (isset($_GET['status']) && $_GET['status'] == 'email_enviado') {
    // Pega o e-mail enviado ou usa o atual como fallback
    $email_enviado = $_GET['email'] ?? ($email ?? 'seu e-mail');
    $msg_email_sucesso = "E-mail de senha enviado para <strong>" . htmlspecialchars($email_enviado) . "</strong>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<?php session_idle_script(); ?>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Usuário - App Controle de Contas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style> 
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #121212;
            color: #eee;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px; /* Adicionado padding para evitar colagem nas bordas em telas pequenas */
            box-sizing: border-box;
        }
        .selection-container {
            padding: 40px;
            background-color: #1e1e1e;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.2);
            text-align: center;
            
            /* Ajuste para Responsividade e Full Desktop */
            width: 100%;
            max-width: 500px; /* Um pouco mais largo para desktop */
        }
        h2 {
            color: #00bfff;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #bbb;
        }
        select, input[type="password"] {
            width: 100%;
            padding: 12px;
            padding-right: 40px;
            border-radius: 5px;
            border: 1px solid #444;
            background-color: #333;
            color: #eee;
            box-sizing: border-box;
        }
        .toggle-password {
            position: absolute;
            top: 37px;
            right: 12px;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .toggle-password:hover {
            color: #00bfff;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            background-color: #00bfff;
            color: #121212;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #0095cc;
        }
        .mensagem-erro {
            background-color: #dc3545;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: white;
        }
        .mensagem-sucesso {
            background-color: #28a745;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: white;
        }
        .alert-float-success {
            background-color: #28a745;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #1e7e34;
            opacity: 1;
            transition: opacity 0.5s ease-out;
        }

        .user-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            /* Aproveita melhor a altura em Desktop, rola se necessário */
            max-height: 50vh; 
            overflow-y: auto;
            border: 1px solid #444;
            border-radius: 5px;
            background-color: #333;
        }
        .user-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #444;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .user-item:hover {
            background-color: #444;
        }
        .user-item:last-child {
            border-bottom: none;
        }
        .user-item input[type="radio"] {
            margin-right: 15px;
            width: auto;
            cursor: pointer;
        }
        .user-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
            border: 1px solid #555;
        }
        .user-item label {
            display: flex;
            align-items: center;
            width: 100%;
            margin: 0;
            font-weight: normal;
            color: #eee;
            cursor: pointer;
        }
        
        /* Ajustes para Mobile */
        @media (max-width: 768px) {
            .selection-container {
                padding: 25px;
                max-width: 100%;
            }
            h2 {
                font-size: 1.5rem;
            }
            .user-item {
                padding: 15px 10px; /* Maior área de toque */
            }
            button {
                padding: 15px;
                font-size: 1rem;
            }
        }
    
/* ACP_LAYOUT_V2 */

body {
    --acp-bg: #060d1c;
    --acp-card: #101b2e;
    --acp-field: #182438;
    --acp-text: #f1f5f9;
    --acp-muted: #b4c0d2;
    --acp-border: #34445b;
    --acp-accent: #38bdf8;
    --acp-selected: #142c44;

    background:
        radial-gradient(ellipse at top left,
            #12345b 0%, transparent 45%),
        radial-gradient(ellipse at bottom right,
            #102d52 0%, transparent 45%),
        var(--acp-bg) !important;

    color: var(--acp-text);
    min-height: 100vh;
    min-height: 100dvh;
    padding: 24px;
}

html[data-theme="light"] body,
body[data-theme="light"],
html.light-mode body,
body.light-mode,
html.light body,
body.light {
    --acp-bg: #edf3fa;
    --acp-card: #ffffff;
    --acp-field: #f8fafc;
    --acp-text: #172033;
    --acp-muted: #475569;
    --acp-border: #cbd5e1;
    --acp-accent: #0284c7;
    --acp-selected: #e0f2fe;

    background:
        radial-gradient(ellipse at top left,
            #dbeafe 0%, transparent 50%),
        var(--acp-bg) !important;
}

.selection-container {
    width: 100%;
    max-width: 540px;
    padding: 36px;
    box-sizing: border-box;
    background: var(--acp-card) !important;
    border: 1px solid var(--acp-border);
    border-radius: 24px;
    color: var(--acp-text);
    box-shadow: 0 24px 70px rgba(0,0,0,.24);
}

.acp-login-icon {
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    margin: 0 auto 18px;
    border-radius: 18px;
    border: 1px solid var(--acp-border);
    background: var(--acp-selected);
    color: var(--acp-accent);
    font-size: 28px;
}

.selection-container h2 {
    color: var(--acp-text) !important;
    font-size: clamp(1.5rem, 4vw, 2rem);
    font-weight: 700;
    margin: 0 0 10px;
}

.acp-subtitle {
    color: var(--acp-muted);
    font-size: .95rem;
    line-height: 1.6;
    margin: 0 0 28px;
}

.selection-container .form-group {
    margin-bottom: 22px;
}

.selection-container label {
    color: var(--acp-text) !important;
    font-size: .95rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.selection-container .user-list {
    background: transparent;
    border: none;
    border-radius: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 34vh;
    margin: 0;
    padding: 3px;
}

.selection-container .user-item {
    background: var(--acp-field);
    border: 1px solid var(--acp-border);
    border-radius: 13px;
    padding: 14px 12px;
    gap: 12px;
    min-height: 52px;
    transition: background .2s, border-color .2s;
}

.selection-container .user-item:last-child {
    border-bottom: 1px solid var(--acp-border);
}

.selection-container .user-item:hover,
.selection-container .user-item:has(input:checked) {
    background: var(--acp-selected);
    border-color: var(--acp-accent);
}

.selection-container .user-item label {
    min-width: 0;
    margin: 0;
    font-size: .95rem;
    overflow-wrap: anywhere;
}

.selection-container .user-item input[type="radio"] {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin: 0;
    accent-color: var(--acp-accent);
}

.selection-container .user-item img {
    width: 46px;
    height: 46px;
    margin-right: 12px;
    flex-shrink: 0;
    border: 1px solid var(--acp-border);
}

.acp-password-wrap {
    position: relative;
}

.selection-container #senha {
    display: block;
    width: 100%;
    height: 54px;
    padding: 12px 52px 12px 15px;
    border: 1px solid var(--acp-border);
    border-radius: 11px;
    background: var(--acp-field) !important;
    color: var(--acp-text) !important;
    font: inherit;
    box-sizing: border-box;
}

.selection-container #senha:focus {
    outline: 2px solid var(--acp-accent);
    outline-offset: 2px;
}

.selection-container #toggleSenha {
    position: absolute;
    top: 50%;
    right: 5px;
    transform: translateY(-50%);
    width: 44px;
    height: 44px;
    padding: 0;
    background: transparent !important;
    border: none;
    color: var(--acp-muted) !important;
    box-shadow: none;
}

.selection-container button[type="submit"] {
    width: 100%;
    min-height: 52px;
    border-radius: 11px;
    font-size: 1rem;
    font-weight: 700;
    background: #38bdf8 !important;
    color: #082032 !important;
    border: 1px solid transparent;
}

.selection-container button[type="submit"]:hover {
    background: #7dd3fc !important;
}

.selection-container .acp-reset-form {
    display: block !important;
    margin-top: 14px;
}

.selection-container .btn-padrao-link {
    width: 100%;
    margin: 0 !important;
    background: transparent !important;
    border: 1px solid var(--acp-border) !important;
    color: var(--acp-text) !important;
}

.selection-container .btn-padrao-link:hover {
    background: var(--acp-selected) !important;
    border-color: var(--acp-accent) !important;
}

.selection-container button:focus-visible,
.selection-container input:focus-visible {
    outline: 2px solid var(--acp-accent);
    outline-offset: 3px;
}

@media (max-width: 600px) {
    body {
        padding: 16px;
    }

    .selection-container {
        padding: 24px 18px;
        border-radius: 18px;
    }

    .selection-container .user-item {
        gap: 8px;
        padding: 12px 10px;
    }

    .selection-container .user-item img {
        width: 40px;
        height: 40px;
        margin-right: 8px;
    }
}

</style>
</head>
<body>
    <div class="selection-container">
        <div class="acp-login-icon" aria-hidden="true">
        <i class="fas fa-users"></i>
    </div>
    <h2>Selecionar Usuário</h2>
    <p class="acp-subtitle">
        Escolha o perfil e informe a senha para entrar no sistema.
    </p>
        
        <?php if (isset($_SESSION['erro_selecao'])): ?>
            <div class="mensagem-erro"><?= htmlspecialchars($_SESSION['erro_selecao']); ?></div>
            <?php unset($_SESSION['erro_selecao']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['sucesso_selecao'])): ?>
            <div class="mensagem-sucesso"><?= htmlspecialchars($_SESSION['sucesso_selecao']); ?></div>
            <?php unset($_SESSION['sucesso_selecao']); ?>
        <?php endif; ?>

        <?php if (!empty($msg_email_sucesso)): ?>
            <div class="alert-float-success" id="msgEmailSucesso">
                <?= $msg_email_sucesso; ?>
            </div>
        <?php endif; ?>

        <form action="../actions/trocar_usuario.php" method="POST">
    <input type="hidden" name="csrf_token"
        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            
            <div class="form-group">
                <label>Acessar como:</label>
                <div class="user-list">
                    <?php while ($usuario = $result_usuarios->fetch_assoc()): ?>
                        <?php
                            $foto_usuario = $usuario['foto'] ? $usuario['foto'] : 'default-profile.png';
                        ?>
                        <div class="user-item">
                            <input type="radio" name="usuario_id" id="user_<?= $usuario['id'] ?>" value="<?= $usuario['id'] ?>" <?= ($usuario['id'] === $id_usuario_atual) ? 'checked' : '' ?> required>
                            
                            <label for="user_<?= $usuario['id'] ?>">
                                <img src="<?= htmlspecialchars(profile_image_url($foto_usuario), ENT_QUOTES, 'UTF-8') ?>" referrerpolicy="no-referrer" alt="Foto de <?= htmlspecialchars($usuario['nome']) ?>">
                                <span>
                                    <?= htmlspecialchars($usuario['nome']) ?>
                                    <?= ($usuario['nivel_acesso'] === 'proprietario') ? ' (Principal)' : '' ?>
                                </span>
                            </label>
                        </div>
                    <?php endwhile; ?>
                    
                    <?php if ($result_usuarios->num_rows === 0): ?>
                        <div style="padding: 15px; text-align: center; color: #aaa;">
                            Nenhum usuário encontrado neste tenant.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha do usuário selecionado:</label>
                <div class="acp-password-wrap">
        <input type="password" name="senha" id="senha"
            autocomplete="current-password"
            placeholder="Digite sua senha" required>
                <button type="button"
        class="fas fa-eye toggle-password"
        id="toggleSenha"
        aria-label="Mostrar senha"
        aria-controls="senha"
        aria-pressed="false"></button>
    </div>
            </div>
            <button type="submit">Acessar Sistema</button>
        </form>

       <form action="../actions/enviar_link_email_perfil.php" method="post" class="acp-reset-form" onsubmit="return confirm('Deseja enviar um link de redefinição de senha para o seu e-mail cadastrado?');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="btn-padrao-link">
        <i class="fas fa-envelope" aria-hidden="true"></i>
        Redefinir por E-mail
    </button>
       </form>
    </div>

    <script>
    const toggleSenha = document.getElementById('toggleSenha');
    const inputSenha = document.getElementById('senha');

    toggleSenha.addEventListener('click', () => {
      const tipo = inputSenha.getAttribute('type') === 'password' ? 'text' : 'password';
      inputSenha.setAttribute('type', tipo);
      toggleSenha.classList.toggle('fa-eye');
      toggleSenha.classList.toggle('fa-eye-slash');
    toggleSenha.setAttribute('aria-pressed', tipo === 'text');
    toggleSenha.setAttribute('aria-label',
        tipo === 'text' ? 'Ocultar senha' : 'Mostrar senha');
    });

    // ✅ SCRIPT PARA FAZER A MENSAGEM SUMIR EM 3 SEGUNDOS
    document.addEventListener('DOMContentLoaded', function() {
        const msgSucesso = document.getElementById('msgEmailSucesso');
        if (msgSucesso) {
            setTimeout(function() {
                msgSucesso.style.opacity = '0';
                // Aguarda a transição de opacidade terminar para remover do DOM
                setTimeout(function() {
                    msgSucesso.remove();
                }, 500); // Tempo igual ao transition no CSS
            }, 3000); // 3000 milissegundos = 3 segundos
        }
    });
    </script>
</body>
</html>

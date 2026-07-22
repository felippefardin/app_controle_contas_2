<?php
require_once '../includes/session_init.php';
require_once '../includes/utils.php'; // Importa Flash Messages

// Se já estiver logado, vai pra Home
if (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] === true) {
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - App Controle</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #0d0d0d, #1a1a1a);
            color: #eee;
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            background: #1c1c1c;
            padding: 40px 35px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.55);
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        h2 { color: #00bfff; margin-bottom: 25px; text-align: center; font-weight: 600; }
        label { color: #fff; margin-bottom: 6px; font-size: 0.95rem; }

        input {
            width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333;
            background-color: #262626; color: #fff; font-size: 1rem; transition: 0.3s;
        }
        input:focus { outline: none; border-color: #00bfff; box-shadow: 0 0 5px rgba(0,191,255,0.4); }

        button {
            width: 100%; padding: 13px; border: none; border-radius: 8px;
            background: linear-gradient(135deg, #007bff, #00bfff);
            color: white; font-weight: bold; font-size: 1.05rem;
            cursor: pointer; transition: 0.2s; margin-top: 10px;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 18px rgba(0,191,255,0.45); }

        .links { text-align: center; margin-top: 18px; font-size: 0.9rem; }
        .links a { color: #8aa4b1; margin: 0 10px; text-decoration: none; transition: 0.2s; }
        .links a:hover { color: #00bfff; text-decoration: underline; }

        .reactivate-link { text-align: center; margin-top: 20px; }
        .reactivate-link a { color: #00bfff; font-size: 0.95rem; text-decoration: none; }
        .reactivate-link a:hover { color: #4dd3ff; }

        /* Botão Feedback (Direita) */
        .floating-icon {
            position: fixed; bottom: 30px; right: 30px;
            width: 50px; height: 50px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; cursor: pointer; z-index: 1000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: all 0.3s;
            background: #ffc107; color: #000;
        }
        .floating-icon:hover { transform: scale(1.1); }
        .tooltip-custom {
            position: absolute; right: 60px; background: #333; color: #fff;
            padding: 5px 10px; border-radius: 5px; font-size: 0.8rem;
            white-space: nowrap; opacity: 0; visibility: hidden; transition: 0.3s;
            pointer-events: none;
        }
        .floating-icon:hover .tooltip-custom { opacity: 1; visibility: visible; right: 60px; }

        /* Botão Suporte (Esquerda) - Adicionado */
        .btn-support-login {
            position: fixed;
            bottom: 30px;
            left: 30px; 
            background: #0d6efd;
            color: #fff;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
            border: 2px solid #fff;
        }
        .btn-support-login:hover {
            transform: scale(1.1);
            background: #0a58ca;
        }
        .support-tooltip-login {
            position: absolute;
            left: 60px;
            background: #333;
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
            pointer-events: none;
        }
        .btn-support-login:hover .support-tooltip-login {
            opacity: 1;
            visibility: visible;
            left: 60px;
        }
        
    </style>
</head>
<body>

<?php
// EXIBE O POP-UP CENTRALIZADO SE HOUVER MENSAGEM (Sucesso no cadastro ou Erro login)
display_flash_message();
?>

<div class="btn-support-login" data-bs-toggle="modal" data-bs-target="#modalSuporte">
    <i class="fa-solid fa-headset"></i>
    <span class="support-tooltip-login">Fale com seu parceiro</span>
</div>

<div class="floating-icon" id="btnFeedback" data-bs-toggle="modal" data-bs-target="#modalFeedback">
    <i class="fa-solid fa-comment-dots"></i>
    <span class="tooltip-custom">Deixe seu feedback</span>
</div>

<div class="login-container">
    <h2><i class="fa-solid fa-right-to-bracket"></i> Login</h2>

    <form action="../actions/login.php" method="POST">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus>

        <br><br>

        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required>

        <button type="submit">Entrar</button>
    </form>

    <div class="links">
        <a href="esqueci_senha_login.php">Esqueci minha senha</a> |
        <a href="registro.php">Criar conta</a>
    </div>

    <div class="reactivate-link">
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalReativarConta">
            <i class="fas fa-sync-alt"></i> Reativar Minha Assinatura
        </a>
    </div>
</div>

<div class="modal fade" id="modalFeedback" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning">Seu Feedback</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formFeedbackLogin">
                    <div style="display: flex; align-items: center; margin-top: 15px;">
                        <input type="checkbox" id="anonimo" name="anonimo" style="width: auto; margin: 0; cursor: pointer;">
                        <label for="anonimo" style="margin-left: 10px; margin-top: 0; cursor: pointer; font-weight: normal;">
                            Enviar anonimamente
                        </label>
                    </div>
                    <div id="dadosIdentificacaoFeed">
                        <input type="text" name="nome" class="form-control mb-2 bg-secondary text-white border-0" placeholder="Seu Nome">
                        <input type="email" name="email" class="form-control mb-2 bg-secondary text-white border-0" placeholder="E-mail">
                        <input type="text" name="whatsapp" class="form-control mb-2 bg-secondary text-white border-0" placeholder="WhatsApp">
                    </div>
                    <div class="mb-3">
                        <label>Pontuação:</label>
                        <select name="pontuacao" class="form-select bg-secondary text-white border-0">
                            <option value="5">⭐⭐⭐⭐⭐ Excelente</option>
                            <option value="4">⭐⭐⭐⭐ Muito Bom</option>
                            <option value="3">⭐⭐⭐ Bom</option>
                            <option value="2">⭐⭐ Regular</option>
                            <option value="1">⭐ Ruim</option>
                        </select>
                    </div>
                    <textarea name="descricao" class="form-control bg-secondary text-white border-0" rows="3" placeholder="Deixe sua opinião..." required></textarea>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" id="btnEnviarFeedback" class="btn btn-warning text-dark w-100" onclick="enviarFeedback()">Enviar Feedback</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSuporte" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-headset me-2"></i> Fale com seu Parceiro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formSuporteLogin">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="anonimoSuporte" name="anonimo">
                        <label class="form-check-label text-light" for="anonimoSuporte">Enviar Anonimamente</label>
                    </div>
                    
                    <div id="dadosIdentificacaoSuporte">
                        <div class="mb-2">
                            <input type="text" name="nome" class="form-control bg-secondary text-white border-0" placeholder="Seu Nome">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <input type="text" name="whatsapp" class="form-control bg-secondary text-white border-0" placeholder="WhatsApp">
                            </div>
                            <div class="col-md-6 mb-2">
                                <input type="email" name="email" class="form-control bg-secondary text-white border-0" placeholder="E-mail">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <textarea name="descricao" class="form-control bg-secondary text-white border-0" rows="4" placeholder="Como podemos ajudar?" required style="resize: none;"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnEnviarSuporte" class="btn btn-primary fw-bold px-4" onclick="enviarSuporte()">
                    Enviar <i class="fa-solid fa-paper-plane ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReativarConta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#1d1d1d; color:#eee; border-radius:12px;">
            <div class="modal-header" style="border-bottom:1px solid #333;">
                <h5 class="modal-title">Reativar Conta Suspensa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Informe seu e-mail para receber o link de reativação.</p>
                <form action="../actions/solicitar_reativacao.php" method="POST">
                    <label>E-mail</label>
                    <input type="email" name="email_reativacao" class="form-control" required>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Enviar Link de Reativação</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('anonimoFeedback').addEventListener('change', function() {
        document.getElementById('dadosIdentificacaoFeed').style.display = this.checked ? 'none' : 'block';
    });

    function enviarFeedback() {
        const btn = document.getElementById('btnEnviarFeedback');
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        const formData = new FormData(document.getElementById('formFeedbackLogin'));
        
        fetch('../actions/enviar_feedback_publico.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
             if(data.status === 'success') {
                alert(data.msg);
                location.reload();
            } else {
                alert(data.msg || 'Erro desconhecido.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Erro ao enviar feedback.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = originalText;
        });
    }

    // --- Lógica Suporte (Novo) ---
    const checkAnonimoSuporte = document.getElementById('anonimoSuporte');
    const divIdentificacaoSuporte = document.getElementById('dadosIdentificacaoSuporte');
    
    if(checkAnonimoSuporte) {
        checkAnonimoSuporte.addEventListener('change', function() {
            if (this.checked) {
                divIdentificacaoSuporte.style.display = 'none';
                divIdentificacaoSuporte.querySelectorAll('input').forEach(i => i.value = '');
            } else {
                divIdentificacaoSuporte.style.display = 'block';
            }
        });
    }

    function enviarSuporte() {
        const btn = document.getElementById('btnEnviarSuporte');
        const originalHTML = btn.innerHTML;
        const form = document.getElementById('formSuporteLogin');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        const formData = new FormData(form);
        
        // Caminho ajustado para estar dentro de /pages/
        fetch('../actions/enviar_suporte_login.php', { 
            method: 'POST', 
            body: formData 
        })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                const modalEl = document.getElementById('modalSuporte');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
                alert(data.msg); 
                form.reset();
            } else {
                alert(data.msg || 'Erro ao processar solicitação.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Erro de conexão.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
    }
</script>
</body>
</html>
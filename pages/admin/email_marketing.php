<?php
// pages/admin/email_marketing.php
require_once '../../includes/session_init.php';
include('../../database.php');

// Proteção: Apenas super admin
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$master_conn = getMasterConnection();

// Exibe todos os usuários ativos, mas identifica quem deu opt-in promocional.
// A ausência de aceite ou um aceite antigo sem essa escolha equivale a "não autorizado".
$sql_lista = "SELECT u.nome, u.email, u.tipo_pessoa, u.criado_em,
                     CASE
                         WHEN JSON_UNQUOTE(JSON_EXTRACT(COALESCE(tc.preferencias_json, '{}'), '$.comunicacoes_promocionais')) IN ('true', '1')
                         THEN 1 ELSE 0
                     END AS aceita_marketing
              FROM usuarios u
              LEFT JOIN termos_consentimento tc
                  ON tc.id = (
                      SELECT MAX(tc2.id)
                      FROM termos_consentimento tc2
                      WHERE tc2.usuario_id = u.id
                  )
              WHERE u.status = 'ativo'
                AND u.email IS NOT NULL
                AND u.email != ''
              ORDER BY aceita_marketing DESC, u.nome ASC";
$result_lista = $master_conn->query($sql_lista);

// Se der erro na query, evita quebrar a página inteira
if (!$result_lista) {
    die("Erro na consulta: " . $master_conn->error);
}

$usuarios_lista = $result_lista->fetch_all(MYSQLI_ASSOC);
$total_usuarios = count($usuarios_lista);
$total_emails = count(array_filter($usuarios_lista, static fn(array $user): bool => (int)$user['aceita_marketing'] === 1));
$total_sem_aceite = $total_usuarios - $total_emails;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<link rel="stylesheet" href="../../assets/css/universal-theme.css?v=20260813-admin">
<script src="../../assets/js/global-theme.js?v=20260813-admin"></script>
    <?php require __DIR__ . '/../../includes/csrf_client.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Marketing - Painel Master</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

    <style>
        /* Reutilizando o CSS do Dashboard para consistência */
        body { background-color: #0e0e0e; color: #eee; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 40px; }
        
        .topbar { width: 100%; background: #1a1a1a; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.4); box-sizing: border-box; }
        .topbar-title { font-size: 1.2rem; color: #00bfff; font-weight: bold; }
        .topbar a { color: #eee; text-decoration: none; padding: 8px 14px; border-radius: 4px; background-color: #333; transition: 0.2s; font-size: 14px; }
        .topbar a:hover { background-color: #444; }

        .container { width: 95%; max-width: 1200px; margin: 30px auto; display: flex; gap: 20px; flex-wrap: wrap; }
        
        .card { background: #121212; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.2); border: 1px solid #333; flex: 1; min-width: 300px; }
        
        h1 { color: #00bfff; text-align: center; margin-bottom: 30px; width: 100%; }
        h2 { color: #ff9f43; font-size: 1.3rem; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; }

        /* Formulário */
        label { display: block; margin-top: 15px; color: #ccc; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="file"] { width: 100%; padding: 12px; margin-top: 5px; border-radius: 6px; border: 1px solid #444; background-color: #1e1e1e; color: #fff; box-sizing: border-box; }
        input:focus { outline: 1px solid #00bfff; }
        
        .btn-send { width: 100%; margin-top: 25px; padding: 15px; border: none; border-radius: 8px; background: linear-gradient(135deg, #00bfff, #0099cc); color: #fff; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; }
        .btn-send:hover { filter: brightness(1.1); }

        /* Lista de Emails */
        .email-list-container { max-height: 600px; overflow-y: auto; background: #1a1a1a; border-radius: 6px; border: 1px solid #333; }
        .email-item { padding: 10px 15px; border-bottom: 1px solid #2a2a2a; display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; }
        .email-item:last-child { border-bottom: none; }
        .email-item:hover { background-color: #252525; }
        .email-role { font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; background: #333; color: #aaa; }
        .consent-summary { width:100%; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .consent-summary > div { padding:14px 16px; border:1px solid #344154; border-radius:8px; background:#17202d; }
        .consent-summary strong { display:block; margin-top:4px; font-size:1.35rem; }
        .consent-yes { color:#40d987; }
        .consent-no { color:#ff8a8a; }
        .consent-badge { display:inline-block; margin-top:5px; padding:3px 8px; border-radius:999px; font-size:.72rem; font-weight:800; }
        .consent-badge.yes { color:#baf7d4; background:rgba(31,157,85,.25); border:1px solid #299c5c; }
        .consent-badge.no { color:#ffd0d0; background:rgba(204,61,61,.22); border:1px solid #a54444; }
        .consent-notice { width:100%; padding:14px 16px; border-left:4px solid #00bfff; background:#152536; color:#dcecff; border-radius:6px; line-height:1.5; }
        @media (max-width:700px) { .consent-summary { grid-template-columns:1fr; } }

        .msg-box { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; width: 100%; box-sizing: border-box; }
        .msg-success { background: rgba(40,167,69,0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .msg-error { background: rgba(220,53,69,0.2); color: #ff6b6b; border: 1px solid #dc3545; }

        /* === CUSTOMIZAÇÃO DARK MODE PARA O EDITOR SUMMERNOTE === */
        .note-editor.note-frame { border: 1px solid #444 !important; background: #1e1e1e !important; }
        .note-toolbar { background-color: #252525 !important; border-bottom: 1px solid #444 !important; }
        .note-btn { background-color: #333 !important; color: #ddd !important; border: 1px solid #444 !important; }
        .note-btn:hover { background-color: #444 !important; }
        .note-btn.active { background-color: #00bfff !important; color: #fff !important; }
        .note-editable { background-color: #1e1e1e !important; color: #eee !important; min-height: 300px; }
        .note-placeholder { color: #777 !important; }
        .note-statusbar { background-color: #252525 !important; }
        /* Cor dos dropdowns do editor */
        .dropdown-menu { background-color: #252525; border: 1px solid #444; }
        .dropdown-item { color: #eee; }
        .dropdown-item:hover { background-color: #333; color: #fff; }
        .note-modal-content { background: #1e1e1e; color: #fff; border: 1px solid #444; }
        .note-modal-header { border-bottom: 1px solid #444; }
        .note-input { background: #333; color: #fff; border: 1px solid #555; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #121212; }
        ::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #00bfff; }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="topbar-title">App Control <span style="color:#fff; font-weight:300;">Marketing</span></div>
        <div>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>

    <div class="container">
        <h1>Campanha de Email Marketing</h1>

        <?php if (isset($_GET['sucesso'])): ?>
            <div class="msg-box msg-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
        <?php elseif (isset($_GET['erro'])): ?>
            <div class="msg-box msg-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <div class="consent-summary" aria-label="Resumo das autorizações promocionais">
            <div><span>Autorizados para marketing</span><strong class="consent-yes"><?= $total_emails ?></strong></div>
            <div><span>Sem autorização promocional</span><strong class="consent-no"><?= $total_sem_aceite ?></strong></div>
        </div>
        <div class="consent-notice">
            <strong>Como funciona:</strong> esta campanha será enviada somente aos usuários que marcaram a opção de receber novidades, melhorias e ofertas. Avisos essenciais sobre segurança, cobrança, suporte e funcionamento da conta não são marketing e continuam em seus fluxos próprios.
        </div>

        <div class="card">
            <h2><i class="fas fa-paper-plane"></i> Novo Disparo</h2>
            <form action="../../actions/admin_enviar_marketing.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('Confirmar o envio somente para os usuários que autorizaram comunicações promocionais?');">
                
                <label for="assunto">Assunto do Email:</label>
                <input type="text" name="assunto" id="assunto" required placeholder="Ex: Novidades Incríveis no App..." style="margin-bottom: 15px;">

                <label for="mensagem">Mensagem (Personalizável com Emojis):</label>
                <textarea name="mensagem" id="mensagem" required></textarea>

                <label for="anexo" style="margin-top: 20px;"><i class="fas fa-paperclip"></i> Anexar Arquivo (PDF, Imagem, Zip - Máx 10MB):</label>
                <input type="file" name="anexo" id="anexo">

                <button type="submit" class="btn-send" <?= $total_emails === 0 ? 'disabled' : '' ?>><i class="fas fa-rocket"></i> Enviar para <?= $total_emails ?> usuário<?= $total_emails === 1 ? '' : 's' ?> autorizado<?= $total_emails === 1 ? '' : 's' ?></button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-users"></i> Preferências dos usuários (<?= $total_usuarios ?>)</h2>
            <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 15px;">A indicação abaixo permite ao usuário master distinguir quem pode ou não receber campanhas promocionais.</p>
            
            <div class="email-list-container">
                <?php if ($total_usuarios > 0): ?>
                    <?php foreach ($usuarios_lista as $user): ?>
                        <div class="email-item">
                            <div>
                                <strong style="color: #fff;"><?= htmlspecialchars($user['nome']) ?></strong><br>
                                <span style="color: #00bfff;"><?= htmlspecialchars($user['email']) ?></span>
                                <br><span class="consent-badge <?= (int)$user['aceita_marketing'] === 1 ? 'yes' : 'no' ?>"><?= (int)$user['aceita_marketing'] === 1 ? 'Marketing autorizado' : 'Não enviar marketing' ?></span>
                            </div>
                            <div style="text-align: right;">
                                <span class="email-role"><?= ucfirst($user['tipo_pessoa']) ?></span>
                                <br>
                                <span style="font-size: 0.75rem; color: #666;">
                                    <?= !empty($user['criado_em']) ? date('d/m/y', strtotime($user['criado_em'])) : '-' ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: #777;">Nenhum usuário encontrado.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#mensagem').summernote({
                placeholder: 'Escreva sua mensagem aqui... Use as ferramentas acima para personalizar!',
                tabsize: 2,
                height: 350,
                lang: 'pt-BR', // Define linguagem (se suportada pelo navegador)
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear', 'fontname', 'fontsize', 'color']],
                    ['para', ['ul', 'ol', 'paragraph', 'height']],
                    ['insert', ['link', 'picture', 'hr']], // Picture permite inserir imagens
                    ['view', ['fullscreen', 'codeview', 'help']],
                    ['misc', ['emoji']] // Botão de emojis (se o plugin estiver carregado, ou usa-se atalho do SO)
                ],
                // Configurações visuais para dark mode
                callbacks: {
                    onInit: function() {
                        // Força cor do texto ao iniciar
                        $('.note-editable').css('color', '#eee');
                    }
                }
            });
        });
    </script>

</body>
</html>

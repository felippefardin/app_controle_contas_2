<?php
// ARQUIVO: includes/mensagem_home_display.php
// Este arquivo deve ser incluído no final de pages/home.php (antes do </body>)

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) return;

// 2. VERIFICAÇÃO DE LOGIN: Só executa se o usuário acabou de fazer login
// Isso impede que o popup apareça apenas atualizando a página (F5)
if (!isset($_SESSION['acabou_de_logar']) || $_SESSION['acabou_de_logar'] !== true) {
    return;
}

// Remove o gatilho imediatamente
unset($_SESSION['acabou_de_logar']);

$usuario_id_logado = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

// Garante conexão com o banco
if (!function_exists('getMasterConnection')) {
    if(file_exists(__DIR__ . '/../database.php')) include_once(__DIR__ . '/../database.php');
    else if(file_exists(__DIR__ . '/database.php')) include_once(__DIR__ . '/database.php');
}

$connMaster = getMasterConnection();

// 3. SQL OTIMIZADO
// Busca mensagens válidas (hoje ou passado) que o usuário ainda não esgotou as visualizações
$sql_msg = "
    SELECT m.*, a.id AS agendamento_id, a.data_exibicao AS data_agendada,
           a.quantidade_logins AS limite_data, v.visualizacoes
    FROM mensagens_home_agendamentos a
    INNER JOIN mensagens_home m ON m.id = a.mensagem_id
    LEFT JOIN mensagens_home_visualizacoes v
        ON v.agendamento_id = a.id AND v.usuario_id = $usuario_id_logado
    WHERE a.data_exibicao = '$hoje'
    AND (v.visualizacoes IS NULL OR v.visualizacoes < a.quantidade_logins)
    ORDER BY a.data_exibicao DESC, m.id DESC
    LIMIT 1
";

$res_msg = $connMaster->query($sql_msg);

if ($res_msg && $res_msg->num_rows > 0) {
    $mensagem = $res_msg->fetch_assoc();
    $mensagem_id = $mensagem['id'];
    $agendamento_id = (int)$mensagem['agendamento_id'];
    
    // Incrementa visualização no banco
    if (isset($mensagem['visualizacoes'])) {
        // CORREÇÃO: Removido 'ultima_visualizacao' que causava o erro fatal
        $connMaster->query("UPDATE mensagens_home_visualizacoes 
                            SET visualizacoes = visualizacoes + 1 
                            WHERE agendamento_id = $agendamento_id AND usuario_id = $usuario_id_logado");
    } else {
        $connMaster->query("INSERT INTO mensagens_home_visualizacoes (mensagem_id, agendamento_id, usuario_id, visualizacoes)
                            VALUES ($mensagem_id, $agendamento_id, $usuario_id_logado, 1)");
    }

    // Renderiza HTML do Modal
    $caminho_imagem = !empty($mensagem['arquivo']) ? '../assets/uploads/mensagens/' . $mensagem['arquivo'] : '';
    $extensao_anexo = strtolower(pathinfo($mensagem['arquivo'] ?? '', PATHINFO_EXTENSION));
    ?>
    <style>
        .sys-modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.85); z-index: 9999; 
            display: flex; align-items: center; justify-content: center; 
            backdrop-filter: blur(5px); animation: fadeIn 0.4s; 
        }
        .sys-modal { 
            background: #1e1e1e; width: 95%; max-width: 500px; 
            border-radius: 12px; overflow: hidden; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.7); border: 1px solid #333; 
            position: relative; animation: slideUp 0.4s; 
            display: flex; flex-direction: column;
        }
        
        /* --- AJUSTE DA IMAGEM --- */
        .sys-modal-img { 
            width: 100%; 
            height: 300px; /* Altura fixa para manter padrão */
            object-fit: contain; /* Garante que a imagem apareça inteira sem cortes */
            background-color: #000; /* Fundo preto para preencher espaços vazios */
            display: block; 
            border-bottom: 1px solid #333;
        }
        .sys-modal-pdf { width: 100%; height: 430px; border: 0; background: #fff; display: block; }
        .sys-pdf-link { display:block; padding:10px; background:#2c2c2c; color:#00bfff; text-align:center; text-decoration:none; border-bottom:1px solid #444; }
        /* ------------------------ */

        .sys-modal-body { padding: 25px; color: #eee; text-align: center; }
        .sys-modal-title { color: #00bfff; font-size: 1.6rem; margin-bottom: 15px; font-weight: bold; }
        .sys-modal-text { font-size: 1.1rem; line-height: 1.6; color: #ccc; margin-bottom: 25px; }
        
        .sys-btn { 
            background: linear-gradient(135deg, #00bfff, #009acd); 
            color: white; padding: 12px 30px; 
            border: none; border-radius: 6px; font-weight: bold; font-size: 1rem;
            cursor: pointer; text-decoration: none; display: inline-block; 
            transition: 0.2s; box-shadow: 0 4px 15px rgba(0, 191, 255, 0.3);
        }
        .sys-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 191, 255, 0.5); color: #fff; }
        
        .sys-close { 
            position: absolute; top: 10px; right: 15px; font-size: 28px; 
            color: white; cursor: pointer; text-shadow: 0 2px 5px rgba(0,0,0,0.8);
            z-index: 10; transition: 0.2s;
        }
        .sys-close:hover { color: #ff4444; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>

    <div class="sys-modal-overlay" id="modalSistema">
        <div class="sys-modal">
            <div class="sys-close" onclick="document.getElementById('modalSistema').style.display='none'">&times;</div>
            
            <?php if($caminho_imagem && $extensao_anexo === 'pdf' && file_exists(__DIR__ . '/../assets/uploads/mensagens/' . $mensagem['arquivo'])): ?>
                <iframe src="<?= htmlspecialchars($caminho_imagem) ?>#toolbar=0" class="sys-modal-pdf" title="Documento PDF"></iframe>
                <a class="sys-pdf-link" href="<?= htmlspecialchars($caminho_imagem) ?>" target="_blank"><i class="fas fa-file-pdf"></i> Abrir PDF em nova aba</a>
            <?php elseif($caminho_imagem && in_array($extensao_anexo, ['jpg', 'jpeg', 'png'], true) && file_exists(__DIR__ . '/../assets/uploads/mensagens/' . $mensagem['arquivo'])): ?>
                <img src="<?= $caminho_imagem ?>" class="sys-modal-img" alt="Aviso">
            <?php endif; ?>

            <div class="sys-modal-body">
                <div class="sys-modal-title"><?= htmlspecialchars($mensagem['titulo']) ?></div>
                <div class="sys-modal-text"><?= nl2br(htmlspecialchars($mensagem['mensagem'])) ?></div>
                
                <?php if(!empty($mensagem['link_botao'])): ?>
                    <a href="<?= htmlspecialchars($mensagem['link_botao']) ?>" target="_blank" class="sys-btn" onclick="document.getElementById('modalSistema').style.display='none'">
                        <?= htmlspecialchars($mensagem['texto_botao']) ?>
                    </a>
                <?php else: ?>
                    <button class="sys-btn" onclick="document.getElementById('modalSistema').style.display='none'">
                        <?= htmlspecialchars($mensagem['texto_botao']) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

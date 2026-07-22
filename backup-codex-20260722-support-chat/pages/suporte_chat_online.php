<?php
// suporte_chat_online.php
// Página de chat online entre suporte e cliente, com registro, timer e auto-finalização

require_once '../includes/session_init.php';
require_once '../database.php';

// Verifica login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$id_usuario = $_SESSION['usuario_id'];

// Conexão Tenant
$conn = getTenantConnection();
if (!$conn) die("Erro ao conectar ao banco do tenant");

// Recebe ID do chamado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID do chamado não encontrado.");
}
$id_chamado = (int)$_GET['id'];

// Busca dados do chamado e garante que pertence ao tenant
$stmt = $conn->prepare("SELECT * FROM chamados_suporte WHERE id = ? AND tenant_id = ? LIMIT 1");
$stmt->bind_param("is", $id_chamado, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$chamado = $result->fetch_assoc();
$stmt->close();

if (!$chamado) die("Chamado não encontrado.");

// Se o chat foi finalizado
if ($chamado['status'] === 'finalizado') {
    echo "<h2 style='color:white;text-align:center;margin-top:40px'>Este chat já foi finalizado.</h2>";
    exit;
}

// Timer
$inicio_timestamp = strtotime($chamado['inicio_atendimento']);
$limite = 3600; // 1 hora
$extra = 300;   // 5 minutos finais
$total_limite = $limite + $extra;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Chat Online</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/chat.css">
<style>
body { background:#121212; margin:0; font-family:Arial; color:#fff; }
.chat-container { max-width:900px; margin:20px auto; background:#1f1f1f; border-radius:10px; padding:20px; }
.chat-messages { height:420px; overflow-y:auto; padding:15px; background:#181818; border-radius:8px; }
.msg { margin-bottom:15px; padding:10px 15px; border-radius:8px; max-width:70%; }
.msg-user { background:#007bff; margin-left:auto; }
.msg-suporte { background:#444; }
.chat-input { display:flex; margin-top:15px; }
.chat-input textarea { width:100%; padding:10px; border-radius:6px; background:#2a2a2a; border:1px solid #555; color:#fff; }
.chat-input button { margin-left:10px; background:#00bfff; border:none; padding:12px 20px; border-radius:6px; cursor:pointer; }
.timer { text-align:center; font-size:18px; margin:10px 0; color:#00bfff; }
.alerta-5min { color:#ff4444; font-weight:bold; }
.finalizado { text-align:center; font-size:20px; color:#ff4444; }
</style>
</head>
<body>
<div class="chat-container">

<h2><i class="fas fa-comments"></i> Chat Online - Chamado #<?= $id_chamado ?></h2>
<div id="timer" class="timer">Carregando...</div>

<div id="chatMessages" class="chat-messages"></div>

<div id="finalizadoBox" class="finalizado" style="display:none;">⚠ Chat finalizado automaticamente.</div>

<div class="chat-input" id="inputArea">
    <textarea id="msgText" placeholder="Digite sua mensagem..."></textarea>
    <button onclick="enviarMensagem()">Enviar</button>
</div>

</div>

<script>
let inicio = <?= $inicio_timestamp ?>;
let limite = <?= $total_limite ?>;
let chatFinalizado = false;

function atualizarTimer() {
    if (chatFinalizado) return;

    const agora = Math.floor(Date.now() / 1000);
    const passado = agora - inicio;
    const restante = limite - passado;

    const timerBox = document.getElementById("timer");

    if (restante <= 0) {
        finalizarChatAutomatico();
        return;
    }

    const min = Math.floor(restante / 60);
    const seg = restante % 60;

    timerBox.innerHTML = `${min}m ${seg}s restantes`;

    if (restante <= 300) {
        timerBox.classList.add("alerta-5min");
        timerBox.innerHTML = `⚠ A sessão termina em ${min}m ${seg}s`;
    }
}
setInterval(atualizarTimer, 1000);

function carregarMensagens() {
    fetch("../actions/carregar_chat.php?id=<?= $id_chamado ?>")
        .then(r => r.json())
        .then(dados => {
            const box = document.getElementById("chatMessages");
            box.innerHTML = "";

            dados.forEach(msg => {
                const div = document.createElement("div");
                div.classList.add("msg");
                div.classList.add(msg.origem === "usuario" ? "msg-user" : "msg-suporte");
                div.innerHTML = msg.mensagem;
                box.appendChild(div);
            });

            box.scrollTop = box.scrollHeight;
        });
}
setInterval(carregarMensagens, 1500);
carregarMensagens();

function enviarMensagem() {
    const texto = document.getElementById('msgText').value.trim();
    if (texto.length === 0 || chatFinalizado) return;

    fetch('../actions/enviar_msg_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_chamado: <?= $id_chamado ?>, mensagem: texto })
    }).then(() => {
        document.getElementById('msgText').value = '';
        carregarMensagens();
    });
}

function finalizarChatAutomatico() {
    chatFinalizado = true;

    document.getElementById("inputArea").style.display = "none";
    document.getElementById("finalizadoBox").style.display = "block";

    fetch('../actions/finalizar_chat.php?id=<?= $id_chamado ?>');
}
</script>

</body>
</html>
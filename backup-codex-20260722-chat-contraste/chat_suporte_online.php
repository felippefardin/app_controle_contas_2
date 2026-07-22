<?php 
require_once '../includes/session_init.php';
require_once '../database.php';

// Conexão Master
$conn = getMasterConnection();

// Recebe chat_id e valida
$chatId = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
if ($chatId <= 0) {
    header("Location: home.php");
    exit;
}

// Verificação Admin
$isAdmin = (
    isset($_SESSION['super_admin']) ||
    (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')
);

$userId = $_SESSION['usuario_id'] ?? 0;
$tenantId = $_SESSION['tenant_id'] ?? 0;

if (!$isAdmin && $userId == 0) {
    header("Location: login.php");
    exit;
}

// 1. Busca o Chat
$stmt = $conn->prepare("SELECT * FROM chat_sessions WHERE id = ?");
$stmt->bind_param("i", $chatId);
$stmt->execute();
$result = $stmt->get_result();
$chat = $result->fetch_assoc();
$stmt->close();

// 2. Verificações de Acesso
$acessoPermitido = false;
if ($chat) {
    if ($isAdmin) {
        $acessoPermitido = true;
    } else {
        if (isset($chat['usuario_id']) && (int)$chat['usuario_id'] === (int)$userId) {
            $acessoPermitido = true;
        } elseif ($tenantId > 0) {
            // CORREÇÃO: Usa a função nativa do sistema para buscar o tenant
            // Isso evita erros de SQL se a coluna 'tenant_id' não existir
            if (function_exists('getTenantById')) {
                $tenantData = getTenantById($tenantId, $conn);
            } else {
                // Fallback de segurança: busca apenas pelo ID primário
                $stmtT = $conn->prepare("SELECT usuario_id FROM tenants WHERE id = ? LIMIT 1");
                $stmtT->bind_param("i", $tenantId);
                $stmtT->execute();
                $resT = $stmtT->get_result();
                $tenantData = $resT->fetch_assoc();
                $stmtT->close();
            }

            // Verifica se o Chat pertence ao Dono deste Tenant
            if ($tenantData && isset($tenantData['usuario_id']) && (int)$chat['usuario_id'] === (int)$tenantData['usuario_id']) {
                $acessoPermitido = true;
            }
        }
    }
}

if (!$chat || !$acessoPermitido) {
    echo "<script>alert('Acesso negado.'); window.location.href='home.php';</script>";
    exit;
}

// 3. GARANTIR PROTOCOLO AGORA
// Se não existir protocolo no banco, gera agora para exibir na tela imediatamente
if (empty($chat['protocolo'])) {
    $novoProtocolo = date('Ymd') . rand(1000, 9999);
    $stmtUpd = $conn->prepare("UPDATE chat_sessions SET protocolo = ? WHERE id = ?");
    $stmtUpd->bind_param("si", $novoProtocolo, $chatId);
    $stmtUpd->execute();
    $stmtUpd->close();
    $chat['protocolo'] = $novoProtocolo; // Atualiza variável local
}

// Header
if (file_exists('../includes/header.php')) {
    include '../includes/header.php';
}

// Variáveis JS
$jsUserType = $isAdmin ? 'admin' : 'user';
$jsUserId = (int)($userId ?: 0);
$jsChatId = (int)$chatId;
?>

<style>
:root {
    --chat-bg: #e5ddd5;
    --header-bg: linear-gradient(135deg, #0d6efd, #0056b3);
    --msg-user: #d9fdd3;
    --msg-admin: #ffffff;
    --primary: #0d6efd;
}

/* Layout */
.chat-container { max-width: 900px; margin: 30px auto; font-family: sans-serif; }
.chat-card {
    background: #fff; border-radius: 12px; overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    display: flex; flex-direction: column; height: 80vh;
}

/* Header */
.chat-header {
    background: var(--header-bg); color: white; padding: 15px 20px;
    display: flex; justify-content: space-between; align-items: center;
}
.protocolo-box { font-size: 0.9rem; opacity: 0.9; margin-top: 4px; }

/* Corpo das mensagens */
#chatBox {
    flex: 1; background-color: var(--chat-bg);
    padding: 20px; overflow-y: auto;
    display: flex; flex-direction: column; gap: 10px;
}

/* Balões */
.msg {
    max-width: 75%; padding: 10px 14px; border-radius: 8px;
    position: relative; font-size: 0.95rem; line-height: 1.4;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    word-wrap: break-word;
}
.msg-user { align-self: flex-end; background: var(--msg-user); border-top-right-radius: 0; }
.msg-admin { align-self: flex-start; background: var(--msg-admin); border-top-left-radius: 0; }
.msg-time { display: block; font-size: 0.7rem; text-align: right; margin-top: 4px; opacity: 0.6; }

/* Anexos */
.msg-file { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #333; font-weight: 600; }
.msg-img { max-width: 200px; border-radius: 6px; display: block; margin-bottom: 5px; }

/* Footer input */
.chat-footer {
    padding: 15px; background: #f0f2f5; border-top: 1px solid #ddd;
    display: flex; align-items: center; gap: 10px;
}
#msgInput {
    flex: 1; padding: 12px 18px; border-radius: 24px; border: 1px solid #ccc; outline: none;
}
.btn-icon {
    background: none; border: none; font-size: 1.3rem; color: #555; cursor: pointer;
}
.btn-send {
    background: var(--primary); color: white; border: none; width: 45px; height: 45px;
    border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.btn-send:hover { background: #0b5ed7; }

/* Modal */
.modal-backdrop {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.6); display: none; z-index: 9999;
    justify-content: center; align-items: center;
}
.modal-content {
    background: #fff; padding: 30px; border-radius: 12px;
    text-align: center; max-width: 400px; width: 90%;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
</style>

<div class="chat-container">
    <div class="chat-card">
        <div class="chat-header">
            <div>
                <h5 style="margin:0; font-weight:700;">Atendimento Online</h5>
                <div class="protocolo-box">Protocolo: <strong id="headerProtocolo"><?php echo htmlspecialchars($chat['protocolo']); ?></strong></div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="timerDisplay" class="badge bg-light text-dark">...</span>
                <button class="btn btn-danger btn-sm" onclick="encerrarChat()">Encerrar</button>
            </div>
        </div>

        <div id="chatBox"></div>

        <div class="chat-footer">
            <label class="btn-icon" title="Anexar">
                <input type="file" id="fileInput" style="display:none" accept="image/*,application/pdf">
                <i class="fas fa-paperclip"></i>
            </label>
            <input type="text" id="msgInput" placeholder="Digite sua mensagem..." autocomplete="off">
            <button class="btn-send" onclick="enviarMensagem()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<div id="modalProtocolo" class="modal-backdrop">
    <div class="modal-content">
        <div style="margin-bottom: 15px;">
            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
        </div>
        <h4>Atendimento Finalizado</h4>
        <p class="text-muted" style="margin: 15px 0;">
            Anote seu protocolo para caso precise solicitar futuramente:
        </p>
        <h2 id="protocoloNum" class="text-primary" style="background:#f8f9fa; padding:10px; border-radius:8px; border:1px dashed #ccc;">
            </h2>
        <a id="backLink" href="#" class="btn btn-primary w-100 mt-3">Voltar ao Início</a>
    </div>
</div>

<audio id="soundMsg" src="data:audio/ogg;base64,T2dnUwACAAAAAAAAAAA+..." preload="auto"></audio>

<script>
const CONFIG = {
    chatId: <?php echo json_encode($jsChatId); ?>,
    userType: <?php echo json_encode($jsUserType); ?>,
    apiUrl: '../actions/chat_api.php'
};

let state = { lastId: 0, active: true };
const els = {
    box: document.getElementById('chatBox'),
    input: document.getElementById('msgInput'),
    timer: document.getElementById('timerDisplay'),
    modal: document.getElementById('modalProtocolo'),
    protoNum: document.getElementById('protocoloNum'),
    backBtn: document.getElementById('backLink')
};

// Configura link de voltar
els.backBtn.href = (CONFIG.userType === 'admin') ? 'admin/dashboard.php' : 'home.php';

// Formata hora
function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}

// Renderiza mensagem
function appendMessage(msg) {
    const div = document.createElement('div');
    const isMe = (msg.sender_type === CONFIG.userType) || 
                 (CONFIG.userType === 'user' && msg.sender_type === 'user') ||
                 (CONFIG.userType === 'admin' && msg.sender_type === 'admin');
    
    div.className = `msg ${isMe ? 'msg-user' : 'msg-admin'}`;
    
    let content = '';
    try {
        // Verifica se é arquivo JSON
        if (msg.mensagem.includes('_is_file')) {
            const file = JSON.parse(msg.mensagem);
            if (file.filetype.startsWith('image/')) {
                content = `<a href="${file.url}" target="_blank"><img src="${file.url}" class="msg-img"></a>`;
            } else {
                content = `<a href="${file.url}" target="_blank" class="msg-file"><i class="fas fa-file-pdf"></i> ${file.name}</a>`;
            }
        } else {
            content = msg.mensagem.replace(/\n/g, '<br>');
        }
    } catch(e) {
        content = msg.mensagem;
    }

    div.innerHTML = `<div>${content}</div><span class="msg-time">${formatTime(msg.data_envio)}</span>`;
    els.box.appendChild(div);
    els.box.scrollTop = els.box.scrollHeight;

    if (!isMe) document.getElementById('soundMsg').play().catch(()=>{});
}

// Enviar texto
function enviarMensagem() {
    const txt = els.input.value.trim();
    if (!txt) return;

    const fd = new FormData();
    fd.append('action', 'enviar_mensagem');
    fd.append('chat_id', CONFIG.chatId);
    fd.append('mensagem', txt);

    // UI Otimista
    appendMessage({ 
        sender_type: CONFIG.userType, 
        mensagem: txt, 
        data_envio: new Date().toISOString() 
    });
    
    els.input.value = '';
    state.lastId++; // Incremento temporário

    fetch(CONFIG.apiUrl, { method: 'POST', body: fd });
}

// Enviar Arquivo
document.getElementById('fileInput').addEventListener('change', function() {
    if (this.files[0]) {
        const fd = new FormData();
        fd.append('action', 'enviar_arquivo');
        fd.append('chat_id', CONFIG.chatId);
        fd.append('arquivo', this.files[0]);

        // Aviso visual
        appendMessage({ sender_type: CONFIG.userType, mensagem: '<i>Enviando arquivo...</i>', data_envio: new Date().toISOString() });

        fetch(CONFIG.apiUrl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if(d.status !== 'success') alert('Erro no envio: ' + d.msg);
        });
        this.value = '';
    }
});

// Enter envia
els.input.addEventListener('keypress', e => { if(e.key === 'Enter') enviarMensagem(); });

// Encerrar
function encerrarChat() {
    if(!confirm('Deseja encerrar o atendimento?')) return;
    
    const fd = new FormData();
    fd.append('action', 'encerrar_chat');
    fd.append('chat_id', CONFIG.chatId);

    fetch(CONFIG.apiUrl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') finalizarInterface(d.protocolo);
    });
}

// Mostrar Modal
function finalizarInterface(protocolo) {
    state.active = false;
    els.protoNum.innerText = protocolo;
    els.modal.style.display = 'flex'; // Abre o modal
    els.input.disabled = true;
}

// Polling
function update() {
    if (!state.active) return;

    const fd = new FormData();
    fd.append('action', 'get_status');
    fd.append('chat_id', CONFIG.chatId);
    fd.append('last_msg_id', state.lastId);

    fetch(CONFIG.apiUrl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.status !== 'success') return;

        // Timer
        let min = Math.floor(d.time_left / 60);
        let sec = Math.floor(d.time_left % 60);
        els.timer.innerText = `${min.toString().padStart(2,'0')}:${sec.toString().padStart(2,'0')}`;
        
        // Estado
        if (d.chat_state === 'waiting_start') els.timer.innerText = 'Aguardando...';
        else if (d.chat_state === 'closed' || d.chat_state === 'timeout') {
            finalizarInterface(d.protocolo);
        }

        // Mensagens
        if (d.messages) {
            d.messages.forEach(m => {
                if (m.id > state.lastId) {
                    appendMessage(m);
                    state.lastId = m.id;
                }
            });
        }
    });
}

setInterval(update, 3000);
update();
</script>

<?php if (file_exists('../includes/footer.php')) include '../includes/footer.php'; ?>
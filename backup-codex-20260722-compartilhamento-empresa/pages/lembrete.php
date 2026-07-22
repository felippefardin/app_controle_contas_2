<?php
require_once '../includes/config/config.php';
require_once '../includes/session_init.php';
require_once '../database.php'; 
require_once '../includes/utils.php'; // Importa utils (Flash Messages)
require_once '../includes/header.php';

// 🔒 Usuário precisa estar logado
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: login.php?erro=nao_logado");
    exit();
}

// 🔒 Verificar tenant ativo
if (!isset($_SESSION['tenant_id'])) {
    session_destroy();
    header("Location: login.php?erro=tenant_inexistente");
    exit();
}

// 📌 Pega dados do usuário
$usuario_id    = $_SESSION['usuario_id']; 
$tenant_id     = $_SESSION['tenant_id'];
$nome_usuario  = $_SESSION['nome'];

// 📌 Conexão do tenant
$conn = getTenantConnection();
if (!$conn) {
    die("Erro ao conectar com o banco de dados do tenant.");
}

// AUTO-CORREÇÃO DE TABELAS (MANTIDO DO ORIGINAL)
try {
    $sqlCriaTabela = "CREATE TABLE IF NOT EXISTS lembretes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        titulo VARCHAR(100) NOT NULL,
        descricao TEXT,
        data_lembrete DATE NOT NULL,
        hora_lembrete TIME NOT NULL,
        cor VARCHAR(20) NOT NULL,
        tipo_visibilidade ENUM('particular', 'grupo') DEFAULT 'particular',
        email_notificacao TEXT DEFAULT NULL,
        email_enviado TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->query($sqlCriaTabela);

    $sqlComentarios = "CREATE TABLE IF NOT EXISTS lembrete_comentarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lembrete_id INT NOT NULL,
        usuario_id INT NOT NULL,
        comentario TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lembrete_id) REFERENCES lembretes(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sqlComentarios);

    $check = $conn->query("SHOW COLUMNS FROM lembretes LIKE 'tipo_visibilidade'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE lembretes ADD COLUMN tipo_visibilidade ENUM('particular', 'grupo') DEFAULT 'particular'");
    }
    $check = $conn->query("SHOW COLUMNS FROM lembretes LIKE 'email_notificacao'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE lembretes ADD COLUMN email_notificacao TEXT DEFAULT NULL");
    } else {
        $conn->query("ALTER TABLE lembretes MODIFY COLUMN email_notificacao TEXT DEFAULT NULL");
    }
} catch (Exception $e) { }

// ====================================================================
// 🔍 BUSCAR LEMBRETES
// ====================================================================
$sql = "SELECT l.*, u.nome as autor_nome 
        FROM lembretes l
        JOIN usuarios u ON l.usuario_id = u.id
        WHERE (l.usuario_id = ?) 
           OR (l.tipo_visibilidade = 'grupo')
        ORDER BY l.data_lembrete ASC, l.hora_lembrete ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$todos_lembretes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Agrupar por Data e contar comentários
$lembretes_agrupados = [];
foreach ($todos_lembretes as $idx => $lem) {
    $total = 0;
    try {
        $sqlCom = "SELECT COUNT(*) as total FROM lembrete_comentarios WHERE lembrete_id = " . $lem['id'];
        $resCom = $conn->query($sqlCom);
        if($resCom) {
            $rowCom = $resCom->fetch_assoc();
            $total = $rowCom['total'];
        }
    } catch(Exception $e) { $total = 0; }
    
    $todos_lembretes[$idx]['total_comentarios'] = $total;
    $data = $lem['data_lembrete'];
    $lembretes_agrupados[$data][] = $todos_lembretes[$idx];
}

function diasSemana($dia) {
    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return $dias[$dia];
}

// --- EXIBE MENSAGEM FLASH CENTRALIZADA ---
display_flash_message();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
 body { background-color: #121212 !important; color: #e0e0e0; }

.page-header {
    display: flex; justify-content: space-between; align-items: center;
    margin: 30px 0; border-bottom: 1px solid #333; padding-bottom: 15px;
}
.page-title {
    color: #00bfff; font-weight: bold; font-size: 1.8rem; margin: 0;
    text-shadow: 0 0 10px rgba(0, 191, 255, 0.3);
}
.btn-main {
    background-color: transparent; color: #00bfff; font-weight: bold;
    border: 1px solid #00bfff; border-radius: 50px; padding: 10px 25px;
    transition: all 0.3s ease; box-shadow: 0 0 5px rgba(0, 191, 255, 0.2);
}
.btn-main:hover {
    background-color: #00bfff; color: #121212;
    box-shadow: 0 0 20px rgba(0, 191, 255, 0.6); transform: scale(1.05);
}

/* LISTAGEM */
.date-group-title {
    color: #aaa; font-size: 1rem; font-weight: bold; margin-top: 25px; margin-bottom: 15px;
    border-left: 4px solid #00bfff; padding-left: 12px;
    background: linear-gradient(90deg, rgba(0,191,255,0.1) 0%, transparent 100%);
    padding: 8px 12px; border-radius: 0 5px 5px 0;
}
.card-lembrete {
    background-color: #1e1e1e; border: 1px solid #333; border-radius: 12px;
    margin-bottom: 15px; transition: all 0.3s ease;
}
.card-lembrete:hover {
    transform: translateY(-3px); border-color: #555; box-shadow: 0 5px 15px rgba(0,0,0,0.5);
}
.card-body { padding: 15px; }
.prioridade-verde { border-left: 4px solid #28a745; }
.prioridade-amarelo { border-left: 4px solid #ffc107; }
.prioridade-vermelho { border-left: 4px solid #dc3545; }
.card-title { font-size: 1.1rem; font-weight: bold; color: #fff; display: flex; justify-content: space-between; margin-bottom: 5px; }
.card-meta { font-size: 0.85rem; color: #888; margin-bottom: 12px; }
.card-desc { color: #ccc; font-size: 0.95rem; white-space: pre-line; margin-bottom: 15px; }
.badge-custom { font-size: 0.7rem; padding: 4px 8px; border-radius: 4px; margin-left: 8px; }
.bg-particular { background-color: #343a40; color: #bbb; border: 1px solid #444; }
.bg-grupo { background-color: #4a148c; color: #fff; border: 1px solid #7b1fa2; box-shadow: 0 0 5px rgba(123, 31, 162, 0.5); }
.card-actions {
    border-top: 1px solid #333; padding-top: 10px;
    display: flex; justify-content: space-between; align-items: center;
}
.btn-icon { background: none; border: none; color: #666; font-size: 1rem; padding: 5px; transition: 0.2s; }
.btn-icon:hover { color: #fff; }
.btn-comentario { color: #00bfff; font-size: 0.9rem; text-decoration: none; background: none; border: none; }
.btn-comentario:hover { text-decoration: underline; text-shadow: 0 0 5px rgba(0,191,255,0.5); }

/* MODAIS */
.modal-content { background-color: #1f1f1f; color: white; border: 1px solid #444; box-shadow: 0 0 20px rgba(0,0,0,0.8); }
.modal-header { border-bottom: 1px solid #333; }
.modal-footer { border-top: 1px solid #333; }
.form-control, .form-select { background-color: #2c2c2c; border: 1px solid #444; color: white; }
.form-control:focus, .form-select:focus { background-color: #333; color: white; border-color: #00bfff; box-shadow: 0 0 5px rgba(0,191,255,0.3); }
.btn-close { filter: invert(1); }
#listaComentarios { max-height: 300px; overflow-y: auto; margin-bottom: 15px; }
.comentario-item { background: #262626; padding: 10px; border-radius: 8px; margin-bottom: 10px; border-left: 2px solid #00bfff; }
.comentario-header { font-size: 0.8rem; color: #00bfff; font-weight: bold; margin-bottom: 3px; }
.comentario-texto { font-size: 0.9rem; color: #eee; margin: 0; }
.comentario-data { font-size: 0.7rem; color: #666; float: right; }
</style>

<div class="container">
    
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-sticky-note me-2"></i>Lembretes</h2>
        <button class="btn btn-main" onclick="abrirModalNovo()">
            <i class="fas fa-plus"></i> Novo Lembrete
        </button>
    </div>

    <?php if (empty($lembretes_agrupados)): ?>
        <div class="text-center py-5 text-muted">
            <i class="far fa-calendar-check fa-4x mb-3" style="opacity: 0.3; color: #00bfff; text-shadow: 0 0 10px rgba(0,191,255,0.5);"></i>
            <p>Nenhum lembrete pendente.</p>
        </div>
    <?php else: ?>
        
        <?php foreach ($lembretes_agrupados as $data => $lembretes_dia): ?>
            <div class="date-group-title">
                <i class="far fa-calendar-alt me-2"></i>
                <?= date('d/m/Y', strtotime($data)) ?> 
                <span style="font-weight: normal; font-size: 0.9em; opacity: 0.8;">- <?= diasSemana(date('w', strtotime($data))) ?></span>
            </div>

            <div class="row">
                <?php foreach ($lembretes_dia as $lem): 
                    $classeCor = 'prioridade-' . ($lem['cor'] ?? 'verde');
                    $ehAutor = ($lem['usuario_id'] == $usuario_id);
                ?>
                <div class="col-md-4">
                    <div class="card card-lembrete <?= $classeCor ?>">
                        <div class="card-body">
                            
                            <div class="card-title">
                                <span><?= htmlspecialchars($lem['titulo']) ?></span>
                                <?php if($lem['tipo_visibilidade'] == 'grupo'): ?>
                                    <span class="badge-custom bg-grupo" title="Visível para todos"><i class="fas fa-users"></i> Grupo</span>
                                <?php else: ?>
                                    <span class="badge-custom bg-particular" title="Só você vê"><i class="fas fa-lock"></i> Privado</span>
                                <?php endif; ?>
                            </div>

                            <div class="card-meta">
                                <i class="far fa-clock"></i> <?= date('H:i', strtotime($lem['hora_lembrete'])) ?>
                                <span class="mx-2">•</span>
                                <i class="far fa-user"></i> <?= ($ehAutor) ? 'Você' : htmlspecialchars($lem['autor_nome']) ?>
                            </div>

                            <div class="card-desc"><?= nl2br(htmlspecialchars($lem['descricao'])) ?></div>

                            <div class="card-actions">
                                <?php if($lem['tipo_visibilidade'] == 'grupo'): ?>
                                    <button class="btn-comentario" onclick="abrirComentarios(<?= $lem['id'] ?>, '<?= htmlspecialchars($lem['titulo']) ?>')">
                                        <i class="far fa-comment-alt"></i> 
                                        <?= $lem['total_comentarios'] > 0 ? $lem['total_comentarios'] : 'Comentar' ?>
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted"><i class="fas fa-eye-slash"></i> Particular</small>
                                <?php endif; ?>

                                <div>
                                    <?php if($ehAutor): ?>
                                        <button class="btn-icon" onclick='editarLembrete(<?= json_encode($lem) ?>)' title="Editar"><i class="fas fa-pencil-alt"></i></button>
                                        
                                        <button class="btn-icon text-danger" onclick="abrirModalExcluir(<?= $lem['id'] ?>)" title="Excluir"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<div class="modal fade" id="modalLembrete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="../actions/salvar_lembrete.php" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Novo Lembrete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="lembreteId">
                
                <div class="mb-3">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" id="titulo" class="form-control" required placeholder="Ex: Reunião de equipe">
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" id="data" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Hora</label>
                        <input type="time" name="hora" id="hora" class="form-control" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Prioridade</label>
                        <select name="cor" id="cor" class="form-select">
                            <option value="verde">🟢 Normal</option>
                            <option value="amarelo">🟡 Atenção</option>
                            <option value="vermelho">🔴 Urgente</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Visibilidade</label>
                        <select name="tipo_visibilidade" id="tipo_visibilidade" class="form-select">
                            <option value="particular">🔒 Particular</option>
                            <option value="grupo">👥 Grupo</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">E-mails para notificação <small class="text-muted">(Separar por vírgula)</small></label>
                    <input type="text" name="email_notificacao" id="email_notificacao" class="form-control" placeholder="email1@exemplo.com, email2@exemplo.com">
                </div>

                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" id="descricao" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-main" style="border-radius: 5px;">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalComentarios" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comentários</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="tituloLembreteComentario" class="text-info mb-3 border-bottom pb-2"></h6>
                <div id="listaComentarios">Carregando...</div>
                
                <form id="formComentario" onsubmit="enviarComentario(event)" class="mt-3">
                    <input type="hidden" id="idLembreteComentario" name="lembrete_id">
                    <div class="input-group">
                        <input type="text" id="textoComentario" name="comentario" class="form-control" placeholder="Escreva..." required>
                        <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Tem certeza que deseja excluir este lembrete?
            </div>
            <div class="modal-footer">
                <form action="../actions/excluir_lembrete.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" id="idExcluir">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var modalLembrete, modalComentarios, modalExcluir;

document.addEventListener('DOMContentLoaded', function() {
    var elLembrete = document.getElementById('modalLembrete');
    var elComentarios = document.getElementById('modalComentarios');
    var elExcluir = document.getElementById('modalExcluir');
    
    if(elLembrete) modalLembrete = new bootstrap.Modal(elLembrete);
    if(elComentarios) modalComentarios = new bootstrap.Modal(elComentarios);
    if(elExcluir) modalExcluir = new bootstrap.Modal(elExcluir);
});

function abrirModalNovo() {
    limparModal();
    if(modalLembrete) modalLembrete.show();
}

function limparModal() {
    document.getElementById('lembreteId').value = '';
    document.getElementById('titulo').value = '';
    document.getElementById('data').value = '<?= date('Y-m-d') ?>';
    document.getElementById('hora').value = '<?= date('H:i') ?>';
    document.getElementById('descricao').value = '';
    document.getElementById('cor').value = 'verde';
    document.getElementById('tipo_visibilidade').value = 'particular';
    document.getElementById('email_notificacao').value = '';
    document.getElementById('modalTitle').innerText = 'Novo Lembrete';
}

function editarLembrete(data) {
    document.getElementById('lembreteId').value = data.id;
    document.getElementById('titulo').value = data.titulo;
    document.getElementById('data').value = data.data_lembrete;
    document.getElementById('hora').value = data.hora_lembrete ? data.hora_lembrete.substring(0, 5) : '';
    document.getElementById('descricao').value = data.descricao;
    document.getElementById('cor').value = data.cor;
    document.getElementById('tipo_visibilidade').value = data.tipo_visibilidade || 'particular';
    document.getElementById('email_notificacao').value = data.email_notificacao || '';
    
    document.getElementById('modalTitle').innerText = 'Editar Lembrete';
    if(modalLembrete) modalLembrete.show();
}

function abrirModalExcluir(id) {
    document.getElementById('idExcluir').value = id;
    if(modalExcluir) modalExcluir.show();
}

function abrirComentarios(id, titulo) {
    document.getElementById('idLembreteComentario').value = id;
    document.getElementById('tituloLembreteComentario').innerText = titulo;
    carregarComentarios(id);
    if(modalComentarios) modalComentarios.show();
}

function carregarComentarios(id) {
    var lista = document.getElementById('listaComentarios');
    lista.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-info spinner-border-sm"></div></div>';

    fetch('../actions/buscar_comentarios.php?id=' + id)
        .then(response => response.text())
        .then(html => { lista.innerHTML = html; })
        .catch(err => { lista.innerHTML = '<p class="text-danger text-center">Erro ao carregar.</p>'; });
}

function enviarComentario(e) {
    e.preventDefault();
    var id = document.getElementById('idLembreteComentario').value;
    var formData = new FormData(document.getElementById('formComentario'));

    fetch('../actions/salvar_comentario.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.sucesso) {
            document.getElementById('textoComentario').value = '';
            carregarComentarios(id); 
        } else {
            alert('Erro: ' + (data.msg || 'Erro desconhecido'));
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
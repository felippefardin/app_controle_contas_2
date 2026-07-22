<?php
require_once '../../includes/session_init.php';
include('../../database.php');

$conn = getMasterConnection();

// Filtro de busca
$busca = $_GET['busca'] ?? '';
$where = "WHERE status IN ('pendente', 'em_andamento')";
if (!empty($busca)) {
    $where .= " AND (protocolo LIKE '%$busca%' OR nome LIKE '%$busca%')";
}

$query = "SELECT * FROM suporte_login $where ORDER BY criado_em DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Suporte Ativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #121212; color: #eee; }
        .card-custom { background-color: #1e1e1e; border: 1px solid #333; }
        .table-dark { --bs-table-bg: #1e1e1e; }
        .badge-pendente { background-color: #ffc107; color: #000; }
        .badge-andamento { background-color: #17a2b8; color: #fff; }
        .modal-content { background-color: #1e1e1e; color: #eee; border: 1px solid #444; }
        .historico-box { max-height: 300px; overflow-y: auto; background: #252525; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .hist-item { margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px; }
        .hist-meta { font-size: 0.8rem; color: #aaa; }
    </style>
</head>
<body class="p-4">
    <div class="container">

        <!-- BOTÃO VOLTAR -->
        <a href="dashboard.php" class="btn btn-outline-light mb-3">
            <i class="fas fa-arrow-left"></i> Voltar para o Dashboard
        </a>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-info"><i class="fas fa-headset"></i> Suporte - Em Aberto</h2>
            <a href="suporte_resolvidos.php" class="btn btn-outline-success">
                <i class="fas fa-check-double"></i> Ver Resolvidos
            </a>
        </div>

        <!-- Busca -->
        <form class="mb-4 d-flex gap-2">
            <input type="text" name="busca" class="form-control bg-dark text-light border-secondary" placeholder="Buscar por protocolo ou nome..." value="<?= htmlspecialchars($busca) ?>">
            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>

        <div class="card card-custom p-3">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Protocolo</th>
                        <th>Data/Hora</th>
                        <th>Remetente</th>
                        <th>Status</th>
                        <th class="text-end">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold text-info"><?= $row['protocolo'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['criado_em'])) ?></td>
                            <td>
                                <?php if ($row['anonimo']): ?>
                                    <span class="badge bg-secondary"><i class="fas fa-user-secret"></i> Anônimo</span>
                                <?php else: ?>
                                    Nova mensagem de: <strong><?= htmlspecialchars($row['nome']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['status'] == 'pendente'): ?>
                                    <span class="badge badge-pendente">Pendente</span>
                                <?php else: ?>
                                    <span class="badge badge-andamento">Em Andamento</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary" onclick="abrirModal(<?= $row['id'] ?>)">
                                    <i class="fas fa-folder-open"></i> Abrir
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum chamado pendente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Detalhes -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-info">Detalhes do Chamado <span id="modalProtocolo"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Dados Principais -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Nome:</strong> <span id="modalNome"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Data:</strong> <span id="modalData"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Email:</strong> <span id="modalEmail"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>WhatsApp:</strong> <span id="modalWhats"></span>
                        </div>
                    </div>

                    <div class="p-3 bg-dark rounded mb-3 border border-secondary">
                        <strong class="d-block mb-2 text-warning">Descrição do Problema:</strong>
                        <p id="modalDescricao" class="mb-0 text-light"></p>
                    </div>

                    <hr class="border-secondary">

                    <!-- Histórico -->
                    <h6 class="text-muted"><i class="fas fa-history"></i> Histórico de Atendimento</h6>
                    <div id="boxHistorico" class="historico-box"></div>

                    <!-- Adicionar Nota -->
                    <div class="input-group mb-3">
                        <input type="text" id="inputHistorico" class="form-control bg-dark text-light border-secondary" placeholder="Adicionar nota ou resposta...">
                        <button class="btn btn-secondary" onclick="adicionarHistorico()"><i class="fas fa-paper-plane"></i></button>
                    </div>

                    <!-- Ações -->
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <input type="hidden" id="modalId">
                        <button class="btn btn-warning" onclick="alterarStatus('em_andamento')">
                            <i class="fas fa-spinner"></i> Marcar em Andamento
                        </button>
                        <button class="btn btn-success" onclick="alterarStatus('resolvido')">
                            <i class="fas fa-check"></i> Marcar como Resolvido
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modalEl = new bootstrap.Modal(document.getElementById('modalDetalhes'));

    function abrirModal(id) {
        console.log("Abrindo modal ID:", id);

        // Caminho correto para buscar detalhes
        fetch('../../actions/admin_suporte_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'acao=buscar_detalhes&id=' + id
        })
        .then(r => r.json())
        .then(resp => {
            if(resp.status === 'success') {
                const d = resp.dados;

                document.getElementById('modalId').value = d.id;
                document.getElementById('modalProtocolo').innerText = d.protocolo;
                document.getElementById('modalNome').innerText = d.anonimo == 1 ? 'Anônimo' : d.nome;
                document.getElementById('modalData').innerText = new Date(d.criado_em).toLocaleString('pt-BR');
                document.getElementById('modalEmail').innerText = d.email || '-';
                document.getElementById('modalWhats').innerText = d.whatsapp || '-';
                document.getElementById('modalDescricao').innerText = d.descricao;

                // Histórico
                const histBox = document.getElementById('boxHistorico');
                histBox.innerHTML = '';

                if (resp.historico) {
                    resp.historico.forEach(h => {
                        histBox.innerHTML += `
                            <div class="hist-item">
                                <div class="hist-meta">
                                    <i class="fas fa-clock"></i> 
                                    ${new Date(h.criado_em).toLocaleString('pt-BR')}
                                </div>
                                <div>${h.mensagem}</div>
                            </div>
                        `;
                    });
                }

                modalEl.show();
            } 
            else {
                alert("Erro: " + resp.msg);
            }
        })
        .catch(err => {
            console.error("ERRO FETCH:", err);
            alert("Erro ao carregar dados. Verifique o console (F12).");
        });
    }

    function adicionarHistorico() {
        const id = document.getElementById('modalId').value;
        const msg = document.getElementById('inputHistorico').value;

        if (!msg) return;

        fetch('../../actions/admin_suporte_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `acao=adicionar_historico&id=${id}&mensagem=${encodeURIComponent(msg)}`
        })
        .then(() => {
            document.getElementById('inputHistorico').value = '';
            abrirModal(id); // Recarrega o modal para ver a msg nova
        });
    }

    function alterarStatus(status) {
        if (!confirm('Confirma a alteração de status?')) return;

        const id = document.getElementById('modalId').value;

        // --- CORREÇÃO AQUI: Adicionado ../../ ao caminho ---
        fetch('../../actions/admin_suporte_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `acao=atualizar_status&id=${id}&status=${status}`
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'success') {
                alert("Status atualizado!");
                if (status === 'resolvido') {
                    location.reload();
                } else {
                    abrirModal(id);
                }
            } else {
                alert("Erro ao atualizar: " + resp.msg);
            }
        })
        .catch(err => console.error("Erro ao atualizar status:", err));
    }
</script>
</body>
</html>

<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// 1. VERIFICA O LOGIN E PEGA A CONEXÃO CORRETA
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php?error=not_logged_in');
    exit;
}

$conn = getTenantConnection();
if ($conn === null) {
    die("Falha ao conectar ao banco de dados do cliente.");
}

$id_usuario = $_SESSION['usuario_id'];

// 2. AJUSTA A CONSULTA SQL PARA FILTRAR PELO USUÁRIO
$lancamentos = [];
$sql = "SELECT id, data, valor FROM caixa_diario WHERE usuario_id = ? ORDER BY data DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $lancamentos = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

include('../includes/header.php');

// ✅ EXIBE MENSAGEM FLUTUANTE CENTRALIZADA
display_flash_message();
?>

<style>
    body { background-color: #121212; color: #eee; }
    .container { width: 95%; max-width: 1200px; margin: 20px auto; background-color: #222; padding: 25px; border-radius: 8px; }
    h2, h3 { color: #00bfff; }
    
    form, .search-container { background-color: #1f1f1f; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; }
    .form-control, .form-control-date { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #444; background-color: #2c2c2c; color: #eee; }
    
    .btn-primary { background-color: #00bfff; padding: 10px 18px; border: none; cursor: pointer; border-radius: 5px; color: #fff; font-weight: bold; }
    .btn-pdf { background-color: #2ecc71; padding: 10px 18px; border: none; color: white; cursor: pointer; border-radius: 5px; }
    
    .btn-edit { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 14px; }
    .btn-delete { background-color: #dc3545; color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; border:none; cursor: pointer; font-size: 14px; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table thead { background-color: #00bfff; color: #fff; }
    table th, table td { padding: 12px; border: 1px solid #444; text-align: center; }
    table tbody tr:hover { background-color: #3c3c3c; }

    /* Modal */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); justify-content: center; align-items: center; }
    .modal-content { background-color: #1f1f1f; padding: 25px; border-radius: 10px; width: 90%; max-width: 500px; position: relative; text-align: center; }
    .close-btn { position: absolute; top: 10px; right: 15px; font-size: 28px; cursor: pointer; color: #aaa; }
    
    @media (max-width: 768px) {
        .toggle-btn { display: block; width: 100%; margin-top: 15px; padding: 10px; background-color: #444; color: white; border-radius: 6px; text-align: center; cursor: pointer; }
    }
</style>

<div class="container">

    <h2>Lançamento de Caixa Diário</h2>

    <form action="../actions/add_caixa_diario.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="form-group">
            <label for="data">Data:</label>
            <input type="date" class="form-control" id="data" name="data" value="<?= date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
            <label for="valor">Valor (R$):</label>
            <input type="number" step="0.01" class="form-control" id="valor" name="valor" placeholder="0.00" required>
        </div>

        <button type="submit" class="btn-primary">Salvar Lançamento</button>
    </form>

    <div class="search-container">
        <h3>Filtrar por Período</h3>
        <div class="form-group">
            <label for="startDate">Data Inicial:</label>
            <input type="date" id="startDate" class="form-control-date">
        </div>
        <div class="form-group">
            <label for="endDate">Data Final:</label>
            <input type="date" id="endDate" class="form-control-date">
        </div>
        <button class="btn-pdf" onclick="gerarPDFPeriodo()">Salvar PDF do Período</button>
    </div>

    <hr>

    <h3>Histórico de Lançamentos</h3>

    <div id="tableWrapper" class="table-wrapper">
        <table id="lancamentosTable">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Valor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($lancamentos)): ?>
                    <?php foreach ($lancamentos as $lancamento): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($lancamento['data'])); ?></td>
                            <td>R$ <?= number_format($lancamento['valor'], 2, ',', '.'); ?></td>
                            <td>
                                <a href="editar_caixa_diario.php?id=<?= $lancamento['id']; ?>" class="btn-edit">
                                    <i class="fas fa-pen"></i>
                                </a>
                                
                                <button class="btn-delete" onclick="abrirModalExcluir(<?= $lancamento['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Nenhum lançamento encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalExcluir" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="fecharModal()">&times;</span>
        <h3 style="color: #dc3545;">Confirmar Exclusão</h3>
        <p>Tem certeza que deseja excluir este lançamento?</p>
        
        <form action="../actions/excluir_caixa_diario.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="idExcluir">
            
            <div style="margin-top: 20px;">
                <button type="button" class="btn-edit" style="background:#555; border:none; cursor:pointer;" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn-delete" style="cursor:pointer;">Sim, Excluir</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
// Filtros de Tabela
const startDateInput = document.getElementById('startDate');
const endDateInput = document.getElementById('endDate');
const tableBody = document.getElementById('lancamentosTable').getElementsByTagName('tbody')[0];

function filterTable() {
    const startDate = startDateInput.value ? new Date(startDateInput.value) : null;
    const endDate = endDateInput.value ? new Date(endDateInput.value) : null;

    if (startDate) startDate.setUTCHours(0,0,0,0);
    if (endDate) endDate.setUTCHours(23,59,59,999);

    Array.from(tableBody.rows).forEach(row => {
        const dateStr = row.cells[0].innerText.split('/');
        const rowDate = new Date(`${dateStr[2]}-${dateStr[1]}-${dateStr[0]}`);
        rowDate.setUTCHours(0,0,0,0);

        let show = true;
        if (startDate && rowDate < startDate) show = false;
        if (endDate && rowDate > endDate) show = false;
        row.style.display = show ? '' : 'none';
    });
}

startDateInput.addEventListener('input', filterTable);
endDateInput.addEventListener('input', filterTable);

// PDF
function gerarPDFPeriodo() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(18);
    doc.text("Histórico de Lançamentos de Caixa", 14, 22);

    const rows = [];
    Array.from(tableBody.rows).forEach(row => {
        if (row.style.display !== 'none') {
            rows.push([row.cells[0].innerText, row.cells[1].innerText]);
        }
    });

    if (rows.length === 0) return alert('Não há lançamentos visíveis.');

    doc.autoTable({ head: [['Data', 'Valor']], body: rows, startY: 30 });
    
    const total = rows.reduce((acc, curr) => {
        return acc + parseFloat(curr[1].replace('R$ ', '').replace('.', '').replace(',', '.'));
    }, 0);

    const finalY = doc.lastAutoTable.finalY + 10;
    doc.text(`Valor Total: R$ ${total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`, 14, finalY);
    doc.save('extrato_caixa.pdf');
}

// Modal
function abrirModalExcluir(id) {
    document.getElementById('idExcluir').value = id;
    document.getElementById('modalExcluir').style.display = 'flex';
}
function fecharModal() {
    document.getElementById('modalExcluir').style.display = 'none';
}
window.onclick = function(e) {
    if(e.target == document.getElementById('modalExcluir')) fecharModal();
}
</script>

<?php require_once '../includes/footer.php'; ?>
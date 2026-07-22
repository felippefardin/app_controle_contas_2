<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils para Flash Messages

if (!isset($_SESSION['usuario_logado'])) {
    header("Location: ../pages/login.php");
    exit();
}
$conn = getTenantConnection();
$usuarioId = $_SESSION['usuario_id'];

include('../includes/header.php');

// Exibe a mensagem centralizada
display_flash_message();

$where = ["cr.status='baixada'", "cr.usuario_id = " . intval($usuarioId)];

if (!empty($_GET['cliente'])) {
    $where[] = "pf.nome LIKE '%" . $conn->real_escape_string($_GET['cliente']) . "%'";
}
if (!empty($_GET['data_inicio']) && !empty($_GET['data_fim'])) {
    $where[] = "cr.data_vencimento BETWEEN '" . $conn->real_escape_string($_GET['data_inicio']) . "' AND '" . $conn->real_escape_string($_GET['data_fim']) . "'";
}

$sql = "SELECT cr.*, c.nome as nome_categoria, pf.nome as nome_pessoa, u.nome as nome_quem_baixou
        FROM contas_receber AS cr
        LEFT JOIN categorias AS c ON cr.id_categoria = c.id
        LEFT JOIN pessoas_fornecedores AS pf ON cr.id_pessoa_fornecedor = pf.id
        LEFT JOIN usuarios AS u ON cr.baixado_por = u.id 
        WHERE " . implode(" AND ", $where) . "
        ORDER BY cr.data_baixa DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contas Recebidas</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* === RESET E GERAL === */
    * { box-sizing: border-box; }
    body { 
        background-color: #121212; 
        color: #eee; 
        font-family: Arial, sans-serif; 
        margin: 0; 
       
    }

    /* === CONTAINER PRINCIPAL (FULL DESKTOP) === */
    .main-container {
        width: 100%;
        max-width: 1600px; /* Limite para telas muito grandes */
        margin: 0 auto;
        padding-bottom: 50px;
    }

    h2 { text-align: center; color: #00bfff; margin-bottom: 20px; }
    
    /* === FORMULÁRIO DE BUSCA === */
    form.search-form { 
        display: flex; 
        flex-wrap: wrap; /* Permite quebrar linha em telas menores */
        justify-content: center; 
        gap: 10px; 
        margin-bottom: 25px; 
        width: 100%;
    }

    form.search-form input { 
        padding: 10px; 
        background: #333; 
        border: 1px solid #444; 
        color: #eee; 
        border-radius: 5px;
        flex: 1; /* Cresce para ocupar espaço */
        min-width: 200px;
        font-size: 16px; /* Melhor para mobile */
    }

    form.search-form button { 
        padding: 10px 20px; 
        border-radius: 5px; 
        border: none; 
        cursor: pointer; 
        font-weight: bold; 
        background-color: #27ae60; 
        color: white; 
        min-width: 100px;
    }

    /* Botão Bulk Action */
    #btnBulkExcluir {
        background-color: #cc3333;
        color: white;
        border: none;
        padding: 10px 22px;
        font-weight: bold;
        border-radius: 5px;
        cursor: pointer;
        display: none; /* Oculto por padrão */
        align-items: center;
        gap: 5px;
        min-width: 100px;
    }
    
    /* === TABELA RESPONSIVA === */
    .table-responsive {
        width: 100%;
        overflow-x: auto; /* Scroll horizontal no mobile */
        border-radius: 8px;
        border: 1px solid #333;
        background-color: #1f1f1f;
        margin-top: 10px;
    }

    table { 
        width: 100%; 
        background-color: #1f1f1f; 
        border-collapse: collapse; 
        min-width: 800px; /* Força largura mínima para layout correto */
    }

    th, td { 
        padding: 12px 15px; 
        text-align: left; 
        border-bottom: 1px solid #333; 
        white-space: nowrap; /* Evita quebra de texto indesejada */
    }

    th { background-color: #222; color: #00bfff; font-weight: bold; }
    tr:nth-child(even) { background-color: #2a2a2a; }
    tr:hover { background-color: #333; }

    /* === BOTÕES DE AÇÃO === */
    .btn-action { 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        padding: 6px 12px; 
        border-radius: 4px; 
        font-size: 13px; 
        font-weight: bold; 
        text-decoration: none; 
        color: white; 
        cursor: pointer; 
        margin: 2px;
        border: none; /* Reset para button */
    }
    .btn-excluir { background-color: #cc3333; }
    .btn-comprovante { background-color: #f39c12; }
    .btn-estornar { background-color: #3498db; }
    
    /* === MODAL === */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); justify-content: center; align-items: center; padding: 10px; }
    .modal-content { background-color: #1f1f1f; padding: 25px; border-radius: 10px; width: 100%; max-width: 500px; text-align: center; position: relative; border: 1px solid #444; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    .close-btn { position: absolute; top: 10px; right: 20px; font-size: 28px; cursor: pointer; color: #aaa; }

    /* === RESPONSIVIDADE MOBILE ESPECÍFICA === */
    @media (max-width: 768px) {
        body { padding: 10px; }
        
        form.search-form { flex-direction: column; }
        form.search-form input { width: 100%; min-width: unset; margin: 2px 0; }
        form.search-form button, #btnBulkExcluir { width: 100%; margin-top: 5px; }

        h2 { font-size: 1.5rem; }
    }
  </style>
</head>
<body>

<div class="main-container">

    <h2>Contas Recebidas (Baixadas)</h2>

    <form class="search-form" method="GET">
      <input type="text" name="cliente" placeholder="Cliente" value="<?= htmlspecialchars($_GET['cliente'] ?? '') ?>">
      <input type="date" name="data_inicio" value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>">
      <input type="date" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
      <button type="submit"><i class="fa fa-search"></i> Buscar</button>
      
      <button type="button" id="btnBulkExcluir" onclick="submitBulkExcluir()">
         <i class="fa fa-trash"></i> Excluir Selecionados
      </button>
    </form>

    <?php if ($result && $result->num_rows > 0): ?>
    <div class="table-responsive">
        <table>
            <thead><tr>
                <th style='width: 40px; text-align:center;'>
                    <input type='checkbox' id='checkAll' onclick='toggleAll(this)'>
                </th>
                <th>Cliente</th>
                <th>Número</th>
                <th>Descrição</th>
                <th>Valor</th>
                <th>Recebido Por</th>
                <th>Data Receb.</th>
                <th>Categoria</th>
                <th>Comprovante</th>
                <th>Ações</th>
            </tr></thead>
            <tbody>
            <?php 
                while($row = $result->fetch_assoc()):
                    $data_baixa = $row['data_baixa'] ? date('d/m/Y', strtotime($row['data_baixa'])) : '-';
                    $quemBaixou = !empty($row['nome_quem_baixou']) ? $row['nome_quem_baixou'] : 'Sistema/N/D';
            ?>
                <tr>
                    <td style='text-align:center;'>
                        <input type='checkbox' class='check-item' value='<?= $row['id'] ?>' onclick='checkBtnState()'>
                    </td>
                    <td><?= htmlspecialchars($row['nome_pessoa'] ?? 'N/D') ?></td>
                    <td><?= htmlspecialchars($row['numero'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['descricao'] ?? '') ?></td>
                    <td>R$ <?= number_format($row['valor'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($quemBaixou) ?></td>
                    <td><?= $data_baixa ?></td>
                    <td><?= htmlspecialchars($row['nome_categoria'] ?? '-') ?></td>
                    <td>
                        <?= !empty($row['comprovante']) ? "<a href='../{$row['comprovante']}' target='_blank' class='btn-action btn-comprovante'><i class='fa fa-file'></i> Ver</a>" : '--' ?>
                    </td>
                    <td>
                        <div style='display:flex; gap:5px;'>
                            <a href='#' onclick="openEstornarModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nome_pessoa'])) ?>'); return false;" class='btn-action btn-estornar'><i class='fa-solid fa-undo'></i> Estornar</a>
                            
                            <button 
                                class='btn-action btn-excluir' 
                                data-id="<?= $row['id'] ?>" 
                                data-nome="<?= htmlspecialchars($row['nome_pessoa']) ?>"
                                onclick="openDeleteModal(this)">
                                <i class='fa-solid fa-trash'></i> Excluir
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="text-align:center; padding: 20px; color: #aaa;">Nenhuma conta recebida encontrada.</p>
    <?php endif; ?>

</div> <div id="deleteModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
      <h3>Confirmar Exclusão</h3>
      <p>Deseja excluir este registro de recebimento?</p>
      <p><strong>Cliente:</strong> <span id="delete-nome"></span></p>
      
      <form action="../actions/excluir_conta_receber.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <input type="hidden" name="id" id="delete-id">
          <input type="hidden" name="redirect" value="baixadas">
          
          <div style="margin-top: 20px;">
            <button type="submit" class='btn-action btn-excluir' style='padding: 10px 20px; font-size:16px; border:none;'>Sim, Excluir</button>
            <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class='btn-action' style='background-color: #555; padding: 10px 20px; font-size:16px; border:none;'>Cancelar</button>
          </div>
      </form>
    </div>
</div>

<div id="estornarModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="document.getElementById('estornarModal').style.display='none'">&times;</span>
      <h3>Confirmar Estorno</h3>
      <p>Tem certeza que deseja estornar o recebimento de <b id="estornar-nome"></b>?</p>
      <p style="color: #aaa; font-size: 0.9em;">A conta voltará para a lista de <strong>Contas a Receber (Pendentes)</strong>.</p>
      
      <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
        <a id="btn-confirm-estorno" href="#" class='btn-action btn-estornar' style='padding: 10px 20px; font-size:16px; text-decoration:none;'>Sim, Estornar</a>
        <button onclick="document.getElementById('estornarModal').style.display='none'" class='btn-action' style='background-color: #555; padding: 10px 20px; font-size:16px; border:none;'>Cancelar</button>
      </div>
    </div>
</div>

<script>
function openDeleteModal(button) {
    let id = button.getAttribute('data-id');
    let nome = button.getAttribute('data-nome');
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-nome').innerText = nome;
    document.getElementById('deleteModal').style.display = 'flex';
}

function openEstornarModal(id, nome) {
    document.getElementById('estornar-nome').innerText = nome;
    document.getElementById('btn-confirm-estorno').href = "../actions/estornar_conta_receber.php?id=" + id;
    document.getElementById('estornarModal').style.display = 'flex';
}

// === LÓGICA DE AÇÃO EM MASSA (BULK) ===
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.check-item');
    checkboxes.forEach(cb => cb.checked = source.checked);
    checkBtnState();
}

function checkBtnState() {
    const count = document.querySelectorAll('.check-item:checked').length;
    const btn = document.getElementById('btnBulkExcluir');
    if (count > 0) {
        btn.style.display = 'inline-flex';
        btn.innerHTML = `<i class="fa fa-trash"></i> Excluir (${count})`;
    } else {
        btn.style.display = 'none';
    }
}

function submitBulkExcluir() {
    const selected = Array.from(document.querySelectorAll('.check-item:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    
    if (!confirm(`Tem certeza que deseja excluir ${selected.length} registros permanentemente?`)) return;

    fetch('../actions/bulk_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            ids: selected,
            tipo: 'receber', 
            acao: 'excluir'
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            location.reload();
        } else {
            alert('Erro: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(err => alert('Erro na requisição'));
}

window.onclick = function(e) { 
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
};
</script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
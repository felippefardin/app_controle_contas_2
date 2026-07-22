<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: ../pages/login.php");
    exit();
}
$conn = getTenantConnection();
if ($conn === null) {
    die("Falha ao obter a conexão com o banco de dados do cliente.");
}

$usuarioId = $_SESSION['usuario_id'];
include('../includes/header.php');

// Exibe a mensagem centralizada
display_flash_message();

$where = ["cp.status='baixada'"];
$where[] = "cp.usuario_id = " . intval($usuarioId);

if (!empty($_GET['fornecedor'])) {
    $where[] = "cp.fornecedor LIKE '%" . $conn->real_escape_string($_GET['fornecedor']) . "%'";
}

if (!empty($_GET['data_inicio']) && !empty($_GET['data_fim'])) {
    $where[] = "cp.data_vencimento BETWEEN '" . $conn->real_escape_string($_GET['data_inicio']) . "' AND '" . $conn->real_escape_string($_GET['data_fim']) . "'";
}

$sql = "SELECT cp.*, c.nome as nome_categoria, pf.nome as nome_pessoa_fornecedor, u.nome as nome_quem_baixou
        FROM contas_pagar AS cp
        LEFT JOIN categorias AS c ON cp.id_categoria = c.id
        LEFT JOIN pessoas_fornecedores AS pf ON cp.id_pessoa_fornecedor = pf.id
        LEFT JOIN usuarios AS u ON cp.baixado_por = u.id 
        WHERE " . implode(" AND ", $where) . "
        ORDER BY cp.data_baixa DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Contas a Pagar - Baixadas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
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
      font-size: 16px; 
      border-radius: 5px; 
      border: 1px solid #444;
      background-color: #333; 
      color: #eee; 
      min-width: 200px;
      flex: 1; /* Cresce para ocupar espaço disponível */
    }

    form.search-form button, form.search-form a.clear-filters {
      color: white; 
      border: none; 
      padding: 10px 22px; 
      font-weight: bold;
      border-radius: 5px; 
      cursor: pointer; 
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 100px;
    }
    form.search-form button { background-color: #27ae60; }
    form.search-form a.clear-filters { background-color: #cc3333; }
    
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
    }

    /* === TABELA RESPONSIVA === */
    .table-responsive {
        width: 100%;
        overflow-x: auto; /* Habilita scroll horizontal no mobile */
        border-radius: 8px;
        background-color: #1f1f1f;
        margin-top: 10px;
        border: 1px solid #333;
    }

    table { 
        width: 100%; 
        background-color: #1f1f1f; 
        border-collapse: collapse; 
        min-width: 800px; /* Força largura mínima para garantir layout no scroll */
    }

    th, td { 
        padding: 12px 15px; 
        text-align: left; 
        border-bottom: 1px solid #333; 
        white-space: nowrap; /* Evita quebra de texto nas células */
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
        border: none;
        transition: opacity 0.2s;
    }
    .btn-action:hover { opacity: 0.9; }
    .btn-excluir { background-color: #cc3333; }
    .btn-comprovante { background-color: #f39c12; }
    .btn-estornar { background-color: #3498db; }
    
    /* === MODAL === */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); justify-content: center; align-items: center; padding: 10px; }
    .modal-content { background-color: #1f1f1f; padding: 25px; border-radius: 10px; width: 100%; max-width: 500px; text-align: center; position: relative; border: 1px solid #444; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    .close-btn { position: absolute; top: 10px; right: 20px; font-size: 28px; cursor: pointer; color: #aaa; }

    /* === RESPONSIVIDADE MOBILE ESPECÍFICA === */
    @media (max-width: 768px) {
        body { padding: 10px; }
        
        form.search-form { flex-direction: column; }
        form.search-form input { width: 100%; min-width: unset; }
        form.search-form button, form.search-form a.clear-filters, #btnBulkExcluir { width: 100%; }

        h2 { font-size: 1.5rem; }
    }
  </style>
</head>
<body>

<div class="main-container">

    <h2>Contas a Pagar - Baixadas</h2>

    <form class="search-form" method="GET" action="">
      <input type="text" name="fornecedor" placeholder="Fornecedor" value="<?php echo htmlspecialchars($_GET['fornecedor'] ?? ''); ?>">
      <input type="date" name="data_inicio" placeholder="Data Início" value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>">
      <input type="date" name="data_fim" placeholder="Data Fim" value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>">
      <button type="submit"><i class="fa fa-search"></i> Buscar</button>
      <a href="contas_pagar_baixadas.php" class="clear-filters">Limpar</a>
      
      <button type="button" id="btnBulkExcluir" onclick="submitBulkExcluir()">
         <i class="fa fa-trash"></i> Excluir Selecionados
      </button>
    </form>

    <?php
    if ($result && $result->num_rows > 0) {
        // Wrapper responsivo adicionado
        echo "<div class='table-responsive'>";
        echo "<table>";
        echo "<thead><tr>
                <th style='width: 40px; text-align:center;'>
                    <input type='checkbox' id='checkAll' onclick='toggleAll(this)'>
                </th>
                <th>Fornecedor</th>
                <th>Número</th> <th>Descrição</th>
                <th>Valor</th>
                <th>Baixado Por</th>
                <th>Data Baixa</th>
                <th>Categoria</th>
                <th>Comprovante</th>
                <th>Ações</th>
              </tr></thead>";
        echo "<tbody>";
        
        while($row = $result->fetch_assoc()){
            $data_baixa = $row['data_baixa'] ? date('d/m/Y', strtotime($row['data_baixa'])) : '-';
            $fornecedorDisplay = !empty($row['nome_pessoa_fornecedor']) ? $row['nome_pessoa_fornecedor'] : ($row['fornecedor'] ?? 'N/D');
            $quemBaixou = !empty($row['nome_quem_baixou']) ? $row['nome_quem_baixou'] : 'Sistema/N/D';

            echo "<tr>";
            echo "<td style='text-align:center;'>
                    <input type='checkbox' class='check-item' value='{$row['id']}' onclick='checkBtnState()'>
                  </td>";
            echo "<td>".htmlspecialchars($fornecedorDisplay)."</td>";
            echo "<td>".htmlspecialchars($row['numero'] ?? '-')."</td>";
            echo "<td>".htmlspecialchars($row['descricao'] ?? '')."</td>";
            echo "<td>R$ ".number_format($row['valor'], 2, ',', '.')."</td>";
            echo "<td>".htmlspecialchars($quemBaixou)."</td>";
            echo "<td>".$data_baixa."</td>";
            echo "<td>".htmlspecialchars($row['nome_categoria'] ?? 'N/A')."</td>";
            
            $linkComprovante = '--';
            if (!empty($row['comprovante'])) {
                $linkComprovante = "<a href='../{$row['comprovante']}' target='_blank' class='btn-action btn-comprovante'><i class='fa fa-file'></i> Ver</a>";
            }
            echo "<td>{$linkComprovante}</td>";
            
            echo "<td>
                <div style='display:flex; gap:5px;'>
                    <a href='../actions/estornar_conta_pagar.php?id={$row['id']}' class='btn-action btn-estornar' onclick=\"return confirm('Tem certeza que deseja estornar esta conta?');\"><i class='fa-solid fa-undo'></i> Estornar</a>
                    
                    <button 
                        class='btn-action btn-excluir' 
                        data-id='{$row['id']}' 
                        data-nome='".htmlspecialchars($fornecedorDisplay, ENT_QUOTES)."' 
                        onclick='openDeleteModal(this)'>
                        <i class='fa-solid fa-trash'></i> Excluir
                    </button>
                </div>
            </td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div>"; // Fecha wrapper responsivo
    } else {
        if (!$result) {
            echo "<p style='color: #ff6b6b; text-align:center;'>Erro na consulta.</p>";
        } else {
            echo "<p style='text-align:center;'>Nenhuma conta baixada encontrada.</p>";
        }
    }
    ?>

</div> <div id="deleteModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
      <h3>Confirmar Exclusão</h3>
      <p>Tem certeza de que deseja excluir esta conta baixada?</p>
      <p><strong>Fornecedor:</strong> <span id="delete-nome"></span></p>
      
      <form action="../actions/excluir_conta_pagar.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <input type="hidden" name="id" id="delete-id">
          <input type="hidden" name="redirect" value="baixadas">
          
          <div style="margin-top: 20px;">
            <button type="submit" class='btn-action btn-excluir' style='padding: 10px 20px; font-size:16px;'>Sim, Excluir</button>
            <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class='btn-action' style='background-color: #555; padding: 10px 20px; font-size:16px;'>Cancelar</button>
          </div>
      </form>
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
            tipo: 'pagar', 
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

window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteModal');
    if (event.target == deleteModal) { deleteModal.style.display = 'none'; }
};
</script>

<?php include('../includes/footer.php'); ?>
</body>
</html>
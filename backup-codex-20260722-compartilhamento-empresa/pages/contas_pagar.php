<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Função Display Flash está aqui

// 1. VERIFICA LOGIN
if (!isset($_SESSION['usuario_logado'])) {
    header("Location: ../pages/login.php");
    exit();
}
$conn = getTenantConnection();
if ($conn === null) die("Falha de conexão.");

$usuarioId = $_SESSION['usuario_id'];

// AJAX Search
if (isset($_GET['action']) && $_GET['action'] === 'search_fornecedor') {
    $term = $_GET['term'] ?? '';
    $stmt = $conn->prepare("SELECT id, nome FROM pessoas_fornecedores WHERE id_usuario = ? AND nome LIKE ? AND tipo = 'fornecedor' ORDER BY nome ASC LIMIT 10");
    $searchTerm = "%{$term}%";
    $stmt->bind_param("is", $usuarioId, $searchTerm);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

include('../includes/header.php');

// --- EXIBE O FLASH MESSAGE CENTRALIZADO ---
display_flash_message();
// -----------------------------------------

// Categorias
$stmt = $conn->prepare("SELECT id, nome FROM categorias WHERE id_usuario = ? AND tipo = 'despesa' ORDER BY nome ASC");
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$categorias_despesa = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Query Principal
$where = ["cp.status='pendente'", "cp.usuario_id = " . intval($usuarioId)];
if (!empty($_GET['data_inicio'])) $where[] = "cp.data_vencimento >= '" . $conn->real_escape_string($_GET['data_inicio']) . "'";
if (!empty($_GET['data_fim'])) $where[] = "cp.data_vencimento <= '" . $conn->real_escape_string($_GET['data_fim']) . "'";

$sql = "SELECT cp.*, c.nome as nome_categoria, pf.nome as nome_pessoa_fornecedor
        FROM contas_pagar AS cp
        LEFT JOIN categorias AS c ON cp.id_categoria = c.id
        LEFT JOIN pessoas_fornecedores AS pf ON cp.id_pessoa_fornecedor = pf.id
        WHERE " . implode(" AND ", $where) . " ORDER BY cp.data_vencimento ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contas a Pagar</title> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* === GERAL === */
    * { box-sizing: border-box; }
    body { background-color: #121212; color: #eee; font-family: Arial, sans-serif; margin: 0; min-height: 100vh; }
    
    /* === CONTAINER RESPONSIVO (FULL DESKTOP) === */
    .main-container {
        width: 100%;
        max-width: 1600px; /* Limite para telas ultrawide */
        margin: 0 auto;
        padding-bottom: 50px;
    }

    h2 { text-align: center; color: #00bfff; margin-bottom: 20px; }
    
    /* === MODAL === */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); justify-content: center; align-items: center; padding: 10px; }
    .modal-content { background-color: #1f1f1f; padding: 25px; border-radius: 10px; width: 100%; max-width: 500px; border: 1px solid #444; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    .close-btn { position: absolute; top: 10px; right: 15px; font-size: 28px; cursor: pointer; color: #aaa; z-index: 10; }
    
    /* === FORMULÁRIO DE BUSCA === */
    form.search-form { 
        display: flex; 
        flex-wrap: wrap; 
        justify-content: center; 
        gap: 10px; 
        margin-bottom: 25px; 
        align-items: center; 
        width: 100%;
    }
    
    /* Inputs responsivos */
    input, select { 
        padding: 10px; 
        background: #333; 
        border: 1px solid #444; 
        color: #eee; 
        border-radius: 5px; 
        font-size: 14px;
        flex: 1; /* Cresce para ocupar espaço */
        min-width: 150px;
    }
    
    /* === TABELA RESPONSIVA === */
    .table-responsive {
        width: 100%;
        overflow-x: auto; /* Scroll horizontal no mobile */
        border-radius: 8px;
        border: 1px solid #333;
        background-color: #1f1f1f;
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
        white-space: nowrap; /* Evita quebra de texto */
    }
    
    th { background-color: #222; color: #00bfff; font-weight: bold; }
    tr:nth-child(even) { background-color: #2a2a2a; }
    tr.vencido { background-color: rgba(220, 53, 69, 0.2) !important; } /* Cor suavizada para ficar melhor no dark mode */

    /* === BOTÕES === */
    .btn { 
        padding: 10px 16px; 
        border-radius: 5px; 
        border: none; 
        cursor: pointer; 
        font-weight: bold; 
        color: white; 
        text-decoration: none; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center;
        gap: 5px;
        font-size: 14px;
        transition: opacity 0.2s;
        min-width: fit-content;
    }
    .btn:hover { opacity: 0.9; }

    .btn-add { background-color: #00bfff; }
    .btn-search { background-color: #27ae60; }
    .btn-clear { background-color: #c0392b; }
    .btn-export { background-color: #f39c12; }
    
    /* Botões de Ação na Tabela */
    .btn-action { padding: 6px 10px; margin: 0 2px; font-size: 13px; }
    .btn-baixar { background: #27ae60; }
    .btn-editar { background: #00bfff; }
    .btn-excluir { background: #c0392b; }
    .btn-repetir { background: #f39c12; }
    
    /* === AUTOCOMPLETE === */
    .autocomplete-container { position: relative; width: 100%; }
    .autocomplete-items { position: absolute; border: 1px solid #444; z-index: 99; top: 100%; left: 0; right: 0; background-color: #333; max-height: 150px; overflow-y: auto; }
    .autocomplete-items div { padding: 10px; cursor: pointer; border-bottom: 1px solid #444; }
    .autocomplete-items div:hover { background-color: #555; }

    /* === RESPONSIVIDADE (MOBILE e TABLET) === */
    @media (max-width: 768px) {
        body { padding: 10px; }
        
        /* Formulário empilhado no mobile */
        form.search-form { flex-direction: column; align-items: stretch; }
        form.search-form input, form.search-form button, form.search-form a { width: 100%; margin: 2px 0; }
        
        .main-content { margin-top: 20px; }
        
        h2 { font-size: 1.5rem; }
        
        /* Modal ocupa mais espaço no mobile */
        .modal-content { width: 95%; margin: 10px; max-height: 90vh; overflow-y: auto; }
    }
  </style>
  
</head>
<body>

<div class="main-container">

    <h2>Contas a Pagar</h2>

    <form class="search-form" method="GET">
      <input type="date" name="data_inicio" value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>" title="Data Início">
      <input type="date" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>" title="Data Fim">
      
      <button type="submit" class="btn btn-search" title="Filtrar"><i class="fa fa-search"></i> Buscar</button>
      <a href="contas_pagar.php" class="btn btn-clear" title="Limpar Filtros"><i class="fa fa-eraser"></i> Limpar</a>

      <button type="button" class="btn btn-add" onclick="document.getElementById('addContaModal').style.display='flex'">➕ Nova</button>
      <button type="button" class="btn btn-export" onclick="document.getElementById('exportModal').style.display='flex'"><i class="fa fa-download"></i> Exportar</button>

      <button type="button" class="btn btn-search" id="btnBulkBaixar" style="display:none; background-color: #27ae60;" onclick="abrirModalBulk()"><i class="fa fa-check-double"></i> Baixar Selecionados</button>
    </form>

    <?php if ($result && $result->num_rows > 0): ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
        <th style="width: 40px; text-align:center;">
            <input type="checkbox" id="checkAll" onclick="toggleAll(this)">
        </th>
                    <th>Fornecedor</th>
                    <th>Número</th>
                    <th>Descrição</th>
                    <th>Vencimento</th>
                    <th>Categoria</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php $hoje = date('Y-m-d'); 
            while($row = $result->fetch_assoc()): 
                $vencido = ($row['data_vencimento'] < $hoje) ? 'vencido' : '';
                $nome = !empty($row['nome_pessoa_fornecedor']) ? $row['nome_pessoa_fornecedor'] : 'N/D';
            ?>
                <tr class="<?= $vencido ?>">
    <td style="text-align:center;">
        <input type="checkbox" class="check-item" value="<?= $row['id'] ?>" onclick="checkBtnState()">
    </td>
                    <td><?= htmlspecialchars($nome) ?></td>
                    <td><?= htmlspecialchars($row['numero'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['descricao'] ?? '') ?></td>
                    <td><?= date('d/m/Y', strtotime($row['data_vencimento'])) ?></td>
                    <td><?= htmlspecialchars($row['nome_categoria'] ?? '-') ?></td>
                    <td>R$ <?= number_format($row['valor'], 2, ',', '.') ?></td>
                    <td><?= $vencido ? 'Vencido' : 'Em dia' ?></td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <button onclick="abrirModalBaixar(<?= $row['id'] ?>, '<?= addslashes($nome) ?>', '<?= $row['valor'] ?>')" class="btn btn-action btn-baixar" title="Dar Baixa"><i class="fa fa-check"></i></button>
                            <a href="editar_conta_pagar.php?id=<?= $row['id'] ?>" class="btn btn-action btn-editar"><i class="fa fa-pen"></i></a>
                            <button onclick="abrirModalRepetir(<?= $row['id'] ?>)" class="btn btn-action btn-repetir"><i class="fa-solid fa-repeat"></i></button>
                            
                            <button 
                                type="button"
                                class="btn btn-action btn-excluir" 
                                data-id="<?= $row['id'] ?>" 
                                data-nome="<?= htmlspecialchars($nome) ?>"
                                onclick="openDeleteModal(this)"
                                title="Excluir">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="text-align:center; margin-top:20px; color: #aaa;">Nenhuma conta pendente encontrada.</p>
    <?php endif; ?>

<div id="modalBulk" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="document.getElementById('modalBulk').style.display='none'">&times;</span>
    <h3>Baixar Múltiplas Contas</h3>
    <p>Selecione os dados para baixar as contas marcadas:</p>
    <div style="display:flex; flex-direction:column; gap:10px;">
        <label>Data do Pagamento:</label>
        <input type="date" id="bulk_data" value="<?= date('Y-m-d') ?>">
        <label>Forma de Pagamento:</label>
        <select id="bulk_forma">
            <option value="dinheiro">Dinheiro</option>
            <option value="pix">Pix</option>
            <option value="boleto">Boleto</option>
            <option value="transferencia">Transferência</option>
        </select>
        <button onclick="submitBulk()" class="btn btn-baixar" style="margin-top:10px;">Confirmar Baixa em Massa</button>
    </div>
  </div>
</div>

</div> <div id="addContaModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
    <h3>Nova Conta</h3>
    <form action="../actions/add_conta_pagar.php" method="POST" style="display:flex; flex-direction:column; gap:10px;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div style="display:flex; gap: 5px; align-items: center;">
            <div class="autocomplete-container" style="flex: 1; margin-bottom:0;">
                <input type="text" id="pesquisar_fornecedor" name="fornecedor_nome" placeholder="Fornecedor..." required style="width:100%;">
                <div id="fornecedor_list" class="autocomplete-items"></div>
            </div>
            <button type="button" class="btn btn-add" style="padding: 10px 12px;" onclick="document.getElementById('modalNovoFornecedor').style.display='flex'">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <input type="hidden" name="fornecedor_id" id="fornecedor_id_hidden">
        
        <input type="text" name="numero" placeholder="Número do Documento">
        <input type="text" name="descricao" placeholder="Descrição" required>
        <input type="text" name="valor" placeholder="Valor (Ex: 1.200,00)" required>
        <input type="date" name="data_vencimento" required>
        
        <div style="display:flex; gap: 5px; align-items: center;">
            <select name="id_categoria" id="select_categoria_pagar" required style="flex: 1; margin-bottom:0;">
                <option value="">Categoria...</option>
                <?php foreach ($categorias_despesa as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= $cat['nome'] ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-add" style="padding: 10px 12px;" onclick="document.getElementById('modalNovaCategoria').style.display='flex'">
                <i class="fas fa-plus"></i>
            </button>
        </div>

        <button type="submit" class="btn btn-add">Salvar</button>
    </form>
  </div>
</div>

<div id="baixarModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
    <h3>Dar Baixa na Conta</h3>
    <p id="texto-baixa" style="color:#aaa; margin-bottom:15px; text-align: center;"></p>
    
    <form action="../actions/baixar_conta.php" method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:10px;">
        <input type="hidden" name="id_conta" id="id_conta_baixa">
        <label>Data do Pagamento:</label>
        <input type="date" name="data_baixa" value="<?= date('Y-m-d') ?>" required>
        <label>Forma de Pagamento:</label>
        <select name="forma_pagamento" required>
            <option value="dinheiro">Dinheiro</option>
            <option value="pix">Pix</option>
            <option value="cartao_credito">Cartão de Crédito</option>
            <option value="cartao_debito">Cartão de Débito</option>
            <option value="transferencia">Transferência</option>
            <option value="boleto">Boleto</option>
        </select>
        <label>Anexar Comprovante (Opcional):</label>
        <input type="file" name="comprovante" accept="image/*,.pdf">
        <button type="submit" class="btn btn-baixar" style="margin-top:10px;">Confirmar Baixa</button>
    </form>
  </div>
</div>

<div id="repetirModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
    <h3>Repetir Conta</h3>
    <form action="../actions/repetir_conta_pagar.php" method="POST" style="display:flex; flex-direction:column; gap:10px;">
        <input type="hidden" name="conta_id" id="repetir_conta_id">
        <input type="number" name="repetir_vezes" placeholder="Quantas vezes?" required>
        <input type="number" name="repetir_intervalo" value="30" placeholder="Intervalo dias">
        <button type="submit" class="btn btn-repetir">Repetir</button>
    </form>
  </div>
</div>

<div id="exportModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('exportModal').style.display='none'">&times;</span>
        <h3>Exportar Relatório</h3>
        <form action="../actions/exportar_contas_pagar.php" method="GET" target="_blank" style="display:flex; flex-direction:column; gap:10px;">
            <label style="text-align:left; color:#ccc; font-size:12px;">Tipo de Relatório:</label>
            <select name="status" required>
                <option value="pendente">Contas a Pagar (Pendentes)</option>
                <option value="baixada">Contas Pagas (Baixadas)</option>
            </select>
            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label style="display:block; text-align:left; color:#ccc; font-size:12px;">De:</label>
                    <input type="date" name="data_inicio" value="<?= date('Y-m-01') ?>" required style="width:100%;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; text-align:left; color:#ccc; font-size:12px;">Até:</label>
                    <input type="date" name="data_fim" value="<?= date('Y-m-t') ?>" required style="width:100%;">
                </div>
            </div>
            <label style="text-align:left; color:#ccc; font-size:12px;">Formato:</label>
            <select name="formato" required>
                <option value="excel">Excel (.xlsx)</option>
                <option value="pdf">PDF (.pdf)</option>
                <option value="csv">CSV (.csv)</option>
            </select>
            <button type="submit" class="btn btn-export" style="margin-top:10px;">Baixar Arquivo</button>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
        <h3>Confirmar Exclusão</h3>
        <p>Tem certeza que deseja excluir a conta de <b id="delete-nome"></b>?</p>
        <form action="../actions/excluir_conta_pagar.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="delete-id">
            <input type="hidden" name="redirect" value="">
            <div style="margin-top:20px; display:flex; justify-content:center; gap:10px;">
                <button type="submit" class="btn btn-excluir">Sim, Excluir</button>
                <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class="btn" style="background-color:#555;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalNovoFornecedor" class="modal" style="z-index: 1050;"> 
  <div class="modal-content">
    <span class="close-btn" onclick="document.getElementById('modalNovoFornecedor').style.display='none'">&times;</span>
    <h3>Novo Fornecedor</h3>
    <form id="form-novo-fornecedor" style="display:flex; flex-direction:column; gap:10px;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="tipo" value="fornecedor">
        
        <label>Nome Completo:</label>
        <input type="text" name="nome" required placeholder="Nome do Fornecedor">
        
        <label>CPF/CNPJ (Opcional):</label>
        <input type="text" name="cpf_cnpj" placeholder="Documento">
        
        <label>Endereço:</label>
        <input type="text" name="endereco" placeholder="Endereço Completo">
        
        <label>Contato (Telefone):</label>
        <input type="text" name="contato" placeholder="(00) 00000-0000">
        
        <label>E-mail:</label>
        <input type="email" name="email" placeholder="email@exemplo.com">
        
        <button type="submit" class="btn btn-add" style="width:100%; margin-top:10px;">Cadastrar</button>
    </form>
  </div>
</div>

<div id="modalNovaCategoria" class="modal" style="z-index: 1060;">
  <div class="modal-content">
    <span class="close-btn" onclick="document.getElementById('modalNovaCategoria').style.display='none'">&times;</span>
    <h3>Nova Categoria (Despesa)</h3>
    <form id="form-nova-categoria" style="display:flex; flex-direction:column; gap:10px;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="tipo" value="despesa"> 
        <label>Nome da Categoria:</label>
        <input type="text" name="nome" required placeholder="Ex: Escritório, Transporte...">
        <button type="submit" class="btn btn-add" style="width:100%; margin-top:10px;">Salvar Categoria</button>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Função Display Flash simplificada para JS
function showFlash(message, type) {
    const alertBox = document.createElement('div');
    alertBox.style.cssText = `
        position: fixed; top: 20px; right: 20px; background: ${type === 'success' ? '#28a745' : '#dc3545'}; 
        color: white; padding: 15px; border-radius: 5px; z-index: 9999; box-shadow: 0 0 10px rgba(0,0,0,0.5);
    `;
    alertBox.innerText = message;
    document.body.appendChild(alertBox);
    setTimeout(() => alertBox.remove(), 4000);
}

function abrirModalBaixar(id, nome, valor) {
    document.getElementById('id_conta_baixa').value = id;
    document.getElementById('texto-baixa').innerText = `${nome} - R$ ${valor}`;
    document.getElementById('baixarModal').style.display = 'flex';
}
function abrirModalRepetir(id) {
    document.getElementById('repetir_conta_id').value = id;
    document.getElementById('repetirModal').style.display = 'flex';
}
function openDeleteModal(button) {
    let id = button.getAttribute('data-id');
    let nome = button.getAttribute('data-nome');
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-nome').innerText = nome;
    document.getElementById('deleteModal').style.display = 'flex';
}
$("#pesquisar_fornecedor").on("keyup", function() {
    let term = $(this).val();
    if (term.length < 2) return $("#fornecedor_list").empty();
    $.getJSON("contas_pagar.php", { action: 'search_fornecedor', term: term }, function(data) {
        let html = data.map(i => `<div onclick="selectForn(${i.id}, '${i.nome}')">${i.nome}</div>`).join('');
        $("#fornecedor_list").html(html);
    });
});
function selectForn(id, nome) {
    $("#pesquisar_fornecedor").val(nome);
    $("#fornecedor_id_hidden").val(id);
    $("#fornecedor_list").empty();
}

// AJAX Cadastro Fornecedor
$('#form-novo-fornecedor').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', true);
    
    $.ajax({
        url: '../actions/cadastrar_pessoa_fornecedor_action.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(resp) {
            document.getElementById('modalNovoFornecedor').style.display='none';
            document.getElementById('form-novo-fornecedor').reset();
            
            // Pega o nome que foi digitado para preencher o campo anterior
            const nomeDigitado = formData.get('nome');
            $("#pesquisar_fornecedor").val(nomeDigitado);
            
            showFlash('Fornecedor cadastrado com sucesso!', 'success');
        },
        error: function() {
            showFlash('Erro ao cadastrar fornecedor.', 'danger');
        }
    });
});

// AJAX Salvar Nova Categoria (Despesa)
$('#form-nova-categoria').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', 'true'); // Sinaliza que é AJAX

    $.ajax({
        url: '../actions/salvar_categoria.php', 
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(resp) {
            if(resp.status === 'success' || resp.id) {
                // Adiciona a nova opção no Select
                let novaOpcao = new Option(formData.get('nome'), resp.id, true, true);
                $('#select_categoria_pagar').append(novaOpcao).trigger('change');

                document.getElementById('modalNovaCategoria').style.display='none';
                document.getElementById('form-nova-categoria').reset();
                showFlash('Categoria criada com sucesso!', 'success');
            } else {
                showFlash(resp.message || 'Erro ao criar categoria.', 'danger');
            }
        },
        error: function() {
            showFlash('Erro na comunicação com o servidor.', 'danger');
        }
    });
});

window.onclick = e => { if(e.target.className === 'modal') e.target.style.display = 'none'; }

// Lógica dos Checkboxes
function toggleAll(source) {
    checkboxes = document.getElementsByClassName('check-item');
    for(var i=0, n=checkboxes.length;i<n;i++) {
        checkboxes[i].checked = source.checked;
    }
    checkBtnState();
}

function checkBtnState() {
    const checkboxes = document.querySelectorAll('.check-item:checked');
    const btn = document.getElementById('btnBulkBaixar');
    if(checkboxes.length > 0) {
        btn.style.display = 'inline-flex';
        btn.innerHTML = `<i class="fa fa-check-double"></i> Baixar (${checkboxes.length})`;
    } else {
        btn.style.display = 'none';
    }
}

function abrirModalBulk() {
    document.getElementById('modalBulk').style.display = 'flex';
}

function submitBulk() {
    const ids = Array.from(document.querySelectorAll('.check-item:checked')).map(cb => cb.value);
    const data_baixa = document.getElementById('bulk_data').value;
    const forma = document.getElementById('bulk_forma').value;

    if (ids.length === 0) return;

    if(!confirm(`Confirma a baixa de ${ids.length} contas?`)) return;

    fetch('../actions/bulk_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            ids: ids,
            tipo: 'pagar', // Mude para 'receber' na pagina de contas_receber
            acao: 'baixar',
            data_baixa: data_baixa,
            forma_pagamento: forma
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}
</script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
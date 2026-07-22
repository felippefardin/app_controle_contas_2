<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

// 1. VERIFICA LOGIN
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}
$conn = getTenantConnection();
if ($conn === null) {
    die("Falha ao obter a conexão com o banco de dados do cliente.");
}

// 2. PEGA DADOS
$usuarioId = $_SESSION['usuario_id'];

// --- PROCESSAMENTO DE FORMULÁRIOS (POST) ---

// A) SALVAR NOVO PRODUTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_modal']) && $_POST['acao_modal'] === 'salvar_novo_produto') {
    $codigo      = $_POST['codigo'];
    $nome        = $_POST['nome'];
    $descricao   = $_POST['descricao']; 
    // $categoria   = $_POST['categoria']; // Removido pois não consta no schema padrão
    
    $qtd_inicial = intval($_POST['quantidade_inicial']);
    $qtd_minima  = intval($_POST['quantidade_minima']); 
    
    $ncm         = $_POST['ncm']; 
    $cfop        = $_POST['cfop']; 

    // Tratamento de Moeda
    $preco_custo = str_replace(['.', ','], ['', '.'], $_POST['preco_custo']);
    $preco_venda = str_replace(['.', ','], ['', '.'], $_POST['preco_venda']);

    $sqlInsert = "INSERT INTO produtos (codigo, nome, descricao, quantidade_estoque, quantidade_minima, preco_compra, preco_venda, ncm, cfop, id_usuario) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sqlInsert);
    // sssiiddssi
    $stmt->bind_param("sssiiddssi", $codigo, $nome, $descricao, $qtd_inicial, $qtd_minima, $preco_custo, $preco_venda, $ncm, $cfop, $usuarioId);
    
    if ($stmt->execute()) {
        // CORREÇÃO AQUI: Usando a função do utils.php
        set_flash_message("success", "Produto cadastrado com sucesso!");
    } else {
        set_flash_message("danger", "Erro ao cadastrar produto: " . $stmt->error);
    }
    header("Location: compras.php");
    exit;
}

// B) SALVAR NOVO FORNECEDOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_modal']) && $_POST['acao_modal'] === 'salvar_novo_fornecedor') {
    $nome     = $_POST['nome'];
    $cpf_cnpj = $_POST['cpf_cnpj'];
    $email    = $_POST['email'];
    $contato  = $_POST['contato']; // Telefone
    $endereco = $_POST['endereco'];
    $tipo     = 'fornecedor';

    $sqlInsert = "INSERT INTO pessoas_fornecedores (nome, cpf_cnpj, email, contato, endereco, tipo, id_usuario) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sqlInsert);
    $stmt->bind_param("ssssssi", $nome, $cpf_cnpj, $email, $contato, $endereco, $tipo, $usuarioId);
    
    if ($stmt->execute()) {
        // CORREÇÃO AQUI: Usando a função do utils.php
        set_flash_message("success", "Fornecedor cadastrado com sucesso!");
    } else {
        set_flash_message("danger", "Erro ao cadastrar fornecedor: " . $stmt->error);
    }
    header("Location: compras.php");
    exit;
}

// --- BLOCO AJAX (Busca existente) ---
if (isset($_GET['action'])) {
    $term = "%" . ($_GET['term'] ?? '') . "%";
    $response = [];

    if ($_GET['action'] === 'search_fornecedores') {
        $sql = "SELECT id, nome FROM pessoas_fornecedores WHERE tipo = 'fornecedor' AND nome LIKE ? AND id_usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $term, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response[] = ['id' => $row['id'], 'text' => $row['nome']];
        }
    }

    if ($_GET['action'] === 'search_produtos') {
        $sql = "SELECT id, nome, preco_compra, quantidade_estoque FROM produtos WHERE nome LIKE ? AND id_usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $term, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response[] = [
                'id' => $row['id'],
                'text' => $row['nome'] . " (Estoque: " . $row['quantidade_estoque'] . ")",
                'preco_compra' => $row['preco_compra']
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['results' => $response]);
    exit;
}

include('../includes/header.php');
// Limpa msg antigas erradas se existirem
if (isset($_SESSION['flash_message']) && !isset($_SESSION['flash_message']['tipo'])) {
    unset($_SESSION['flash_message']);
}
display_flash_message();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de Compra</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #121212; color: #eee; }
        .container { 
            background-color: #222; 
            padding: 25px; 
            border-radius: 8px; 
            margin-top: 30px;
            margin-bottom: 30px;
            max-width: 1200px; 
        }
        h1, h2, h4 { color: #eee; }
        h1, h2 { border-bottom: 2px solid #0af; padding-bottom: 10px; margin-bottom: 20px; }
        .form-control, .select2-container .select2-selection--single { 
            background-color: #333; 
            color: #eee; 
            border-color: #444; 
            height: calc(1.5em + .75rem + 2px); 
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered { 
            color: #eee; line-height: 38px; 
        }
        .select2-dropdown { background-color: #333; border-color: #444; }
        .select2-results__option { color: #eee; }
        .select2-results__option--highlighted { background-color: #0af !important; }
        .select2-container { width: 100% !important; }
        .table { color: #eee; }
        .table thead th { border-top: none; border-bottom: 2px solid #0af; }
        .table-bordered td, .table-bordered th { border: 1px solid #444; }
        .total-compra { font-size: 1.5rem; font-weight: bold; color: #28a745; }
        .modal-content { background-color: #222; color: #eee; border: 1px solid #444; }
        .modal-header, .modal-footer { border-color: #444; }
        .close { color: #eee; }
        @media (max-width: 768px) {
            .container { padding: 15px; margin-top: 15px; width: 95%; }
            h1 { font-size: 1.5rem; }
            h2 { font-size: 1.3rem; }
            .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-dolly"></i> Registrar Compra</h1>

    <form action="../actions/registrar_compra.php" method="POST" id="form-compra">
        <div class="form-group">
            <label for="fornecedor_id">Fornecedor</label>
            <div class="input-group">
                <select id="fornecedor_id" name="id_fornecedor" class="form-control" required></select>
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#modalNovoFornecedor">
                        <i class="fas fa-plus"></i> Novo
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card bg-dark text-white mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0" style="border:none; padding:0; font-size: 1.25rem; margin:0;">Adicionar Produtos</h2>
                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modalNovoProduto">
                    <i class="fas fa-plus"></i> Novo Produto
                </button>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-8 col-12">
                        <label>Pesquisar Produto Existente</label>
                        <select id="produto_select" class="form-control"></select>
                    </div>
                    <div class="form-group col-md-4 col-12 d-flex align-items-end">
                        <button type="button" id="add-produto" class="btn btn-success btn-block">Adicionar à Compra</button>
                    </div>
                </div>
            </div>
        </div>

        <h2><i class="fas fa-boxes"></i> Itens da Compra</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th style="width: 120px;">Qtd</th>
                        <th style="width: 150px;">Custo Un.</th>
                        <th>Subtotal</th>
                        <th style="width: 80px;">Ação</th>
                    </tr>
                </thead>
                <tbody id="compra-items"></tbody>
            </table>
        </div>
        <div class="text-right mt-3">
            <h3 class="total-compra">Total: R$ <span id="total-geral">0.00</span></h3>
            <input type="hidden" name="valor_total" id="valor_total_hidden" value="0">
        </div>

        <div class="form-group mt-4">
            <label for="observacao">Observações</label>
            <textarea name="observacao" class="form-control" rows="3"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary btn-lg btn-block mt-4 mb-3">Finalizar Compra</button>
    </form>
</div>

<div class="modal fade" id="modalNovoProduto" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form action="compras.php" method="POST">
          <div class="modal-header">
            <h4 class="modal-title">Cadastrar Novo Produto</h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="acao_modal" value="salvar_novo_produto">

              <div class="row">
                  <div class="col-md-4">
                      <div class="form-group">
                          <label>Código / SKU:</label>
                          <input type="text" name="codigo" class="form-control" placeholder="Opcional">
                      </div>
                  </div>
                  <div class="col-md-8">
                      <div class="form-group">
                          <label>Nome do Produto:</label>
                          <input type="text" name="nome" class="form-control" required>
                      </div>
                  </div>
              </div>

              <div class="form-group">
                  <label>Descrição:</label>
                  <textarea name="descricao" class="form-control" rows="2"></textarea>
              </div>

              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>Quantidade Inicial:</label>
                          <input type="number" name="quantidade_inicial" class="form-control" value="0" min="0" required>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>Quantidade Mínima:</label>
                          <input type="number" name="quantidade_minima" class="form-control" value="0" min="0">
                      </div>
                  </div>
              </div>

              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>Preço de Custo (R$):</label>
                          <input type="text" name="preco_custo" class="form-control input-dinheiro" placeholder="0,00" required>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>Preço de Venda (R$):</label>
                          <input type="text" name="preco_venda" class="form-control input-dinheiro" placeholder="0,00" required>
                      </div>
                  </div>
              </div>

              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>NCM:</label>
                          <input type="text" name="ncm" class="form-control" placeholder="Ex: 84713000">
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>CFOP:</label>
                          <input type="text" name="cfop" class="form-control" placeholder="Ex: 5102">
                      </div>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success">Salvar Produto</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalNovoFornecedor" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="compras.php" method="POST">
          <div class="modal-header">
            <h4 class="modal-title">Cadastrar Novo Fornecedor</h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="acao_modal" value="salvar_novo_fornecedor">

              <div class="form-group">
                  <label>Nome / Razão Social:</label>
                  <input type="text" name="nome" class="form-control" required>
              </div>

              <div class="form-group">
                  <label>CPF / CNPJ:</label>
                  <input type="text" name="cpf_cnpj" class="form-control mask-doc" placeholder="Apenas números">
              </div>

              <div class="row">
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>Email:</label>
                          <input type="email" name="email" class="form-control" required>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="form-group">
                          <label>Telefone / Contato:</label>
                          <input type="text" name="contato" class="form-control mask-phone">
                      </div>
                  </div>
              </div>

              <div class="form-group">
                  <label>Endereço:</label>
                  <input type="text" name="endereco" class="form-control">
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success">Salvar Fornecedor</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// --- MÁSCARAS ---
function mascaraMoeda(event) {
    const onlyDigits = event.target.value.split("").filter(s => /\d/.test(s)).join("").padStart(3, "0");
    const digitsFloat = onlyDigits.slice(0, -2) + "." + onlyDigits.slice(-2);
    event.target.value = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(digitsFloat);
}

document.querySelectorAll('.input-dinheiro').forEach(input => input.addEventListener('input', mascaraMoeda));

// Máscara simples para telefone e documento (visual)
document.querySelectorAll('.mask-phone').forEach(input => {
    input.addEventListener('input', e => {
        let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
        e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
    });
});

$(document).ready(function() {
    const select2Config = {
        width: '100%',
        language: { noResults: () => "Nenhum resultado", searching: () => "Pesquisando..." }
    };

    // Select2 Fornecedores
    $('#fornecedor_id').select2({
        ...select2Config,
        placeholder: 'Selecione um fornecedor',
        ajax: {
            url: 'compras.php?action=search_fornecedores',
            dataType: 'json', delay: 250,
            processResults: data => ({ results: data.results }), cache: true
        }
    });

    // Select2 Produtos
    $('#produto_select').select2({
        ...select2Config,
        placeholder: 'Pesquisar produto...',
        ajax: {
            url: 'compras.php?action=search_produtos',
            dataType: 'json', delay: 250,
            processResults: data => ({ results: data.results }), cache: true
        }
    });

    // Adicionar produto
    $('#add-produto').on('click', function() {
        var produtoData = $('#produto_select').select2('data')[0];
        if (!produtoData || !produtoData.id) { alert('Selecione um produto.'); return; }

        var produtoId = produtoData.id;
        var produtoNome = produtoData.text.split(' (Estoque:')[0];
        var precoCompra = parseFloat(produtoData.preco_compra || 0).toFixed(2);

        if ($('#compra-items').find(`tr[data-id="${produtoId}"]`).length > 0) {
            alert('Este produto já foi adicionado.'); return;
        }
        
        var row = `
            <tr data-id="${produtoId}">
                <td class="align-middle">${produtoNome}<input type="hidden" name="produtos[${produtoId}][id]" value="${produtoId}"></td>
                <td><input type="number" name="produtos[${produtoId}][quantidade]" class="form-control quantidade" value="1" min="1" required></td>
                <td><input type="text" name="produtos[${produtoId}][preco]" class="form-control preco" value="${precoCompra}" required></td>
                <td class="subtotal align-middle">${precoCompra}</td>
                <td class="align-middle text-center"><button type="button" class="btn btn-danger btn-sm remover-item"><i class="fas fa-trash"></i></button></td>
            </tr>`;
        $('#compra-items').append(row);
        atualizarTotal();
        $('#produto_select').val(null).trigger('change');
    });

    $('#compra-items').on('click', '.remover-item', function() {
        $(this).closest('tr').remove();
        atualizarTotal();
    });

    $('#compra-items').on('input', '.quantidade, .preco', function() {
        var tr = $(this).closest('tr');
        var qtd = parseInt(tr.find('.quantidade').val()) || 0;
        var preco = parseFloat(tr.find('.preco').val().replace(',', '.')) || 0;
        if (qtd < 1) qtd = 0;
        tr.find('.subtotal').text((qtd * preco).toFixed(2));
        atualizarTotal();
    });

    function atualizarTotal() {
        var total = 0;
        $('#compra-items tr').each(function() {
            total += parseFloat($(this).find('.subtotal').text()) || 0;
        });
        $('#total-geral').text(total.toFixed(2));
        $('#valor_total_hidden').val(total.toFixed(2));
    }

    $('#form-compra').on('submit', function(e){
        if ($('#compra-items tr').length === 0) {
            e.preventDefault(); alert('Adicione pelo menos um produto.');
        }
    });
});
</script>

<?php include('../includes/footer.php'); ?>
</body>
</html>
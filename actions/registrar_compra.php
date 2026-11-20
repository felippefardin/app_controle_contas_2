<?php
declare(strict_types=1);

require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/compras.php');
    exit;
}

require_csrf();

$conn = getTenantConnection();
if ($conn === null) {
    set_flash_message('danger', 'Falha ao obter a conexão com o banco de dados do cliente.');
    header('Location: ../pages/compras.php');
    exit;
}

function vencimentoParcela(string $primeiroVencimento, int $mesesDepois): string
{
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $primeiroVencimento);
    if (!$base) {
        throw new RuntimeException('Data de vencimento inválida.');
    }
    $dia = (int)$base->format('d');
    $mesAlvo = $base->modify('first day of this month')->modify('+' . $mesesDepois . ' months');
    $ultimoDia = (int)$mesAlvo->format('t');
    return $mesAlvo->setDate((int)$mesAlvo->format('Y'), (int)$mesAlvo->format('m'), min($dia, $ultimoDia))->format('Y-m-d');
}

function numeroParcela(string $numeroDocumento, int $idCompra, int $parcela, int $totalParcelas): string
{
    $base = trim($numeroDocumento) !== '' ? trim($numeroDocumento) : 'COMP-' . str_pad((string)$idCompra, 6, '0', STR_PAD_LEFT);
    $sufixo = $totalParcelas > 1 ? '/' . $parcela : '';
    return mb_substr($base, 0, max(1, 20 - mb_strlen($sufixo))) . $sufixo;
}

$idUsuario = get_data_owner_id();
$idFornecedor = (int)($_POST['id_fornecedor'] ?? 0);
$observacao = trim((string)($_POST['observacao'] ?? ''));
$produtos = $_POST['produtos'] ?? [];
$gerarConta = (int)($_POST['gerar_conta_pagar'] ?? 0) === 1;
$numeroDocumento = mb_substr(trim((string)($_POST['numero_documento'] ?? '')), 0, 20);
$idCategoria = (int)($_POST['id_categoria'] ?? 0);
$primeiroVencimento = trim((string)($_POST['data_vencimento'] ?? ''));
$tipoPagamento = ($_POST['tipo_pagamento'] ?? 'unica') === 'parcelado' ? 'parcelado' : 'unica';
$numeroParcelas = $tipoPagamento === 'parcelado' ? (int)($_POST['numero_parcelas'] ?? 0) : 1;

if ($idFornecedor <= 0 || !is_array($produtos) || !$produtos) {
    set_flash_message('danger', 'Selecione um fornecedor e adicione pelo menos um produto.');
    header('Location: ../pages/compras.php');
    exit;
}
if ($gerarConta) {
    $dataValida = DateTimeImmutable::createFromFormat('!Y-m-d', $primeiroVencimento);
    if (!$dataValida || $dataValida->format('Y-m-d') !== $primeiroVencimento) {
        set_flash_message('danger', 'Informe uma data de vencimento válida.');
        header('Location: ../pages/compras.php');
        exit;
    }
    if ($numeroParcelas < 1 || $numeroParcelas > 36) {
        set_flash_message('danger', 'A quantidade de parcelas deve estar entre 1 e 36.');
        header('Location: ../pages/compras.php');
        exit;
    }
} else {
    $numeroParcelas = 1;
    $primeiroVencimento = null;
    $idCategoria = 0;
}

// Calcula novamente o total a partir dos itens para não confiar no campo oculto da tela.
$itensValidados = [];
$valorTotal = 0.0;
foreach ($produtos as $produto) {
    $idProduto = (int)($produto['id'] ?? 0);
    $quantidade = (int)($produto['quantidade'] ?? 0);
    $preco = (float)($produto['preco'] ?? 0);
    if ($idProduto <= 0 || $quantidade <= 0 || $preco <= 0) {
        set_flash_message('danger', 'Há um produto com quantidade ou custo inválido.');
        header('Location: ../pages/compras.php');
        exit;
    }
    $itensValidados[] = compact('idProduto', 'quantidade', 'preco');
    $valorTotal += $quantidade * $preco;
}
$valorTotal = round($valorTotal, 2);

$conn->begin_transaction();
try {
    // Confirma que o fornecedor pertence à empresa atual.
    $stmtFornecedor = $conn->prepare("SELECT id FROM pessoas_fornecedores WHERE id = ? AND id_usuario = ? AND tipo = 'fornecedor'");
    $stmtFornecedor->bind_param('ii', $idFornecedor, $idUsuario);
    $stmtFornecedor->execute();
    if (!$stmtFornecedor->get_result()->fetch_assoc()) {
        throw new RuntimeException('Fornecedor inválido para esta empresa.');
    }
    $stmtFornecedor->close();

    if ($gerarConta) {
        if ($idCategoria > 0) {
            $stmtCategoria = $conn->prepare("SELECT id FROM categorias WHERE id = ? AND id_usuario = ? AND tipo = 'despesa'");
            $stmtCategoria->bind_param('ii', $idCategoria, $idUsuario);
            $stmtCategoria->execute();
            if (!$stmtCategoria->get_result()->fetch_assoc()) {
                throw new RuntimeException('Categoria de despesa inválida.');
            }
            $stmtCategoria->close();
        } else {
            $nomeCategoria = 'Compras de estoque';
            $stmtCategoria = $conn->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND tipo = 'despesa' AND nome = ? LIMIT 1");
            $stmtCategoria->bind_param('is', $idUsuario, $nomeCategoria);
            $stmtCategoria->execute();
            $categoriaEncontrada = $stmtCategoria->get_result()->fetch_assoc();
            $stmtCategoria->close();
            if ($categoriaEncontrada) {
                $idCategoria = (int)$categoriaEncontrada['id'];
            } else {
                $stmtNovaCategoria = $conn->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, 'despesa')");
                $stmtNovaCategoria->bind_param('is', $idUsuario, $nomeCategoria);
                $stmtNovaCategoria->execute();
                $idCategoria = (int)$conn->insert_id;
                $stmtNovaCategoria->close();
            }
        }
    }

    $stmtCompra = $conn->prepare("INSERT INTO compras (id_usuario, id_fornecedor, valor_total, observacao, numero_documento, id_categoria, data_primeiro_vencimento, numero_parcelas, gerar_conta_pagar, condicao_pagamento) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?)");
    $stmtCompra->bind_param('iidssisiis', $idUsuario, $idFornecedor, $valorTotal, $observacao, $numeroDocumento, $idCategoria, $primeiroVencimento, $numeroParcelas, $gerarConta, $tipoPagamento);
    $stmtCompra->execute();
    $idCompra = (int)$conn->insert_id;
    $stmtCompra->close();

    foreach ($itensValidados as $item) {
        $stmtItem = $conn->prepare('INSERT INTO compra_items (id_compra, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)');
        $stmtItem->bind_param('iiid', $idCompra, $item['idProduto'], $item['quantidade'], $item['preco']);
        $stmtItem->execute();
        $stmtItem->close();

        $stmtEstoque = $conn->prepare('UPDATE produtos SET quantidade_estoque = quantidade_estoque + ? WHERE id = ? AND id_usuario = ?');
        $stmtEstoque->bind_param('iii', $item['quantidade'], $item['idProduto'], $idUsuario);
        $stmtEstoque->execute();
        if ($stmtEstoque->affected_rows !== 1) {
            throw new RuntimeException('Um dos produtos não pertence à empresa atual.');
        }
        $stmtEstoque->close();

        $observacaoMovimento = 'Compra #' . $idCompra;
        $stmtMovimento = $conn->prepare("INSERT INTO movimento_estoque (id_produto, id_usuario, id_pessoa_fornecedor, tipo, quantidade, observacao) VALUES (?, ?, ?, 'entrada', ?, ?)");
        $stmtMovimento->bind_param('iiiis', $item['idProduto'], $idUsuario, $idFornecedor, $item['quantidade'], $observacaoMovimento);
        $stmtMovimento->execute();
        $stmtMovimento->close();
    }

    if ($gerarConta) {
        $totalCentavos = (int)round($valorTotal * 100);
        $baseCentavos = intdiv($totalCentavos, $numeroParcelas);
        $restanteCentavos = $totalCentavos % $numeroParcelas;
        $formaPagamento = $numeroParcelas > 1 ? 'Parcelado' : 'Parcela única';

        for ($parcela = 1; $parcela <= $numeroParcelas; $parcela++) {
            $valorParcela = ($baseCentavos + ($parcela <= $restanteCentavos ? 1 : 0)) / 100;
            $vencimento = vencimentoParcela($primeiroVencimento, $parcela - 1);
            $numeroConta = numeroParcela($numeroDocumento, $idCompra, $parcela, $numeroParcelas);
            $descricaoConta = 'Compra #' . $idCompra . ($numeroParcelas > 1 ? " - Parcela {$parcela}/{$numeroParcelas}" : '');

            $stmtPagar = $conn->prepare("INSERT INTO contas_pagar (usuario_id, id_pessoa_fornecedor, id_categoria, numero, descricao, valor, data_vencimento, status, forma_pagamento, id_compra, parcela_numero, parcela_total) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, ?, ?)");
            $stmtPagar->bind_param('iiissdssiii', $idUsuario, $idFornecedor, $idCategoria, $numeroConta, $descricaoConta, $valorParcela, $vencimento, $formaPagamento, $idCompra, $parcela, $numeroParcelas);
            $stmtPagar->execute();
            $stmtPagar->close();
        }
    }

    $conn->commit();
    $mensagem = "Compra #{$idCompra} registrada com sucesso";
    $mensagem .= $gerarConta ? " e {$numeroParcelas} " . ($numeroParcelas === 1 ? 'conta foi gerada' : 'contas foram geradas') . ' em Contas a Pagar.' : ' sem gerar conta a pagar.';
    set_flash_message('success', $mensagem);
} catch (Throwable $erro) {
    $conn->rollback();
    error_log('Erro ao registrar compra: ' . $erro->getMessage());
    set_flash_message('danger', 'Não foi possível registrar a compra: ' . $erro->getMessage());
}

header('Location: ../pages/compras.php');
exit;

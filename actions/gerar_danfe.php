<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// 1. Verifica Sessão
if (!isset($_SESSION['usuario_logado'])) {
    die('Acesso negado.');
}

// 2. Valida Entrada (Correção do FILTER_SANITIZE_STRING depreciado)
// Usa htmlspecialchars como fallback simples se FILTER_SANITIZE_FULL_SPECIAL_CHARS não estiver disponível ou apenas pega a string crua filtrada manualmente
$chave = $_GET['chave'] ?? '';
$chave = preg_replace('/[^0-9]/', '', $chave); // Apenas números na chave

if (!$chave) {
    die('Chave de acesso não fornecida.');
}

// 3. Busca XML no Banco
$conn = getTenantConnection();
$configImpressao = ['impressora_largura' => '80', 'impressao_automatica' => '0'];
$resultadoImpressao = $conn->query("SELECT chave, valor FROM configuracoes_tenant WHERE chave IN ('impressora_largura', 'impressao_automatica')");
if ($resultadoImpressao) {
    while ($config = $resultadoImpressao->fetch_assoc()) {
        $configImpressao[$config['chave']] = $config['valor'];
    }
}
$larguraDanfe = in_array((int)$configImpressao['impressora_largura'], [58, 80], true)
    ? (int)$configImpressao['impressora_largura']
    : 80;
$impressaoAutomatica = $configImpressao['impressao_automatica'] === '1';
$stmt = $conn->prepare("SELECT xml_path, protocolo, data_emissao FROM notas_fiscais WHERE chave_acesso = ?");
$stmt->bind_param("s", $chave);
$stmt->execute();
$result = $stmt->get_result();
$nota = $result->fetch_assoc();

if (!$nota || !file_exists(__DIR__ . '/../' . $nota['xml_path'])) {
    die('Arquivo XML não encontrado no servidor.');
}

// 4. Carrega XML
$xmlContent = file_get_contents(__DIR__ . '/../' . $nota['xml_path']);
// Remove prefixos de namespace para facilitar a leitura (comum em XMLs complexos)
$xmlContent = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xmlContent);
$xml = simplexml_load_string($xmlContent);

if ($xml === false) {
    die('Erro ao ler o arquivo XML.');
}

// 5. Extrai Dados para o HTML (Simulação de DANFE)
// Tenta navegar na estrutura padrão da NFe
// Estrutura esperada: nfeProc -> NFe -> infNFe
// Ou direto: NFe -> infNFe (se for apenas o XML da nota sem protocolo)

$infNFe = null;
if (isset($xml->NFe->infNFe)) {
    $infNFe = $xml->NFe->infNFe;
} elseif (isset($xml->infNFe)) {
    $infNFe = $xml->infNFe;
} elseif (isset($xml->protNFe)) {
     // Caso seja apenas um retorno de protocolo ou estrutura diferente
     // Tenta buscar dentro de uma tag filha se existir
}

if (!$infNFe) {
    // Fallback para o Mock que criamos (ele tem estrutura simplificada as vezes)
    // O Mock criado em testar_emissao_mock.php tem: <nfeProc> ... <protNFe> ... </nfeProc>
    // Mas NÃO tem a tag <NFe> com os detalhes do produto! 
    // O script mock anterior salvou APENAS o protocolo de retorno fictício, não a nota completa com produtos.
    
    // CORREÇÃO CRÍTICA: O script de mock anterior gerou um XML incompleto (só protocolo).
    // Para visualizar, precisamos dos dados da VENDA, já que o XML mockado não os tem.
    
    // Vamos buscar os dados da venda no banco para preencher o DANFE visualmente
    $stmtVenda = $conn->prepare("SELECT * FROM notas_fiscais nf JOIN vendas v ON nf.id_venda = v.id JOIN venda_items vi ON v.id = vi.id_venda JOIN produtos p ON vi.id_produto = p.id WHERE nf.chave_acesso = ?");
    $stmtVenda->bind_param("s", $chave);
    $stmtVenda->execute();
    $resultVenda = $stmtVenda->get_result();
    
    $itensVenda = [];
    $vendaInfo = null;
    while($row = $resultVenda->fetch_assoc()){
        $vendaInfo = $row;
        $itensVenda[] = $row;
    }
    
    // Dados da Empresa (Emitente)
    $empresa = $conn->query("SELECT * FROM empresa_config LIMIT 1")->fetch_assoc();
    $empresa_kv = $conn->query("SELECT chave, valor FROM configuracoes_tenant");
    while ($r = $empresa_kv->fetch_assoc()) $empresa[$r['chave']] = $r['valor'];
} else {
    // Se o XML for completo (Produção real ou Mock bem feito), usa ele
    $emit = $infNFe->emit;
    $dest = $infNFe->dest;
    $total = $infNFe->total->ICMSTot;
    $det = $infNFe->det;
    $ide = $infNFe->ide;
}

// Variáveis de Exibição (Normalizadas)
$emit_nome = isset($emit) ? (string)$emit->xNome : ($empresa['razao_social'] ?? 'EMPRESA DE TESTE');
$emit_cnpj = isset($emit) ? (string)$emit->CNPJ : ($empresa['cnpj'] ?? '00000000000000');
$emit_end  = isset($emit) ? "{$emit->enderEmit->xLgr}, {$emit->enderEmit->nro}" : ($empresa['logradouro'] ?? '') . ", " . ($empresa['numero'] ?? '');
$emit_mun  = isset($emit) ? "{$emit->enderEmit->xMun} - {$emit->enderEmit->UF}" : ($empresa['municipio'] ?? '') . " - " . ($empresa['uf'] ?? '');

$protocolo = $nota['protocolo'] ?? 'PENDENTE';
$dataEmissao = date('d/m/Y H:i:s', strtotime($nota['data_emissao']));
$valorTotal = isset($total) ? (float)$total->vNF : ($vendaInfo['valor_total'] ?? 0.00);

// Formatação Chave
$chaveFormatada = implode(' ', str_split($chave, 4));

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFE NFC-e - Visualização</title>
    <style>
        @page { size: <?= $larguraDanfe ?>mm auto; margin: 2mm; }
        body { 
            font-family: "Courier New", Courier, monospace; 
            font-size: 12px; 
            margin: 0; 
            padding: 20px; 
            background: #e9e9e9; 
        }

        .danfe-container { 
            width: <?= $larguraDanfe - 4 ?>mm;
            box-sizing: border-box;
            margin: 0 auto; 
            background: #fff; 
            padding: 15px; 
            border-radius: 5px;
            border: 1px solid #ccc; 
            box-shadow: 0 0 8px rgba(0,0,0,0.15); 
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }

        .line { 
            border-bottom: 1px dashed #000; 
            margin: 6px 0; 
        }

        .header h2 { 
            margin: 0; 
            font-size: 14px; 
            font-weight: bold;
        }

        .header p { 
            margin: 1px 0; 
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 8px; 
        }

        th { 
            border-bottom: 1px solid #000; 
            font-size: 11px; 
            padding-bottom: 3px;
        }

        td { 
            font-size: 11px;
            padding: 2px 0; 
            vertical-align: top;
        }

        .total-row { 
            font-size: 13px; 
            font-weight: bold; 
            margin-top: 10px; 
            border-top: 1px dashed #000; 
            padding-top: 6px; 
        }

        .chave { 
            font-size: 11px; 
            margin-top: 8px; 
            text-align: center; 
            word-break: break-all;
            letter-spacing: 1px;
        }

        .btn-print { 
            display: block; 
            width: 100%; 
            /* padding: 9px;  */
            background: #28a745; 
            color: #fff; 
            text-align: center; 
            border: none; 
            cursor: pointer; 
            margin-bottom: 12px; 
            font-family: Arial, sans-serif; 
            font-size: 13px;
            font-weight: bold; 
            text-decoration: none; 
            border-radius: 5px; 
        }

        /* ASSINATURA */
        .assinatura-area {
            margin-top: 20px;
            text-align: center;
        }

        .assinatura-linha {
            border-top: 1px solid #000;
            width: 100%;
            margin: 40px auto 8px auto;
        }

        .assinatura-label {
            font-size: 11px;
            text-align: center;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .danfe-container { 
                border: none; 
                box-shadow: none; 
                width: 100%; 
                margin: 0; 
            }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="danfe-container">

    <a href="#" onclick="window.print(); return false;" class="btn-print">🖨️ IMPRIMIR NOTA</a>

    <!-- Cabeçalho -->
    <div class="header text-center">
        <h2><?= strtoupper($emit_nome) ?></h2>
        <p>CNPJ: <?= $emit_cnpj ?></p>
        <p><?= $emit_end ?></p>
        <p><?= $emit_mun ?></p>
        <div class="line"></div>
        <p class="bold">DANFE NFC-e</p>
        <p>Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</p>
        <p style="font-size:10px;">Não permite aproveitamento de crédito de ICMS</p>
    </div>

    <!-- Itens -->
    <table>
        <thead>
            <tr>
                <th>DESCRIÇÃO</th>
                <th>QTD</th>
                <th>UN</th>
                <th>VL UNIT</th>
                <th class="text-right">VL TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($det)): ?>
                <?php foreach ($det as $itemXml): $produtoXml = $itemXml->prod; ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$produtoXml->xProd) ?></td>
                        <td><?= number_format((float)$produtoXml->qCom, 3, ',', '.') ?></td>
                        <td><?= htmlspecialchars((string)$produtoXml->uCom) ?></td>
                        <td><?= number_format((float)$produtoXml->vUnCom, 2, ',', '.') ?></td>
                        <td class="text-right"><?= number_format((float)$produtoXml->vProd, 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($itensVenda as $itemVenda): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$itemVenda['nome']) ?></td>
                        <td><?= number_format((float)$itemVenda['quantidade'], 3, ',', '.') ?></td>
                        <td>UN</td>
                        <td><?= number_format((float)$itemVenda['preco_unitario'], 2, ',', '.') ?></td>
                        <td class="text-right"><?= number_format((float)$itemVenda['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Totais -->
    <div class="total-row">
        <div style="display:flex; justify-content:space-between;">
            <span>QTD. ITENS</span>
            <span><?= isset($det) ? count($det) : count($itensVenda) ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:15px; margin-top:6px;">
            <span>VALOR TOTAL R$</span>
            <span><?= number_format($valorTotal, 2, ',', '.') ?></span>
        </div>
    </div>

    <div class="line"></div>

    <!-- Infos -->
    <div class="text-center" style="font-size: 10px;">
        <p>Emissão: <?= $dataEmissao ?></p>
        <p>Via Consumidor</p>

        <div class="line"></div>

        <p><strong>Consulte pela Chave de Acesso em:</strong></p>
        <p>http://www.nfce.fazenda.sp.gov.br/</p>

        <div class="chave bold">
            <?= $chaveFormatada ?>
        </div>

        <div class="line"></div>

        <p><strong>Protocolo:</strong></p>
        <p><?= $protocolo ?> - <?= $dataEmissao ?></p>

        <br>

        <img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=<?= $chave ?>&choe=UTF-8" 
             alt="QR Code" style="width:100px;">
    </div>

    <!-- ASSINATURA -->
    <div class="assinatura-area">
        <div class="assinatura-linha"></div>
        <p class="assinatura-label">Assinatura do Responsável</p>
    </div>

</div>

<?php if ($impressaoAutomatica): ?>
<script>
window.addEventListener('load', () => window.print(), { once: true });
</script>
<?php endif; ?>

</body>
</html>

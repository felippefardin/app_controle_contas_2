<?php
require_once '../vendor/autoload.php';
require_once '../database.php'; 
require_once '../includes/session_init.php'; // Garante sessÃ£o correta
require_once '../includes/config/nfe_config.php';

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Complements;
use NFePHP\Common\Certificate;

header('Content-Type: application/json');

// 1ï¸âƒ£ FunÃ§Ã£o de LOG
function log_nfce($msg) {
    $dirLog = __DIR__ . '/../logs/';
    if (!is_dir($dirLog)) mkdir($dirLog, 0755, true);
    $arquivo = $dirLog . 'nfce_debug.log';
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($arquivo, $linha, FILE_APPEND);
}

// 2ï¸âƒ£ ValidaÃ§Ã£o de SessÃ£o e Entrada
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    log_nfce("âŒ SessÃ£o invÃ¡lida ou expirada.");
    echo json_encode(['success' => false, 'message' => 'SessÃ£o expirada. FaÃ§a login novamente.']);
    exit;
}

$conn = getTenantConnection(); // âœ… Usa a conexÃ£o correta do Tenant
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexÃ£o com o banco de dados.']);
    exit;
}

$id_venda = filter_input(INPUT_POST, 'id_venda', FILTER_VALIDATE_INT);
if (!$id_venda) {
    echo json_encode(['success' => false, 'message' => 'ID da venda invÃ¡lido.']);
    exit;
}

try {
    log_nfce("ðŸ”¹ Iniciando emissÃ£o para venda #{$id_venda}");
    $conn->begin_transaction();

    // 3ï¸âƒ£ Carrega ConfiguraÃ§Ãµes (HÃ­brido: Tabela Antiga + Nova)
    
    // A. Busca dados cadastrais (CNPJ, EndereÃ§o) da tabela 'empresa_config'
    // Assumimos que existe apenas 1 configuraÃ§Ã£o por Tenant (LIMIT 1)
    $stmtConfig = $conn->query("SELECT * FROM empresa_config LIMIT 1");
    $empresaConfig = $stmtConfig->fetch_assoc();

    if (!$empresaConfig) {
        throw new Exception("Dados da empresa (CNPJ, EndereÃ§o) nÃ£o encontrados em 'empresa_config'.");
    }

    // B. Busca dados fiscais (CSC, Ambiente) da tabela 'configuracoes_tenant' e SOBREPÃ•E
    $stmtTenant = $conn->query("SELECT chave, valor FROM configuracoes_tenant");
    if ($stmtTenant) {
        while ($row = $stmtTenant->fetch_assoc()) {
            // Mapeia os campos da nova tabela para o array antigo
            $empresaConfig[$row['chave']] = $row['valor'];
        }
    }

    // Ajustes manuais de compatibilidade
    $empresaConfig['ambiente'] = (int)($empresaConfig['ambiente'] ?? 2);
    $tpAmb = $empresaConfig['ambiente'];
    if ($tpAmb === 1 && !filter_var($_ENV['FISCAL_ALLOW_PRODUCTION'] ?? false, FILTER_VALIDATE_BOOL)) throw new Exception('ProduÃ§Ã£o bloqueada atÃ© a homologaÃ§Ã£o ser validada.');

    // 4ï¸âƒ£ Atualiza NumeraÃ§Ã£o
    $novo_numero_nf = (int)($empresaConfig['ultimo_numero_nfce'] ?? 0) + 1;
    $conn->query("UPDATE empresa_config SET ultimo_numero_nfce = $novo_numero_nf WHERE id = " . (int)$empresaConfig['id']);
    log_nfce("ðŸ”¢ NÃºmero NF reservado: {$novo_numero_nf}");

    // 5ï¸âƒ£ ConfiguraÃ§Ã£o do NFePHP
    $configJson = getConfigJson($empresaConfig); // Usa funÃ§Ã£o do seu include
    
    // Verifica certificado
    $certPath = __DIR__ . '/../' . ($empresaConfig['certificado_a1_path'] ?? '');
    if (!file_exists($certPath)) {
        throw new Exception("Certificado Digital nÃ£o encontrado no caminho: $certPath");
    }
    $certificadoContent = file_get_contents($certPath);
    
    $tools = new Tools($configJson, Certificate::readPfx($certificadoContent, $empresaConfig['certificado_senha']));
    $tools->model('65'); // NFC-e

    // 6ï¸âƒ£ Busca Venda e Itens
    $stmtVenda = $conn->prepare("SELECT * FROM vendas WHERE id = ? AND id_usuario = ?");
    $usuarioId=(int)(get_data_owner_id() ?? 0);
    $stmtVenda->bind_param("ii", $id_venda, $usuarioId);
    $stmtVenda->execute();
    $venda = $stmtVenda->get_result()->fetch_assoc();
    
    if (!$venda) throw new Exception("Venda #$id_venda nÃ£o encontrada.");

    $stmtItens = $conn->prepare("
        SELECT iv.*, p.nome, p.ncm, p.cfop 
        FROM venda_items iv
        JOIN produtos p ON iv.id_produto = p.id
        WHERE iv.id_venda = ?
    ");
    $stmtItens->bind_param("i", $id_venda);
    $stmtItens->execute();
    $itens = $stmtItens->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($itens)) throw new Exception("Venda sem itens.");

    // 7ï¸âƒ£ Montagem do XML (Resumido)
    $nfe = new Make();
    $inf = new \stdClass();
    $inf->versao = '4.00';
    $inf->Id = null; 
    $inf->pk_nItem = null;
    $nfe->taginfNFe($inf);

    // Dados IdentificaÃ§Ã£o
    $ide = new \stdClass();
    $codigosUf=['RO'=>'11','AC'=>'12','AM'=>'13','RR'=>'14','PA'=>'15','AP'=>'16','TO'=>'17','MA'=>'21','PI'=>'22','CE'=>'23','RN'=>'24','PB'=>'25','PE'=>'26','AL'=>'27','SE'=>'28','BA'=>'29','MG'=>'31','ES'=>'32','RJ'=>'33','SP'=>'35','PR'=>'41','SC'=>'42','RS'=>'43','MS'=>'50','MT'=>'51','GO'=>'52','DF'=>'53'];
    $ide->cUF = $codigosUf[strtoupper($empresaConfig['uf'] ?? '')] ?? throw new Exception('UF fiscal invÃ¡lida.');
    $ide->cNF = rand(10000000, 99999999);
    $ide->natOp = 'VENDA';
    $ide->mod = 65;
    $ide->serie = (int)($empresaConfig['serie_nfce'] ?? 1);
    $ide->nNF = $novo_numero_nf;
    $ide->dhEmi = date('Y-m-d\TH:i:sP');
    $ide->tpNF = 1;
    $ide->idDest = 1;
    $ide->cMunFG = $empresaConfig['cod_municipio'] ?? '3205309';
    $ide->tpImp = 4;
    $ide->tpEmis = 1;
    $ide->cDV = 0;
    $ide->tpAmb = $tpAmb;
    $ide->finNFe = 1;
    $ide->indFinal = 1;
    $ide->indPres = 1;
    $ide->procEmi = 0;
    $ide->verProc = '1.0';
    $nfe->tagide($ide);

    // Emitente
    $emit = new \stdClass();
    $emit->CNPJ = preg_replace('/[^0-9]/', '', $empresaConfig['cnpj']);
    $emit->xNome = $empresaConfig['razao_social'];
    $emit->IE = preg_replace('/[^0-9]/', '', $empresaConfig['ie']);
    $emit->CRT = $empresaConfig['regime_tributario'] ?? 1;
    $nfe->tagemit($emit);

    // EndereÃ§o Emitente
    $enderEmit = new \stdClass();
    $enderEmit->xLgr = $empresaConfig['logradouro'];
    $enderEmit->nro = $empresaConfig['numero'];
    $enderEmit->xBairro = $empresaConfig['bairro'];
    $enderEmit->cMun = $empresaConfig['cod_municipio'];
    $enderEmit->xMun = $empresaConfig['municipio'];
    $enderEmit->UF = $empresaConfig['uf'];
    $enderEmit->CEP = preg_replace('/[^0-9]/', '', $empresaConfig['cep']);
    $enderEmit->cPais = '1058';
    $enderEmit->xPais = 'BRASIL';
    $nfe->tagenderEmit($enderEmit);

    // Itens
    foreach ($itens as $k => $item) {
        $std = new \stdClass();
        $std->item = $k + 1;
        $std->cProd = $item['id_produto'];
        $std->xProd = $item['nome'];
        $std->NCM = $item['ncm'];
        $std->CFOP = $item['cfop'];
        $std->uCom = 'UN';
        $std->qCom = $item['quantidade'];
        $std->vUnCom = number_format($item['preco_unitario'], 2, '.', '');
        $std->vProd = number_format($item['subtotal'], 2, '.', '');
        $std->indTot = 1;
        $nfe->tagprod($std);

        // Imposto Simples (Exemplo 102)
        $stdIcms = new \stdClass();
        $stdIcms->item = $k + 1;
        $stdIcms->orig = 0;
        $stdIcms->CSOSN = '102';
        $nfe->tagICMSSN($stdIcms);
    }

    // Totais e Pagamento
    $nfe->tagICMSTot(new \stdClass());
    
    $stdPag = new \stdClass();
    $stdPag->vTroco = null; 
    $nfe->tagpag($stdPag);

    $stdDetPag = new \stdClass();
    $mapPag=['dinheiro'=>'01','cheque'=>'02','cartao_credito'=>'03','cartao_debito'=>'04','boleto'=>'15','pix'=>'17','transferencia'=>'18','receber'=>'15'];
    $stdDetPag->tPag = $mapPag[$venda['forma_pagamento']] ?? '99';
    $stdDetPag->vPag = number_format($venda['valor_total'], 2, '.', '');
    $nfe->tagdetPag($stdDetPag);

    // 8ï¸âƒ£ Assinatura e Envio
    $xml = $nfe->getXML();
    $xmlAssinado = $tools->signNFe($xml);
    log_nfce("ðŸ” XML assinado com sucesso.");

    $resp = $tools->sefazEnviaLote([$xmlAssinado], (string)random_int(1,999999999), 1);
    
    $st = new Tools($configJson, Certificate::readPfx($certificadoContent, $empresaConfig['certificado_senha']));
    $stdCl = json_decode(json_encode(simplexml_load_string($resp)));
    
    if (isset($stdCl->protNFe->infProt->cStat) && $stdCl->protNFe->infProt->cStat == 100) {
        // Sucesso
        $chave = (string)$stdCl->protNFe->infProt->chNFe;
        $prot = (string)$stdCl->protNFe->infProt->nProt;
        
        // Salva XML
        $xmlAutorizado = Complements::toAuthorize($xmlAssinado, $resp);
        $dirNotas=__DIR__.'/../storage/notas_fiscais';
        if(!is_dir($dirNotas) && !mkdir($dirNotas,0770,true)) throw new Exception('Falha ao criar diretÃ³rio fiscal.');
        $xmlPath = "storage/notas_fiscais/{$chave}.xml";
        if(file_put_contents(__DIR__.'/../'.$xmlPath, $xmlAutorizado)===false) throw new Exception('Falha ao guardar XML autorizado.');

        // Grava no banco
        $serie=(int)($empresaConfig['serie_nfce']??1);
        $stmtNota = $conn->prepare("INSERT INTO notas_fiscais (id_venda,numero,serie,modelo,ambiente,status,chave_acesso,protocolo,xml_path,data_emissao) VALUES (?,?,?,65,?,'autorizada',?,?,?,NOW())");
        $stmtNota->bind_param("iiiisss", $id_venda,$novo_numero_nf,$serie,$tpAmb,$chave,$prot,$xmlPath);
        $stmtNota->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Nota emitida!', 'chave' => $chave]);
    } else {
        // Erro na SEFAZ
        $motivo = $stdCl->protNFe->infProt->xMotivo ?? $stdCl->xMotivo ?? 'Erro desconhecido';
        throw new Exception("RejeiÃ§Ã£o SEFAZ: $motivo");
    }

} catch (Exception $e) {
    $conn->rollback();
    log_nfce("âŒ ERRO FATAL: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


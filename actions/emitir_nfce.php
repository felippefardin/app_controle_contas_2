<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/fiscal_a1.php';
require_once __DIR__ . '/../includes/config/nfe_config.php';

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Complements;
use NFePHP\Common\Certificate;

header('Content-Type: application/json');

// 1ï¸âƒ£ Função de LOG
function log_nfce($msg) {
    $dirLog = __DIR__ . '/../logs/';
    if (!is_dir($dirLog)) mkdir($dirLog, 0755, true);
    $arquivo = $dirLog . 'nfce_debug.log';
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($arquivo, $linha, FILE_APPEND);
}

// 2ï¸âƒ£ Validação de Sessão e Entrada
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    log_nfce("âŒ Sessão inválida ou expirada.");
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$conn = getTenantConnection(); // ✅ Usa a conexão correta do Tenant
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
    exit;
}

$id_venda = filter_input(INPUT_POST, 'id_venda', FILTER_VALIDATE_INT);
if (!$id_venda) {
    echo json_encode(['success' => false, 'message' => 'ID da venda inválido.']);
    exit;
}

try {
    log_nfce("🔹 Iniciando emissão para venda #{$id_venda}");
    $homologacaoPiloto = isset($_POST['enviar_nfce_homologacao']);
    $somenteValidacao = isset($_POST['validar_nfce_local']);
    if ($homologacaoPiloto) {
        if (empty($_SESSION['tenant_id']) || !is_tenant_admin()) {
            http_response_code(403);
            echo json_encode(['message' => 'Envio em homologação indisponível para esta conta.']);
            exit;
        }
    }
    if ($somenteValidacao || $homologacaoPiloto) {
        $token = $_SESSION['token_validacao_nfce'] ?? '';
        $enviado = $_POST['token_validacao_nfce'] ?? '';
        if ($token === '' || !is_string($enviado) || !hash_equals($token, $enviado)) {
            http_response_code(403);
            echo json_encode(['message' => 'Sessão do teste expirada. Atualize o relatório.']);
            exit;
        }
    }
    $validacaoLocal = !$homologacaoPiloto && ($somenteValidacao || is_file('/etc/appcontrolepro-nfce-validacao-local'));
    if ($validacaoLocal) {
        $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    } else {
        $conn->begin_transaction();
    }

    // 3ï¸âƒ£ Carrega Configurações (Híbrido: Tabela Antiga + Nova)
    
    // A. Busca dados cadastrais (CNPJ, Endereço) da tabela 'empresa_config'
    // Assumimos que existe apenas 1 configuração por Tenant (LIMIT 1)
    $stmtConfig = $conn->query("SELECT * FROM empresa_config LIMIT 1" . ($homologacaoPiloto ? " FOR UPDATE" : ""));
    $empresaConfig = $stmtConfig->fetch_assoc();

    if (!$empresaConfig && !$validacaoLocal) {
        throw new Exception("Dados da empresa (CNPJ, Endereço) não encontrados em 'empresa_config'.");
    }

    // B. Busca dados fiscais (CSC, Ambiente) da tabela 'configuracoes_tenant' e SOBREPÕE
    $stmtTenant = $conn->query("SELECT chave, valor FROM configuracoes_tenant");
    if ($stmtTenant) {
        while ($row = $stmtTenant->fetch_assoc()) {
            // Mapeia os campos da nova tabela para o array antigo
            $empresaConfig[$row['chave']] = $row['valor'];
        }
    }

    // Ajustes manuais de compatibilidade
    $empresaConfig = (array) $empresaConfig;
    $empresaConfig['ambiente'] = (int)($empresaConfig['ambiente'] ?? 2);
    if ($validacaoLocal) {
        if (empty($empresaConfig['cnpj']) || empty($empresaConfig['razao_social']) || empty($empresaConfig['uf'])) {
            $empresaConfig = array_replace($empresaConfig ?? [], [
                'cnpj' => '99999999000191', 'razao_social' => 'Emitente Ficticio',
                'fantasia' => 'Teste local', 'ie' => '999999999',
                'logradouro' => 'Rua de Teste', 'numero' => '1', 'bairro' => 'Centro',
                'municipio' => 'Vitoria', 'uf' => 'ES', 'cep' => '29010000',
                'cod_municipio' => '3205309', 'regime_tributario' => 1,
            ]);
        }
        $empresaConfig['ambiente'] = 2;
        $empresaConfig['csc'] = str_repeat('A', 36);
        $empresaConfig['csc_id'] = '000001';
    }
    $tpAmb = $empresaConfig['ambiente'];
    if ($homologacaoPiloto && ($tpAmb !== 2 || empty($empresaConfig['csc']) || empty($empresaConfig['csc_id']))) {
        throw new RuntimeException('Selecione Homologação e configure o CSC e seu ID antes de enviar.');
    }
    if ($tpAmb === 1 && !filter_var($_ENV['FISCAL_ALLOW_PRODUCTION'] ?? false, FILTER_VALIDATE_BOOL)) throw new Exception('Produção bloqueada até a homologação ser validada.');

    // 4ï¸âƒ£ Atualiza Numeração
    $novo_numero_nf = (int)($empresaConfig['ultimo_numero_nfce'] ?? 0) + 1;
    if (!$validacaoLocal && !$homologacaoPiloto) {
        $conn->query("UPDATE empresa_config SET ultimo_numero_nfce = $novo_numero_nf WHERE id = " . (int)$empresaConfig['id']);
    }
    log_nfce("🔢 Número NF reservado: {$novo_numero_nf}");

    // 5ï¸âƒ£ Configuração do NFePHP
    if ($homologacaoPiloto) {
        $jaEnviada = $conn->prepare("SELECT id FROM notas_fiscais WHERE id_venda = ? AND ambiente = 2 AND numero IS NOT NULL LIMIT 1");
        $jaEnviada->bind_param('i', $id_venda);
        $jaEnviada->execute();
        if ($jaEnviada->get_result()->fetch_row()) {
            throw new RuntimeException('Esta venda já possui uma tentativa em homologação. Consulte o resultado antes de reenviar.');
        }
        $seriePiloto = (int) ($empresaConfig['serie_nfce'] ?? 1);
        $numero = $conn->prepare('SELECT COALESCE(MAX(numero), 0) + 1 FROM notas_fiscais WHERE ambiente = 2 AND serie = ?');
        $numero->bind_param('i', $seriePiloto);
        $numero->execute();
        $novo_numero_nf = (int) $numero->get_result()->fetch_row()[0];
    }
    $configJson = getConfigJson($empresaConfig); // Usa função do seu include
    
    // Verifica certificado
    if ($validacaoLocal) {
        $senhaTeste = bin2hex(random_bytes(16));
        $chaveTeste = openssl_pkey_new(['private_key_bits' => 2048]);
        $pedidoTeste = $chaveTeste
            ? openssl_csr_new(['commonName' => 'Validacao local do PDV'], $chaveTeste)
            : false;
        $certificadoTeste = $pedidoTeste
            ? openssl_csr_sign($pedidoTeste, null, $chaveTeste, 1)
            : false;
        if (!$certificadoTeste || !openssl_pkcs12_export(
            $certificadoTeste, $pfxTeste, $chaveTeste, $senhaTeste
        )) {
            throw new RuntimeException('Falha ao gerar o certificado do teste local.');
        }
        $certificate = Certificate::readPfx($pfxTeste, $senhaTeste);
    } else {
        $certificate = fiscal_a1_load($empresaConfig);
    }
    $tools = new Tools($configJson, $certificate);
    $tools->model('65'); // NFC-e

    // 6ï¸âƒ£ Busca Venda e Itens
    $stmtVenda = $conn->prepare("SELECT * FROM vendas WHERE id = ? AND id_usuario = ?");
    $usuarioId=(int)(get_data_owner_id() ?? 0);
    $stmtVenda->bind_param("ii", $id_venda, $usuarioId);
    $stmtVenda->execute();
    $venda = $stmtVenda->get_result()->fetch_assoc();
    
    if (!$venda) throw new Exception("Venda #$id_venda não encontrada.");

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

    // Dados Identificação
    $ide = new \stdClass();
    $codigosUf=['RO'=>'11','AC'=>'12','AM'=>'13','RR'=>'14','PA'=>'15','AP'=>'16','TO'=>'17','MA'=>'21','PI'=>'22','CE'=>'23','RN'=>'24','PB'=>'25','PE'=>'26','AL'=>'27','SE'=>'28','BA'=>'29','MG'=>'31','ES'=>'32','RJ'=>'33','SP'=>'35','PR'=>'41','SC'=>'42','RS'=>'43','MS'=>'50','MT'=>'51','GO'=>'52','DF'=>'53'];
    $ide->cUF = $codigosUf[strtoupper($empresaConfig['uf'] ?? '')] ?? throw new Exception('UF fiscal inválida.');
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

    // Endereço Emitente
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
        $std->uTrib = $std->uCom;
        $std->qTrib = $std->qCom;
        $std->vUnTrib = $std->vUnCom;
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
    if ($validacaoLocal || $homologacaoPiloto) {
        $nfe->tagtransp((object) ['modFrete' => 9]);
    }
    $nfe->tagpag($stdPag);

    $stdDetPag = new \stdClass();
    $mapPag=['dinheiro'=>'01','cheque'=>'02','cartao_credito'=>'03','cartao_debito'=>'04','boleto'=>'15','pix'=>'17','transferencia'=>'18','receber'=>'15'];
    $stdDetPag->tPag = $mapPag[$venda['forma_pagamento']] ?? '99';
    $stdDetPag->vPag = number_format($venda['valor_total'], 2, '.', '');
    $nfe->tagdetPag($stdDetPag);

    // 8ï¸âƒ£ Assinatura e Envio
    $xml = $nfe->getXML();
    $xmlAssinado = $tools->signNFe($xml);
    if ($validacaoLocal) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'validacao_local' => true,
            'xml_valido' => true,
            'message' => 'Teste local concluído: XML do PDV assinado. Nenhuma nota foi emitida.',
        ]);
        exit;
    }

    log_nfce("ðŸ” XML assinado com sucesso.");

    if ($homologacaoPiloto) {
        if (!preg_match('/Id="NFe([0-9]{44})"/', $xmlAssinado, $matches)) {
            throw new RuntimeException('Não foi possível identificar a chave do XML assinado.');
        }
        $chavePiloto = $matches[1];
        $reserva = $conn->prepare("INSERT INTO notas_fiscais (id_venda, numero, serie, modelo, ambiente, status, chave_acesso) VALUES (?, ?, ?, 65, 2, 'em_envio', ?)");
        $reserva->bind_param('iiis', $id_venda, $novo_numero_nf, $seriePiloto, $chavePiloto);
        $reserva->execute();
        $idTentativa = $conn->insert_id;
        $conn->commit();
    }
    $resp = $tools->sefazEnviaLote([$xmlAssinado], (string)random_int(1,999999999), 1);
    
    $st = new Tools($configJson, $certificate);
    $stdCl = json_decode(json_encode(simplexml_load_string($resp)));
    
    if (isset($stdCl->protNFe->infProt->cStat) && $stdCl->protNFe->infProt->cStat == 100) {
        // Sucesso
        $chave = (string)$stdCl->protNFe->infProt->chNFe;
        $prot = (string)$stdCl->protNFe->infProt->nProt;
        
        // Salva XML
        $xmlAutorizado = Complements::toAuthorize($xmlAssinado, $resp);
        $dirNotas=__DIR__.'/../storage/notas_fiscais';
        if(!is_dir($dirNotas) && !mkdir($dirNotas,0770,true)) throw new Exception('Falha ao criar diretório fiscal.');
        $xmlPath = "storage/notas_fiscais/{$chave}.xml";
        if(file_put_contents(__DIR__.'/../'.$xmlPath, $xmlAutorizado)===false) throw new Exception('Falha ao guardar XML autorizado.');

        // Grava no banco
        $serie=(int)($empresaConfig['serie_nfce']??1);
        if ($homologacaoPiloto) {
            if ($chave !== $chavePiloto) throw new RuntimeException('A chave retornada difere da chave enviada.');
            $stmtNota = $conn->prepare("UPDATE notas_fiscais SET status='autorizada', protocolo=?, xml_path=?, data_emissao=NOW() WHERE id=?");
            $stmtNota->bind_param('ssi', $prot, $xmlPath, $idTentativa);
        } else {
            $stmtNota = $conn->prepare("INSERT INTO notas_fiscais (id_venda,numero,serie,modelo,ambiente,status,chave_acesso,protocolo,xml_path,data_emissao) VALUES (?,?,?,65,?,'autorizada',?,?,?,NOW())");
            $stmtNota->bind_param("iiiisss", $id_venda,$novo_numero_nf,$serie,$tpAmb,$chave,$prot,$xmlPath);
        }
        $stmtNota->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => $homologacaoPiloto ? 'NFC-e autorizada em homologação. Chave: ' . $chave : 'Nota emitida!', 'chave' => $chave]);
    } else {
        // Erro na SEFAZ
        $motivo = $stdCl->protNFe->infProt->xMotivo ?? $stdCl->xMotivo ?? 'Erro desconhecido';
        if ($homologacaoPiloto && isset($idTentativa)) {
            $retorno = $conn->prepare("UPDATE notas_fiscais SET mensagem_erro=? WHERE id=?");
            $retorno->bind_param('si', $motivo, $idTentativa);
            $retorno->execute();
        }
        throw new Exception("Rejeição SEFAZ: $motivo");
    }

} catch (Throwable $e) {
    $conn->rollback();
    log_nfce("âŒ ERRO FATAL: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


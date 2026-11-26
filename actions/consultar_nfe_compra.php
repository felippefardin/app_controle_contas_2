<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/fiscal_a1.php';
require_once __DIR__ . '/../includes/config/nfe_config.php';

use NFePHP\NFe\Tools;

header('Content-Type: application/json; charset=utf-8');

function respostaNfeCompra(bool $success, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['usuario_logado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaNfeCompra(false, 'Sessão inválida ou método não permitido.', [], 403);
}

try {
    require_csrf();
    $chave = preg_replace('/\D/', '', (string) ($_POST['chave_acesso'] ?? ''));
    if (!preg_match('/^\d{44}$/D', $chave)) throw new RuntimeException('Informe a chave de acesso completa da NF-e, com 44 dígitos.');
    $conn = getTenantConnection();
    if (!$conn) throw new RuntimeException('Não foi possível acessar os dados da empresa.');
    $empresa = $conn->query('SELECT * FROM empresa_config LIMIT 1')->fetch_assoc();
    if (!$empresa) throw new RuntimeException('Cadastre os dados fiscais da empresa antes de consultar a SEFAZ.');
    $kv = $conn->query('SELECT chave, valor FROM configuracoes_tenant');
    while ($kv && $row = $kv->fetch_assoc()) $empresa[$row['chave']] = $row['valor'];
    $empresa['ambiente'] = (int) ($empresa['ambiente'] ?? 1);
    if (preg_replace('/\D/', '', (string) ($empresa['cnpj'] ?? '')) === '') throw new RuntimeException('Cadastre o CNPJ da empresa nas configurações fiscais.');

    $tools = new Tools(getConfigJson($empresa), fiscal_a1_load($empresa));
    $tools->model('55');
    $retorno = simplexml_load_string($tools->sefazDistDFe(0, 0, $chave));
    if (!$retorno) throw new RuntimeException('A SEFAZ devolveu uma resposta inválida.');
    $cStat = (string) ($retorno->cStat ?? '');
    $xMotivo = trim((string) ($retorno->xMotivo ?? ''));
    if ($cStat !== '138') throw new RuntimeException($xMotivo !== '' ? "SEFAZ: {$xMotivo}" : 'A NF-e não foi localizada para este CNPJ.');

    $xmlNfe = null;
    foreach ($retorno->loteDistDFeInt->docZip ?? [] as $docZip) {
        $conteudo = base64_decode((string) $docZip, true);
        $descompactado = $conteudo === false ? false : gzdecode($conteudo);
        if ($descompactado !== false && (str_contains((string) ($docZip['schema'] ?? ''), 'procNFe') || str_contains($descompactado, '<NFe'))) {
            $xmlNfe = $descompactado;
            break;
        }
    }
    if (!$xmlNfe) throw new RuntimeException('A SEFAZ localizou a nota, mas ainda não liberou o XML completo. Faça a manifestação do destinatário no portal fiscal e tente novamente.');
    $xml = simplexml_load_string($xmlNfe);
    if (!$xml) throw new RuntimeException('Não foi possível interpretar o XML autorizado.');
    $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
    $inf = $xml->xpath('//nfe:infNFe')[0] ?? null;
    if (!$inf) throw new RuntimeException('O XML recebido não contém uma NF-e válida.');
    $destCnpj = preg_replace('/\D/', '', (string) ($inf->dest->CNPJ ?? ''));
    $empresaCnpj = preg_replace('/\D/', '', (string) ($empresa['cnpj'] ?? ''));
    if ($destCnpj !== '' && !hash_equals($empresaCnpj, $destCnpj)) throw new RuntimeException('A NF-e informada não foi emitida para o CNPJ desta empresa.');

    $idUsuario = get_data_owner_id();
    $emitCnpj = preg_replace('/\D/', '', (string) ($inf->emit->CNPJ ?? ''));
    $fornecedorId = 0;
    if ($emitCnpj !== '') {
        $stmt = $conn->prepare("SELECT id FROM pessoas_fornecedores WHERE id_usuario=? AND tipo='fornecedor' AND REPLACE(REPLACE(REPLACE(cpf_cnpj,'.',''),'/',''),'-','')=? LIMIT 1");
        $stmt->bind_param('is', $idUsuario, $emitCnpj);
        $stmt->execute();
        $fornecedorId = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
    }
    $itens = [];
    foreach ($inf->det as $det) {
        $codigo = trim((string) ($det->prod->cEAN ?? ''));
        if ($codigo === '' || strtoupper($codigo) === 'SEM GTIN') $codigo = trim((string) ($det->prod->cProd ?? ''));
        $produtoId = 0;
        if ($codigo !== '') {
            $stmt = $conn->prepare('SELECT id FROM produtos WHERE id_usuario=? AND codigo=? AND deleted_at IS NULL LIMIT 1');
            $stmt->bind_param('is', $idUsuario, $codigo);
            $stmt->execute();
            $produtoId = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        }
        $quantidade = (float) ($det->prod->qCom ?? 0);
        if ($quantidade <= 0) continue;
        $itens[] = ['id_produto'=>$produtoId,'codigo'=>mb_substr($codigo,0,100),'nome'=>mb_substr(trim((string)$det->prod->xProd),0,255),'ncm'=>preg_replace('/\D/','',(string)($det->prod->NCM??'')),'cfop'=>preg_replace('/\D/','',(string)($det->prod->CFOP??'')),'quantidade'=>$quantidade,'preco'=>round((float)($det->prod->vUnCom??0),4)];
    }
    if (!$itens) throw new RuntimeException('A NF-e não possui itens de mercadoria importáveis.');
    respostaNfeCompra(true, 'NF-e consultada. Confira os dados antes de finalizar a compra.', [
        'nota'=>['chave'=>$chave,'numero'=>(string)($inf->ide->nNF??''),'serie'=>(string)($inf->ide->serie??''),'data_emissao'=>(string)($inf->ide->dhEmi??$inf->ide->dEmi??''),'valor_total'=>round((float)($inf->total->ICMSTot->vNF??0),2)],
        'fornecedor'=>['id'=>$fornecedorId,'nome'=>trim((string)($inf->emit->xNome??'')),'cnpj'=>$emitCnpj],
        'itens'=>$itens,
    ]);
} catch (Throwable $e) {
    error_log('Consulta NF-e de compra: '.$e->getMessage());
    respostaNfeCompra(false, $e->getMessage(), [], 422);
}

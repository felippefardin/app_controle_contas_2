<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Utils para Flash Message

if (!isset($_SESSION['usuario_logado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

$conn = getTenantConnection();

function cnpjValido(string $cnpj): bool {
    $cnpj=preg_replace('/\D/','',$cnpj); if(strlen($cnpj)!==14||preg_match('/^(\d)\1{13}$/',$cnpj))return false;
    foreach([12,13] as $t){$s=0;$p=$t-7;for($i=0;$i<$t;$i++){$s+=(int)$cnpj[$i]*$p--;if($p<2)$p=9;}$d=11-($s%11);$d=$d>=10?0:$d;if((int)$cnpj[$t]!==$d)return false;}return true;
}

try {
    $cnpj=preg_replace('/\D/','',$_POST['cnpj']??'');
    $cep=preg_replace('/\D/','',$_POST['cep']??'');
    $uf=strtoupper(trim($_POST['uf']??''));
    $codMun=preg_replace('/\D/','',$_POST['cod_municipio']??'');
    $ambiente=(int)($_POST['ambiente']??2);
    $regime=(int)($_POST['regime_tributario']??1);
    $serie=max(1,min(999,(int)($_POST['serie_nfce']??1)));
    if(!cnpjValido($cnpj)) throw new Exception('CNPJ inválido.');
    if(!preg_match('/^[A-Z]{2}$/',$uf)||!preg_match('/^\d{7}$/',$codMun)||strlen($cep)!==8) throw new Exception('UF, CEP ou código IBGE inválido.');
    if(!in_array($ambiente,[1,2],true)||!in_array($regime,[1,2,3],true)) throw new Exception('Ambiente ou regime tributário inválido.');
    if($ambiente===1&&!filter_var($_ENV['FISCAL_ALLOW_PRODUCTION']??false,FILTER_VALIDATE_BOOL)) throw new Exception('Produção bloqueada pelo servidor. Conclua a homologação antes da liberação.');
    $conn->begin_transaction();

    // 1. Salva dados cadastrais
    $check = $conn->query("SELECT id FROM empresa_config LIMIT 1");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO empresa_config (razao_social) VALUES (NULL)");
    }

    $stmt = $conn->prepare("UPDATE empresa_config SET 
        razao_social=?, fantasia=?, cnpj=?, ie=?, 
        logradouro=?, numero=?, bairro=?, municipio=?, 
        cod_municipio=?, uf=?, cep=? 
        LIMIT 1");
    
    $stmt->bind_param("sssssssssss", 
        $_POST['razao_social'], $_POST['fantasia'], $cnpj, $_POST['ie'],
        $_POST['logradouro'], $_POST['numero'], $_POST['bairro'], $_POST['municipio'],
        $codMun, $uf, $cep
    );
    $stmt->execute();

    // 2. Salva dados fiscais (KV Store)
    $camposFiscais = ['regime_tributario', 'ambiente', 'csc_id', 'csc', 'serie_nfce'];
    $stmtKv = $conn->prepare("INSERT INTO configuracoes_tenant (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

    foreach ($camposFiscais as $chave) {
        if (isset($_POST[$chave]) && !($chave==='csc' && trim($_POST[$chave])==='')) {
            $valor = $chave==='ambiente'?(string)$ambiente:($chave==='regime_tributario'?(string)$regime:($chave==='serie_nfce'?(string)$serie:trim($_POST[$chave])));
            $stmtKv->bind_param("ss", $chave, $valor);
            $stmtKv->execute();
        }
    }

    $conn->commit();
    
    // Mensagem de Sucesso
    set_flash_message('success', 'Configurações fiscais salvas com sucesso!');
    header('Location: ../pages/configuracao_fiscal.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    // Mensagem de Erro
    set_flash_message('danger', 'Erro ao salvar: ' . $e->getMessage());
    header('Location: ../pages/configuracao_fiscal.php');
    exit;
}
?>

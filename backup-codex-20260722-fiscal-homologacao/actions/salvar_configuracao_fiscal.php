<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Utils para Flash Message

if (!isset($_SESSION['usuario_logado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

$conn = getTenantConnection();

try {
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
        $_POST['razao_social'], $_POST['fantasia'], $_POST['cnpj'], $_POST['ie'],
        $_POST['logradouro'], $_POST['numero'], $_POST['bairro'], $_POST['municipio'],
        $_POST['cod_municipio'], $_POST['uf'], $_POST['cep']
    );
    $stmt->execute();

    // 2. Salva dados fiscais (KV Store)
    $camposFiscais = ['regime_tributario', 'ambiente', 'csc_id', 'csc'];
    $stmtKv = $conn->prepare("INSERT INTO configuracoes_tenant (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

    foreach ($camposFiscais as $chave) {
        if (isset($_POST[$chave])) {
            $valor = $_POST[$chave];
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
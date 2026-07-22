<?php
require_once '../includes/session_init.php';
require_once '../database.php'; // Carrega a função getTenantConnection()

header('Content-Type: application/json');

// 1. VERIFICA LOGIN (Correção para booleano)
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado.']);
    exit;
}

// 2. VERIFICA PERMISSÃO (Correção: variável está na raiz da sessão, não dentro de usuario_logado)
$perfil = $_SESSION['nivel_acesso'] ?? 'padrao';

// Permite 'admin', 'master' ou 'proprietario' (caso use esse termo específico)
if ($perfil !== 'admin' && $perfil !== 'master' && $perfil !== 'proprietario') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Nível: ' . $perfil]);
    exit;
}

// 3. PEGA A CONEXÃO CORRETA DO TENANT
$conn = getTenantConnection(); 
if ($conn === null) {
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com o banco de dados do cliente.']);
    exit;
}

// Força o mysqli a lançar Exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$nova_meta = $_POST['meta'] ?? 0;
// Converte formato (ex: 10.000,00) para formato float (10000.00)
$valor_meta = (float)str_replace(',', '.', str_replace('.', '', $nova_meta)); 
$ano_mes_atual = date('Y_n');
$chave_meta = "meta_vendas_" . $ano_mes_atual;

try {
    // 4. Insere ou Atualiza a meta
    $sql = "
        INSERT INTO configuracoes_tenant (chave, valor) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ";
    
    $stmt = $conn->prepare($sql);
    
    $valor_meta_str = (string)$valor_meta;
    $stmt->bind_param("ss", $chave_meta, $valor_meta_str);
    
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Meta atualizada com sucesso!']);
   
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
exit;
?>
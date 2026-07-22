<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

// 1. Verifica Login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. Segurança: Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'Método inválido.');
    header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    exit;
}

// 3. Segurança: CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de segurança inválido.');
    header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    exit;
}

$conn = getTenantConnection();
$usuarioId = $_SESSION['usuario_id'];
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id && $id > 0) {
    // Prepara o comando
    $stmt = $conn->prepare("DELETE FROM pessoas_fornecedores WHERE id = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id, $usuarioId);

    try {
        // Tenta executar a exclusão
        if ($stmt->execute()) {
            // Verifica se realmente deletou algo (pode não deletar se o ID não for do usuário)
            if ($stmt->affected_rows > 0) {
                set_flash_message('success', 'Cadastro excluído com sucesso!');
            } else {
                set_flash_message('danger', 'Registro não encontrado ou você não tem permissão.');
            }
        } else {
            // Erro genérico do execute (raro cair aqui se tiver try-catch, mas mantemos por segurança)
            set_flash_message('danger', "Erro ao processar a exclusão.");
        }
    } catch (mysqli_sql_exception $e) {
        // CAPTURA O ERRO DE CHAVE ESTRANGEIRA (FK)
        if ($e->getCode() == 1451) {
            set_flash_message('warning', '<b>Não é possível excluir:</b> Este cliente/fornecedor possui Vendas, Compras ou Contas vinculadas. <br>Exclua os registros financeiros antes de remover o cadastro.');
        } else {
            // Outros erros de banco
            set_flash_message('danger', "Erro técnico ao excluir: " . $e->getMessage());
        }
    }
    
    $stmt->close();
} else {
    set_flash_message('danger', 'ID inválido.');
}

header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
exit;
?>
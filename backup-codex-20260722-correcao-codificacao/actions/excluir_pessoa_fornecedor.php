<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';

// 1. Verifica Login
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. SeguranÃ§a: Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'MÃ©todo invÃ¡lido.');
    header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    exit;
}

// 3. SeguranÃ§a: CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de seguranÃ§a invÃ¡lido.');
    header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
    exit;
}

$conn = getTenantConnection();
$usuarioId = get_data_owner_id();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id && $id > 0) {
    // Prepara o comando
    $stmt = $conn->prepare("DELETE FROM pessoas_fornecedores WHERE id = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id, $usuarioId);

    try {
        // Tenta executar a exclusÃ£o
        if ($stmt->execute()) {
            // Verifica se realmente deletou algo (pode nÃ£o deletar se o ID nÃ£o for do usuÃ¡rio)
            if ($stmt->affected_rows > 0) {
                set_flash_message('success', 'Cadastro excluÃ­do com sucesso!');
            } else {
                set_flash_message('danger', 'Registro nÃ£o encontrado ou vocÃª nÃ£o tem permissÃ£o.');
            }
        } else {
            // Erro genÃ©rico do execute (raro cair aqui se tiver try-catch, mas mantemos por seguranÃ§a)
            set_flash_message('danger', "Erro ao processar a exclusÃ£o.");
        }
    } catch (mysqli_sql_exception $e) {
        // CAPTURA O ERRO DE CHAVE ESTRANGEIRA (FK)
        if ($e->getCode() == 1451) {
            set_flash_message('warning', '<b>NÃ£o Ã© possÃ­vel excluir:</b> Este cliente/fornecedor possui Vendas, Compras ou Contas vinculadas. <br>Exclua os registros financeiros antes de remover o cadastro.');
        } else {
            // Outros erros de banco
            set_flash_message('danger', "Erro tÃ©cnico ao excluir: " . $e->getMessage());
        }
    }
    
    $stmt->close();
} else {
    set_flash_message('danger', 'ID invÃ¡lido.');
}

header('Location: ../pages/cadastrar_pessoa_fornecedor.php');
exit;
?>

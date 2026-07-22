<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// 1. VERIFICA O LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php?error=not_logged_in');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "RequisiÃ§Ã£o invÃ¡lida.";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

$conn = getTenantConnection();
if ($conn === null) {
    $_SESSION['error_message'] = "Falha na conexÃ£o com o banco de dados.";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

// ObtÃ©m ID do usuÃ¡rio da sessÃ£o
$id_usuario = get_data_owner_id();

$contaId = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
$repetirVezes = filter_input(INPUT_POST, 'repetir_vezes', FILTER_VALIDATE_INT);
$repetirIntervalo = filter_input(INPUT_POST, 'repetir_intervalo', FILTER_VALIDATE_INT);

if (!$contaId || !$repetirVezes || !$repetirIntervalo || $repetirVezes <= 0 || $repetirIntervalo <= 0) {
    $_SESSION['error_message'] = "Dados invÃ¡lidos para repetir a conta.";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

// 2. BUSCA A CONTA ORIGINAL
$stmt = $conn->prepare("SELECT * FROM contas_pagar WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $contaId, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$contaOriginal = $result->fetch_assoc();
$stmt->close();

if (!$contaOriginal) {
    $_SESSION['error_message'] = "Conta original nÃ£o encontrada.";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

// 3. PREPARA E EXECUTA AS INSERÃ‡Ã•ES (Com o campo 'numero' de volta)
$sql = "INSERT INTO contas_pagar (
            id_pessoa_fornecedor, 
            numero, 
            descricao, 
            valor, 
            data_vencimento, 
            id_categoria, 
            usuario_id, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";

$stmt_insert = $conn->prepare($sql);

if ($stmt_insert === false) {
    die("Erro ao preparar a query de inserÃ§Ã£o: " . $conn->error);
}

$dataVencimento = new DateTime($contaOriginal['data_vencimento']);
$conn->begin_transaction();
$sucesso = true;

for ($i = 1; $i <= $repetirVezes; $i++) {
    // Adiciona o intervalo de dias
    $dataVencimento->add(new DateInterval("P{$repetirIntervalo}D"));
    $novaDataVencimento = $dataVencimento->format('Y-m-d');
    
    // Adiciona indicaÃ§Ã£o de repetiÃ§Ã£o na descriÃ§Ã£o (opcional, mas Ãºtil)
    $novaDescricao = $contaOriginal['descricao'] . " (RepetiÃ§Ã£o $i/$repetirVezes)";

    // âœ… Bind parameters atualizado para incluir 'numero'
    // Tipos: i=int, s=string, s=string, d=double, s=string, i=int, i=int
    $stmt_insert->bind_param(
        "issdsii",
        $contaOriginal['id_pessoa_fornecedor'],
        $contaOriginal['numero'], // Copia o nÃºmero da conta original
        $novaDescricao,
        $contaOriginal['valor'],
        $novaDataVencimento,
        $contaOriginal['id_categoria'],
        $id_usuario
    );

    if (!$stmt_insert->execute()) {
        $sucesso = false;
        $_SESSION['error_message'] = "Erro ao repetir a conta: " . $stmt_insert->error;
        break; 
    }
}

if ($sucesso) {
    $conn->commit();
    $_SESSION['success_message'] = "Conta repetida com sucesso {$repetirVezes} vez(es).";
} else {
    $conn->rollback();
}

$stmt_insert->close();
header('Location: ../pages/contas_pagar.php');
exit;
?>

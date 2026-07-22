<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

if (!isset($_SESSION['usuario_logado'])) { header('Location: ../pages/login.php'); exit; }

$conn = getTenantConnection();
$id_usuario = $_SESSION['usuario_id'];
$contaId = (int)$_POST['conta_id'];
$repetirVezes = (int)$_POST['repetir_vezes'];
$repetirIntervalo = (int)$_POST['repetir_intervalo'];

if (!$contaId || $repetirVezes <= 0) {
    set_flash_message('error', "Dados inválidos.");
    header('Location: ../pages/contas_receber.php');
    exit;
}

// Busca original
$stmt = $conn->prepare("SELECT * FROM contas_receber WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $contaId, $id_usuario);
$stmt->execute();
$contaOriginal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($contaOriginal) {
    $sql = "INSERT INTO contas_receber (id_pessoa_fornecedor, numero, descricao, valor, data_vencimento, id_categoria, usuario_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
    $stmt_insert = $conn->prepare($sql);
    
    $dataVencimento = new DateTime($contaOriginal['data_vencimento']);
    $conn->begin_transaction();

    for ($i = 1; $i <= $repetirVezes; $i++) {
        $dataVencimento->add(new DateInterval("P{$repetirIntervalo}D"));
        $novaData = $dataVencimento->format('Y-m-d');
        $novaDesc = $contaOriginal['descricao'] . " (Rep $i/$repetirVezes)";
        
        $stmt_insert->bind_param("issdsii", 
            $contaOriginal['id_pessoa_fornecedor'], 
            $contaOriginal['numero'], 
            $novaDesc, 
            $contaOriginal['valor'], 
            $novaData, 
            $contaOriginal['id_categoria'], 
            $id_usuario
        );
        $stmt_insert->execute();
    }
    $conn->commit();
    // SUCESSO
    set_flash_message('success', "Conta repetida com sucesso!");
} else {
    set_flash_message('error', "Conta original não encontrada.");
}

header('Location: ../pages/contas_receber.php');
exit;
?>
<?php
// actions/enviar_suporte_login.php

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once '../database.php'; 

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método inválido.');

    $conn = getMasterConnection();
    if (!$conn || $conn->connect_error) throw new Exception('Erro de conexão.');

    // Dados
    $anonimo = isset($_POST['anonimo']) ? 1 : 0;
    $descricao = trim($_POST['descricao'] ?? '');
    $canal = strtolower(trim($_POST['canal_preferido'] ?? 'email'));

    if (empty($descricao)) throw new Exception('Descrição obrigatória.');

    if ($anonimo) {
        $nome = 'Anônimo';
        $whatsapp = null;
        $email = null;
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($nome)) throw new Exception('Nome obrigatório.');
    }

    // GERAÇÃO DE PROTOCOLO (SUP-ANO-SEQUENCIAL)
    if (!in_array($canal, ['email', 'whatsapp'], true)) throw new Exception('Canal de resposta inválido.');
    if ($canal === 'email' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Informe um e-mail válido.');
    if ($canal === 'whatsapp' && strlen(preg_replace('/\D+/', '', (string)$whatsapp)) < 10) throw new Exception('Informe um WhatsApp válido com DDD.');

    $ano = date('Y');
    // Pega o último ID para gerar o próximo sequencial (simulação simples, ideal seria sequence)
    $res = $conn->query("SELECT id FROM suporte_login ORDER BY id DESC LIMIT 1");
    $lastId = ($res && $row = $res->fetch_assoc()) ? $row['id'] : 0;
    $nextId = $lastId + 1;
    $protocolo = sprintf("SUP-%s-%06d", $ano, $nextId);

    // Inserção
    $sql = "INSERT INTO suporte_login (protocolo, nome, whatsapp, email, descricao, anonimo, canal_preferido, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) throw new Exception('Erro na preparação da query: ' . $conn->error);

    $stmt->bind_param("sssssis", $protocolo, $nome, $whatsapp, $email, $descricao, $anonimo, $canal);

    if ($stmt->execute()) {
        // Cria o primeiro registro no histórico
        $suporteId = $stmt->insert_id;
        $msgInicial = "Chamado aberto pelo usuário.";
        $conn->query("INSERT INTO suporte_historico (suporte_id, mensagem, tipo) VALUES ($suporteId, '$msgInicial', 'sistema')");

        echo json_encode([
            'status' => 'success', 
            'msg' => "Suporte enviado! Seu protocolo é: $protocolo. Anote para consultar depois.",
            'protocolo' => $protocolo
        ]);
    } else {
        throw new Exception('Erro ao salvar: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
exit;
?>

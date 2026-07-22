<?php
require '../vendor/autoload.php';
include '../database.php';

use Dompdf\Dompdf;

$chatId = $_GET['chat_id'] ?? 0;
$conn = getMasterConnection(); // ✅ MySQLi

// Buscar dados do chat
$stmt = $conn->prepare("SELECT cs.*, u.nome as usuario_nome FROM chat_sessions cs JOIN usuarios u ON cs.usuario_id = u.id WHERE cs.id = ?");
$stmt->bind_param("i", $chatId);
$stmt->execute();
$chat = $stmt->get_result()->fetch_assoc();

if (!$chat) die("Chat não encontrado.");

// Buscar mensagens
$stmtMsg = $conn->prepare("SELECT * FROM chat_messages WHERE chat_session_id = ? ORDER BY id ASC");
$stmtMsg->bind_param("i", $chatId);
$stmtMsg->execute();
$resultMsg = $stmtMsg->get_result();

// Construir HTML
$html = '<h1>Protocolo de Atendimento: ' . $chat['protocolo'] . '</h1>';
$html .= '<p><strong>Cliente:</strong> ' . htmlspecialchars($chat['usuario_nome']) . '</p>';
$html .= '<p><strong>Data:</strong> ' . date('d/m/Y', strtotime($chat['data_criacao'])) . '</p>';
$html .= '<hr>';

while ($msg = $resultMsg->fetch_assoc()) {
    $autor = ($msg['sender_type'] == 'admin') ? 'Suporte' : 'Cliente';
    $color = ($msg['sender_type'] == 'admin') ? '#0000FF' : '#000';
    
    $html .= '<div style="margin-bottom: 10px; font-family: sans-serif;">';
    $html .= '<strong style="color:'.$color.'">' . $autor . ' (' . date('H:i:s', strtotime($msg['data_envio'])) . '):</strong><br>';
    $html .= nl2br(htmlspecialchars($msg['mensagem']));
    $html .= '</div>';
}

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("protocolo_" . $chat['protocolo'] . ".pdf", ["Attachment" => true]);
?>
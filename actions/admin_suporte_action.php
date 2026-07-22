<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../vendor/autoload.php';

// ---------------------------------------------------------
// CORREÇÃO DE PERMISSÃO
// ---------------------------------------------------------
// Verifica se existe a sessão de Super Admin OU se o nível de acesso é permitido
$is_super_admin = isset($_SESSION['super_admin']);

if (!$is_super_admin) {
    // Se não for nenhum dos dois, nega
    echo json_encode(['status' => 'error', 'msg' => 'Acesso negado. Nível insuficiente.']);
    exit;
}
// ---------------------------------------------------------

header('Content-Type: application/json');
$conn = getMasterConnection();
$acao = $_POST['acao'] ?? '';

function registrarHistoricoExterno(mysqli $conn, int $id, string $mensagem, string $tipo): void {
    $stmt = $conn->prepare('INSERT INTO suporte_historico (suporte_id, mensagem, tipo) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $id, $mensagem, $tipo);
    $stmt->execute();
    $stmt->close();
}

try {
    if ($acao === 'responder_email') {
        $id = (int)($_POST['id'] ?? 0);
        $mensagem = trim($_POST['mensagem'] ?? '');
        if ($id <= 0 || $mensagem === '') throw new Exception('Informe a resposta.');
        $stmt = $conn->prepare('SELECT nome,email,protocolo FROM suporte_login WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $id); $stmt->execute();
        $chamado = $stmt->get_result()->fetch_assoc();
        if (!$chamado || !filter_var($chamado['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Este chamado não possui e-mail válido.');

        registrarHistoricoExterno($conn, $id, $mensagem, 'resposta_email');
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
            $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 587);
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? $mail->Username, $_ENV['MAIL_FROM_NAME'] ?? 'Suporte App Controle de Contas');
            $mail->addAddress($chamado['email'], $chamado['nome']);
            $mail->isHTML(true);
            $mail->Subject = 'Resposta do suporte - ' . $chamado['protocolo'];
            $nomeSeguro = htmlspecialchars($chamado['nome'], ENT_QUOTES, 'UTF-8');
            $msgSeguro = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));
            $mail->Body = "<p>Olá, {$nomeSeguro}.</p><p><strong>Protocolo:</strong> {$chamado['protocolo']}</p><p>{$msgSeguro}</p><p>Equipe de Suporte</p>";
            $mail->AltBody = "Protocolo: {$chamado['protocolo']}\n\n{$mensagem}";
            $mail->send();
            $conn->query("UPDATE suporte_login SET status='respondido', respondido_em=NOW(), ultimo_envio_status='enviado', ultimo_envio_erro=NULL WHERE id=" . $id);
            echo json_encode(['status'=>'success','msg'=>'Resposta enviada por e-mail e registrada no histórico.']);
        } catch (Throwable $mailError) {
            $erro = mb_substr($mailError->getMessage(), 0, 500);
            $stmtErro = $conn->prepare("UPDATE suporte_login SET status='em_andamento', ultimo_envio_status='erro', ultimo_envio_erro=? WHERE id=?");
            $stmtErro->bind_param('si', $erro, $id); $stmtErro->execute();
            registrarHistoricoExterno($conn, $id, 'Falha no envio do e-mail: ' . $erro, 'sistema');
            throw new Exception('A resposta foi salva, mas o e-mail não pôde ser enviado: ' . $erro);
        }
    } elseif ($acao === 'preparar_whatsapp') {
        $id = (int)($_POST['id'] ?? 0);
        $mensagem = trim($_POST['mensagem'] ?? '');
        if ($id <= 0 || $mensagem === '') throw new Exception('Informe a resposta.');
        $stmt = $conn->prepare('SELECT nome,whatsapp,protocolo FROM suporte_login WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $id); $stmt->execute(); $chamado = $stmt->get_result()->fetch_assoc();
        $numero = preg_replace('/\D+/', '', (string)($chamado['whatsapp'] ?? ''));
        if (strlen($numero) < 10) throw new Exception('Este chamado não possui WhatsApp válido.');
        if (!str_starts_with($numero, '55')) $numero = '55' . $numero;
        registrarHistoricoExterno($conn, $id, $mensagem, 'resposta_whatsapp');
        $conn->query("UPDATE suporte_login SET status='em_andamento', respondido_em=NOW(), ultimo_envio_status='whatsapp_aberto', ultimo_envio_erro=NULL WHERE id=" . $id);
        $texto = "Olá, {$chamado['nome']}. Resposta do suporte. Protocolo: {$chamado['protocolo']}\n\n{$mensagem}";
        echo json_encode(['status'=>'success','url'=>'https://wa.me/' . $numero . '?text=' . rawurlencode($texto)]);
    } elseif ($acao === 'atualizar_status') {
        $id = intval($_POST['id']);
        $novoStatus = $_POST['status']; // 'em_andamento', 'resolvido', 'fechado'
        
        $sql = "UPDATE suporte_login SET status = ?";
        if ($novoStatus === 'resolvido' || $novoStatus === 'fechado') {
            $sql .= ", resolvido_em = NOW()";
        }
        $sql .= " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $novoStatus, $id);
        
        if($stmt->execute()) {
            // Log no histórico
            $msg = "Status alterado para: " . ucfirst(str_replace('_', ' ', $novoStatus));
            $conn->query("INSERT INTO suporte_historico (suporte_id, mensagem, tipo) VALUES ($id, '$msg', 'sistema')");
            echo json_encode(['status' => 'success']);
        } else {
            throw new Exception($stmt->error);
        }

    } elseif ($acao === 'adicionar_historico') {
        $id = intval($_POST['id']);
        $mensagem = trim($_POST['mensagem']);
        
        if (!empty($mensagem)) {
            $stmt = $conn->prepare("INSERT INTO suporte_historico (suporte_id, mensagem, tipo) VALUES (?, ?, 'suporte')");
            $stmt->bind_param("is", $id, $mensagem);
            $stmt->execute();
        }
        echo json_encode(['status' => 'success']);

    } elseif ($acao === 'buscar_detalhes') {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("SELECT * FROM suporte_login WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $chamado = $stmt->get_result()->fetch_assoc();
        
        if(!$chamado) throw new Exception("Chamado não encontrado.");

        $resHist = $conn->query("SELECT * FROM suporte_historico WHERE suporte_id = $id ORDER BY criado_em ASC");
        $historico = $resHist->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['status' => 'success', 'dados' => $chamado, 'historico' => $historico]);

    } elseif ($acao === 'excluir') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM suporte_login WHERE id = $id");
        // O CASCADE no banco cuida do histórico, mas por segurança:
        $conn->query("DELETE FROM suporte_historico WHERE suporte_id = $id");
        echo json_encode(['status' => 'success']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
?>

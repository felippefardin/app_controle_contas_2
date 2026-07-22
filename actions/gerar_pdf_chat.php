<?php
require_once '../includes/session_init.php';
require_once '../vendor/autoload.php';
require_once '../database.php';

if (empty($_SESSION['super_admin'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

$id = (int)($_GET['id'] ?? $_GET['chat_id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'interno';
if ($id <= 0 || !in_array($tipo, ['interno', 'externo', 'online'], true)) exit('Atendimento inválido.');

$conn = getMasterConnection();
$a = null;
$stmt = null;

if ($tipo === 'interno') {
    $stmtAtendimento = $conn->prepare("SELECT c.*,t.nome_empresa FROM chamados_suporte c LEFT JOIN tenants t ON t.tenant_id COLLATE utf8mb4_unicode_ci=c.tenant_id COLLATE utf8mb4_unicode_ci WHERE c.id=? AND c.status='concluido'");
    $stmtAtendimento->bind_param('i', $id);
    $stmtAtendimento->execute();
    $a = $stmtAtendimento->get_result()->fetch_assoc();
    $stmt = $conn->prepare('SELECT autor_nome AS autor,mensagem,criado_em FROM chamados_historico WHERE chamado_id=? ORDER BY criado_em,id');
    $stmt->bind_param('i', $id);
} elseif ($tipo === 'externo') {
    $stmtAtendimento = $conn->prepare("SELECT *,nome AS usuario_nome,resolvido_em AS encerrado_em FROM suporte_login WHERE id=? AND status IN ('resolvido','fechado')");
    $stmtAtendimento->bind_param('i', $id);
    $stmtAtendimento->execute();
    $a = $stmtAtendimento->get_result()->fetch_assoc();
    $stmt = $conn->prepare("SELECT CASE WHEN tipo='sistema' THEN 'Sistema' WHEN tipo LIKE 'resposta_%' OR tipo='suporte' THEN 'Suporte' ELSE 'Solicitante' END AS autor,mensagem,criado_em FROM suporte_historico WHERE suporte_id=? ORDER BY criado_em,id");
    $stmt->bind_param('i', $id);
} else {
    $stmtAtendimento = $conn->prepare("SELECT cs.*,u.nome AS usuario_nome,u.email AS usuario_email,a.nome AS admin_nome FROM chat_sessions cs LEFT JOIN usuarios u ON u.id=cs.usuario_id LEFT JOIN usuarios a ON a.id=cs.admin_id WHERE cs.id=? AND cs.status='closed'");
    $stmtAtendimento->bind_param('i', $id);
    $stmtAtendimento->execute();
    $a = $stmtAtendimento->get_result()->fetch_assoc();
    $stmt = $conn->prepare("SELECT CASE sender_type WHEN 'admin' THEN 'Atendente' WHEN 'user' THEN 'Usuário' ELSE 'Sistema' END AS autor,mensagem,data_envio AS criado_em FROM chat_messages WHERE chat_session_id=? ORDER BY data_envio,id");
    $stmt->bind_param('i', $id);
}

if (!$a) exit('Atendimento não encontrado.');
$stmt->execute();
$msgs = $stmt->get_result();
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$protocolo = $a['protocolo'] ?: 'SEM-PROTOCOLO';
$titulos = ['interno' => 'Suporte interno — assinante', 'externo' => 'Suporte externo — não assinante', 'online' => 'Chat de atendimento online'];

$html = '<style>body{font-family:DejaVu Sans,Arial;color:#222;font-size:12px}h1{color:#087ea4}.meta{background:#f2f5f6;padding:14px;line-height:1.6}.msg{margin:12px 0;padding:10px;border-left:3px solid #087ea4;background:#fafafa}.rodape{margin-top:24px;color:#666;font-size:10px}</style>';
$html .= '<h1>Histórico de Atendimento</h1><div class="meta"><b>Protocolo:</b> '.$e($protocolo).'<br><b>Tipo:</b> '.$e($titulos[$tipo]).'<br><b>Solicitante:</b> '.$e($a['usuario_nome'] ?? $a['nome'] ?? 'Não identificado').'<br><b>Contato:</b> '.$e($a['usuario_email'] ?? $a['email'] ?? $a['whatsapp'] ?? '');
if (!empty($a['descricao'])) $html .= '<br><b>Descrição:</b> '.$e($a['descricao']);
if ($tipo === 'online') {
    $html .= '<br><b>Início:</b> '.$e(!empty($a['data_inicio']) ? date('d/m/Y H:i', strtotime($a['data_inicio'])) : '-');
    $html .= '<br><b>Encerramento:</b> '.$e(!empty($a['closed_at']) ? date('d/m/Y H:i', strtotime($a['closed_at'])) : '-');
    if (!empty($a['admin_nome'])) $html .= '<br><b>Atendente:</b> '.$e($a['admin_nome']);
}
$html .= '</div>';

foreach ($msgs as $m) {
    $texto = (string)$m['mensagem'];
    $arquivo = json_decode($texto, true);
    if (is_array($arquivo) && !empty($arquivo['_is_file'])) {
        $texto = '[Anexo] ' . ($arquivo['name'] ?? 'arquivo') . (!empty($arquivo['url']) ? ' — ' . $arquivo['url'] : '');
    }
    $data = !empty($m['criado_em']) ? date('d/m/Y H:i:s', strtotime($m['criado_em'])) : '-';
    $html .= '<div class="msg"><b>'.$e($m['autor']).'</b> — '.$e($data).'<br>'.nl2br($e($texto)).'</div>';
}
$html .= '<div class="rodape">Documento gerado em '.date('d/m/Y H:i:s').' a partir do histórico armazenado no sistema.</div>';

$pdf = new Dompdf\Dompdf();
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4');
$pdf->render();
$pdf->stream('suporte_'.$protocolo.'.pdf', ['Attachment' => true]);

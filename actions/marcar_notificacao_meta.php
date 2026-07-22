<?php
require_once '../includes/session_init.php';require_once '../database.php';
if(empty($_SESSION['usuario_logado'])||$_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(403);exit;}
$id=(int)($_POST['id']??0);$usuario=(int)($_SESSION['usuario_id']??0);$conn=getTenantConnection();
$stmt=$conn->prepare('UPDATE notificacoes_equipe SET lida_em=NOW() WHERE id=? AND usuario_id=? AND lida_em IS NULL');$stmt->bind_param('ii',$id,$usuario);$stmt->execute();
header('Content-Type: application/json');echo json_encode(['success'=>true]);

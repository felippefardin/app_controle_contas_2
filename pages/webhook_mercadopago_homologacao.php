<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/mercadopago_security.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
$dir='/var/lib/appcontrolepro-webhook-teste';
$request=json_decode(file_get_contents('php://input', false, null, 0, 16384),true);
$id=rawurldecode((string)($request['data']['id']??''));
parse_str(str_replace('data.id=', 'resource_id=', $_SERVER['QUERY_STRING']??''), $query);
$signedId=(string)($query['resource_id']??$id);
$ids=json_decode(file_get_contents($dir.'/ids.json'),true);
if (!isset($ids[$id]) || $signedId!==$id || ($request['type']??'')!=='subscription_preapproval') { http_response_code(204); exit; }
$secret=is_readable($dir.'/secret') ? trim(file_get_contents($dir.'/secret')) : '';
if ($secret==='') { http_response_code(503); exit; }
if (!mp_validate_webhook_signature($_SERVER['HTTP_X_SIGNATURE']??'',$_SERVER['HTTP_X_REQUEST_ID']??'',$signedId,$secret)) { http_response_code(401); exit; }
$line=json_encode(['recebido'=>gmdate(DATE_ATOM), 'tipo'=>$request['type'], 'acao'=>$request['action']??'']);
if (file_put_contents($dir.'/events/'.$ids[$id].'.log',$line.PHP_EOL,FILE_APPEND|LOCK_EX)===false) { http_response_code(500); exit; }
http_response_code(200);

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit("Somente CLI.\n");
define('APP_SKIP_SESSION',true);
require_once __DIR__.'/../database.php';
require_once __DIR__.'/../includes/mercadopago_security.php';
require_once __DIR__.'/../includes/indicacao_reward_service.php';
$db=getMasterConnection();
$rows=$db->query("SELECT usuario_id,tenant_id,id_assinatura_mp FROM tenants WHERE status_assinatura='ativo' AND id_assinatura_mp IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
foreach($rows as $t){
 try{
  $meses=mesesPendentesIndicador($db,(int)$t['usuario_id']); if($meses<1) continue;
  $sub=mp_api_request('GET','/preapproval/'.rawurlencode($t['id_assinatura_mp']))['body'];
  $base=(string)($sub['next_payment_date']??'now');
  $nova=(new DateTimeImmutable($base))->modify("+{$meses} months")->format('Y-m-d\TH:i:s.000\Z');
  mp_api_request('PUT','/preapproval/'.rawurlencode($t['id_assinatura_mp']),['auto_recurring'=>['start_date'=>$nova]]);
  $s=$db->prepare("UPDATE indicacao_recompensas SET status='aplicado',mp_preapproval_id=?,aplicado_em=NOW() WHERE indicador_id=? AND status='pendente'");
  $s->bind_param('si',$t['id_assinatura_mp'],$t['usuario_id']);$s->execute();$s->close();
  echo "[OK] {$t['tenant_id']}: {$meses} mês(es)\n";
 }catch(Throwable $e){fwrite(STDERR,"[ERRO] {$t['tenant_id']}: {$e->getMessage()}\n");}
}
$db->close();

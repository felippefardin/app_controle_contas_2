<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
$db = getMasterConnection();
if (!$db) exit(1);
$rows = $db->query("SELECT id FROM trocas_planos WHERE estado<>'concluida' AND estado<>'revisao' ORDER BY id LIMIT 100");
$ids = array_column($rows->fetch_all(MYSQLI_ASSOC),'id');
if (in_array('--verificar', $argv, true)) { echo count($ids), " troca(s) pendente(s).\n"; exit; }
foreach ($ids as $id) {
    try {
        $db->begin_transaction();
        $q=$db->prepare("SELECT * FROM trocas_planos WHERE id=? AND estado NOT IN ('concluida','revisao') FOR UPDATE");
        $q->bind_param('i',$id); $q->execute(); $j=$q->get_result()->fetch_assoc();
        if (!$j) { $db->rollback(); continue; }
        $t=$db->prepare('SELECT id_assinatura_mp,status_assinatura FROM tenants WHERE tenant_id=? FOR UPDATE');
        $t->bind_param('s',$j['tenant_id']); $t->execute(); $conta=$t->get_result()->fetch_assoc();
        if (($conta['id_assinatura_mp'] ?? '') !== $j['assinatura_mp'] || ($conta['status_assinatura'] ?? '') !== 'ativo') {
            $db->query("UPDATE trocas_planos SET estado='revisao' WHERE id=".(int)$id); $db->commit(); continue;
        }
        $path='/preapproval/'.rawurlencode($j['assinatura_mp']);
        $remote=mp_api_request('GET',$path)['body']??[];
        if (($remote['status']??'')!=='authorized' || ($remote['external_reference']??'')!==$j['referencia'] || ($remote['auto_recurring']['currency_id']??'')!=='BRL') throw new RuntimeException('Assinatura incompatível.');
        $next=strtotime($remote['next_payment_date']??'');
        if (!$next) throw new RuntimeException('Renovação indisponível.');
        $atualizarValor=static function (int $valor) use ($path): void {
            $r=mp_api_request('PUT',$path,['auto_recurring'=>['transaction_amount'=>$valor/100,'currency_id'=>'BRL']]);
            if ((int)round((float)($r['body']['auto_recurring']['transaction_amount']??0)*100)!==$valor) throw new RuntimeException('Valor não confirmado.');
        };
        if ($j['estado']==='preparada') {
            if ($next!==(int)$j['renovacao'] || $next<=time()) { $db->query("UPDATE trocas_planos SET estado='revisao' WHERE id=".(int)$id);$db->commit();continue; }
            $atualizarValor((int)$j['valor_programado']);
            if ((int)$j['estorno']>0) {
                $refund=mp_api_request('POST','/v1/payments/'.rawurlencode($j['pagamento_origem']).'/refunds',['amount'=>(int)$j['estorno']/100],$j['chave'])['body']??[];
                if (($refund['status']??'')!=='approved' || empty($refund['id'])) throw new RuntimeException('Estorno sem confirmação de aprovação.');
                $rid=(string)$refund['id'];
                $save=$db->prepare('UPDATE trocas_planos SET estorno_mp=? WHERE id=?');$save->bind_param('si',$rid,$id);$save->execute();
            }
            $v=$db->prepare('UPDATE checkout_assinaturas SET plano=? WHERE tenant_id=? AND mp_preapproval_id=?');$v->bind_param('sss',$j['plano_destino'],$j['tenant_id'],$j['assinatura_mp']);$v->execute();
            $v=$db->prepare('UPDATE tenants SET plano_atual=? WHERE tenant_id=? AND id_assinatura_mp=?');$v->bind_param('sss',$j['plano_destino'],$j['tenant_id'],$j['assinatura_mp']);$v->execute();
            $estado=(int)$j['valor_programado']===(int)$j['valor_normal'] && (int)$j['credito_restante']===0?'concluida':'aguardando_fatura';
            $v=$db->prepare('UPDATE trocas_planos SET estado=? WHERE id=?');$v->bind_param('si',$estado,$id);$v->execute();
        } elseif ($next>(int)$j['renovacao']) {
            $data=gmdate('Y-m-d',(int)$j['renovacao']-86400);
            $f=$db->prepare("SELECT transacao_id FROM faturas_assinatura WHERE tenant_id=? AND status='pago' AND data_pagamento>=? AND transacao_id<>? ORDER BY id DESC LIMIT 12");
            $f->bind_param('sss',$j['tenant_id'],$data,$j['pagamento_origem']);$f->execute();
            foreach ($f->get_result() as $row) {
                $pid=(string)$row['transacao_id'];
                if (!ctype_digit($pid)) continue;
                $pay=mp_api_request('GET','/v1/payments/'.rawurlencode($pid))['body']??[];
                if (($pay['external_reference']??'')!==$j['referencia'] || ($pay['status']??'')!=='approved' || strtotime($pay['date_approved']??'')<(int)$j['renovacao']-3600 || (int)round((float)($pay['transaction_amount']??0)*100)!==(int)$j['valor_programado']) continue;
                $normal=(int)$j['valor_normal'];
                $saldo=(int)$j['credito_restante'];
                $novo=max((int)$j['piso'],$normal-$saldo);
                $resta=max(0,$saldo-($normal-$novo));
                $atualizarValor($novo);
                $estado=$novo===$normal?'concluida':'aguardando_fatura';
                $v=$db->prepare('UPDATE trocas_planos SET estado=?,valor_programado=?,credito_restante=?,renovacao=?,pagamento_origem=? WHERE id=?');
                $v->bind_param('siiisi',$estado,$novo,$resta,$next,$pid,$id);$v->execute();break;
            }
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollback();error_log('Troca de plano #'.$id.': '.$e->getMessage()); }
}

<?php
function sincronizarRecompensasIndicador(mysqli $db, int $indicadorId): void {
    $stmt=$db->prepare('SELECT FLOOR(COUNT(*)/3) ciclos FROM indicacoes WHERE id_indicador=?');
    $stmt->bind_param('i',$indicadorId); $stmt->execute();
    $ciclos=(int)($stmt->get_result()->fetch_assoc()['ciclos']??0); $stmt->close();
    $ins=$db->prepare("INSERT IGNORE INTO indicacao_recompensas(indicador_id,ciclo) VALUES(?,?)");
    for($c=1;$c<=$ciclos;$c++){ $ins->bind_param('ii',$indicadorId,$c); $ins->execute(); }
    $ins->close();
}
function mesesPendentesIndicador(mysqli $db,int $indicadorId): int {
    sincronizarRecompensasIndicador($db,$indicadorId);
    $s=$db->prepare("SELECT COALESCE(SUM(meses),0) total FROM indicacao_recompensas WHERE indicador_id=? AND status='pendente'");
    $s->bind_param('i',$indicadorId);$s->execute();$n=(int)$s->get_result()->fetch_assoc()['total'];$s->close();return $n;
}
function reservarRecompensasIndicador(mysqli $db,int $indicadorId,int $checkoutId): void {
    $s=$db->prepare("UPDATE indicacao_recompensas SET status='reservado',checkout_id=? WHERE indicador_id=? AND status='pendente'");
    $s->bind_param('ii',$checkoutId,$indicadorId);$s->execute();$s->close();
}
function aplicarRecompensasCheckout(mysqli $db,int $checkoutId,string $mpId): void {
    $s=$db->prepare("UPDATE indicacao_recompensas SET status='aplicado',mp_preapproval_id=?,aplicado_em=NOW() WHERE checkout_id=? AND status='reservado'");
    $s->bind_param('si',$mpId,$checkoutId);$s->execute();$s->close();
}

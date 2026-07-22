<?php
$caixaPendente = null;
try {
    $paginaAtual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($_SESSION['usuario_logado']) && $paginaAtual !== 'fechamento_caixa.php' && function_exists('getTenantConnection')) {
        $caixaConn = getTenantConnection();
        if ($caixaConn) {
            $donoDados = get_data_owner_id();
            $sql = "SELECT DATE(v.data_venda) AS data_caixa,COUNT(*) AS quantidade,COALESCE(SUM(v.valor_total),0) AS total
                      FROM vendas v
                 LEFT JOIN fechamentos_caixa f ON f.id_usuario=v.id_usuario AND f.data_caixa=DATE(v.data_venda)
                     WHERE v.id_usuario=?
                       AND (DATE(v.data_venda)<CURDATE() OR (DATE(v.data_venda)=CURDATE() AND CURTIME()>='18:00:00'))
                  GROUP BY DATE(v.data_venda),f.ultimo_venda_id
                    HAVING MAX(v.id)>COALESCE(f.ultimo_venda_id,0)
                  ORDER BY data_caixa ASC LIMIT 1";
            $stmtCaixa = $caixaConn->prepare($sql);
            $stmtCaixa->bind_param('i', $donoDados);
            $stmtCaixa->execute();
            $caixaPendente = $stmtCaixa->get_result()->fetch_assoc();
            $stmtCaixa->close();
            $caixaConn->close();
        }
    }
} catch (Throwable $e) {
    $caixaPendente = null;
}
?>
<?php if ($caixaPendente): ?>
<style>
#caixaReminder{position:fixed;right:22px;bottom:22px;z-index:19990;width:min(430px,calc(100vw - 44px));background:#fff7df;color:#382b00;border:1px solid #e4ad18;border-left:6px solid #f0ad00;border-radius:10px;padding:18px;box-shadow:0 12px 38px rgba(0,0,0,.35);font-family:Arial,sans-serif}#caixaReminder h3{margin:0 0 8px;color:#7a5100;font-size:19px}#caixaReminder p{margin:5px 0;line-height:1.45}#caixaReminder .caixa-actions{display:flex;gap:9px;margin-top:14px;flex-wrap:wrap}#caixaReminder a,#caixaReminder button{border:0;border-radius:6px;padding:10px 13px;font-weight:700;cursor:pointer;text-decoration:none}#caixaReminder a{background:#087ea4;color:#fff}#caixaReminder button{background:#ded5bd;color:#382b00}
</style>
<aside id="caixaReminder" role="alert" aria-live="assertive">
    <h3>Fechamento de caixa pendente</h3>
    <p>Existem <strong><?= (int)$caixaPendente['quantidade'] ?> venda(s)</strong> de <?= date('d/m/Y', strtotime($caixaPendente['data_caixa'])) ?>, totalizando <strong>R$ <?= number_format((float)$caixaPendente['total'], 2, ',', '.') ?></strong>.</p>
    <p>Revise e confirme o fechamento para concluir o dia.</p>
    <div class="caixa-actions"><a href="<?= str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/pages/') ? '' : '../pages/' ?>fechamento_caixa.php?data=<?= urlencode($caixaPendente['data_caixa']) ?>">Fechar caixa agora</a><button type="button" onclick="document.getElementById('caixaReminder').remove()">Lembrar depois</button></div>
</aside>
<?php endif; ?>

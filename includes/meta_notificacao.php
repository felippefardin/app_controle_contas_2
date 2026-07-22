<?php
$metaNotif = null;
if (!empty($_SESSION['usuario_id']) && function_exists('getTenantConnection')) {
    try {
        $metaConn = getTenantConnection();
        if ($metaConn) {
            $metaUsuario = (int)$_SESSION['usuario_id'];
            $stmtMetaNotif = $metaConn->prepare('SELECT id,titulo,mensagem FROM notificacoes_equipe WHERE usuario_id=? AND lida_em IS NULL ORDER BY criado_em LIMIT 1');
            $stmtMetaNotif->bind_param('i',$metaUsuario);$stmtMetaNotif->execute();$metaNotif=$stmtMetaNotif->get_result()->fetch_assoc();
        }
    } catch (Throwable $e) { $metaNotif=null; }
}
?>
<?php if($metaNotif): ?>
<style>#metaParabensOverlay{position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:20000;display:flex;align-items:center;justify-content:center;padding:20px}.meta-parabens-card{max-width:560px;background:linear-gradient(145deg,#15202b,#202f3d);color:#fff;border:1px solid #00bfff;border-radius:18px;padding:34px;text-align:center;box-shadow:0 20px 60px rgba(0,191,255,.25)}.meta-trofeu{font-size:58px}.meta-parabens-card h2{color:#ffd166}.meta-parabens-card button{background:#00bfff;color:#001018;border:0;border-radius:8px;padding:12px 24px;font-weight:bold;cursor:pointer}</style>
<div id="metaParabensOverlay"><div class="meta-parabens-card"><div class="meta-trofeu">🏆🎉</div><h2><?=htmlspecialchars($metaNotif['titulo'])?></h2><p><?=htmlspecialchars($metaNotif['mensagem'])?></p><button onclick="fecharMetaParabens()">Comemorar com a equipe!</button></div></div>
<script>function fecharMetaParabens(){document.getElementById('metaParabensOverlay').remove();fetch('../actions/marcar_notificacao_meta.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id=<?= (int)$metaNotif['id'] ?>'});}</script>
<?php endif; ?>

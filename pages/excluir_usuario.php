<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_tenant_admin();
// Endpoint antigo desativado: exclusões agora exigem POST, CSRF e são recuperáveis.
header('Location: usuarios.php?erro=use_exclusao_segura');
exit;

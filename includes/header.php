<?php
require_once __DIR__ . '/session_init.php';

// ===============================
// TEMA GLOBAL (PERSISTENTE COM COOKIES)
// ===============================
// 1. Tenta pegar da Sessão
// 2. Se não tiver sessão, tenta pegar do Cookie
// 3. Se não tiver nenhum, assume 'dark' (padrão)
$temaAtual = $_SESSION['tema_preferencia'] ?? $_COOKIE['tema_preferencia'] ?? 'dark';

// Garante que o padrão é dark se vier algo estranho
if ($temaAtual !== 'light' && $temaAtual !== 'dark') {
    $temaAtual = 'dark';
}

// Aplica a classe apenas se for light, pois o CSS padrão já é dark
$classeBody = ($temaAtual === 'light') ? 'light-mode' : ''; 
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<title>App Controle de Contas</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme.css">
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
</head>

<body class="<?= $classeBody ?>">

<header class="header-controls">
   <div class="header-group">
    <button class="btn btn-font-access" onclick="adjustFontSize(-1)">A-</button>
    <button class="btn btn-font-access" onclick="adjustFontSize(1)">A+</button>
    <button class="btn btn-font-access" onclick="resetFontSize()">
        <i class="fas fa-sync-alt"></i>
    </button>
</div>

   <div class="header-group">
        <?php if (basename($_SERVER['PHP_SELF']) === 'home.php' || true): ?>
            <button id="themeToggle" class="btn" onclick="toggleTheme()">
                <i class="fas <?= $temaAtual === 'light' ? 'fa-moon' : 'fa-sun' ?>"></i>
            </button>
        <?php endif; ?>

        <?php if (isset($_SESSION['super_admin_original'])): ?>
            <form action="../actions/retornar_super_admin.php" method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn" style="background-color: #ff9800; color: white; margin-right: 10px;">
                    <i class="fas fa-user-shield"></i> Voltar Admin
                </button>
            </form>
        <?php endif; ?>
        <a href="../pages/home.php" class="btn btn-home">
            <i class="fas fa-home"></i> Home
        </a>

        <a href="../pages/logout.php" class="btn btn-exit">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </div>
</header>

<script>
// Inclui o token nas submissões e requisições internas sem depender de cada tela.
document.addEventListener('DOMContentLoaded', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    document.querySelectorAll('form').forEach(form => {
        if ((form.method || 'get').toLowerCase() !== 'post' || form.querySelector('input[name="csrf_token"]')) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = token;
        form.appendChild(input);
    });
});

const nativeFetch = window.fetch.bind(window);
window.fetch = (resource, options = {}) => {
    const method = String(options.method || 'GET').toUpperCase();
    const target = typeof resource === 'string' ? resource : resource.url;
    const url = new URL(target, window.location.href);
    if (method !== 'GET' && url.origin === window.location.origin) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const headers = new Headers(options.headers || {});
        headers.set('X-CSRF-Token', token);
        options = {...options, headers};
    }
    return nativeFetch(resource, options);
};

// ===============================
// TOGGLE DE TEMA (PERSISTENTE)
// ===============================
function toggleTheme() {
    if (window.appTheme) {
        window.appTheme.toggle();
        return;
    }
    const body = document.body;
    const icon = document.querySelector('#themeToggle i');

    // Alterna a classe visualmente
    body.classList.toggle('light-mode');
    
    // Verifica qual estado ficou ativo
    const isLight = body.classList.contains('light-mode');
    const novoTema = isLight ? 'light' : 'dark';

    // Atualiza ícone
    if (icon) {
        // Se for light, mostra lua (para ir pro dark). Se for dark, mostra sol (para ir pro light)
        // Ajuste conforme sua preferência de ícone
        icon.className = 'fas ' + (isLight ? 'fa-moon' : 'fa-sun');
    }

    // 1. SALVA NO COOKIE DO NAVEGADOR (Validade de 1 ano)
    const d = new Date();
    d.setTime(d.getTime() + (365 * 24 * 60 * 60 * 1000));
    let expires = "expires="+ d.toUTCString();
    document.cookie = "tema_preferencia=" + novoTema + ";" + expires + ";path=/";

    // 2. SALVA NO BACKEND (SESSÃO + BANCO)
    const formData = new FormData();
    formData.append('tema', novoTema);

    fetch('../actions/salvar_tema.php', {
        method: 'POST',
        body: formData
    }).catch(err => console.error("Erro ao salvar tema:", err));
}

// ===============================
// ACESSIBILIDADE – FONTE
// ===============================
function adjustFontSize(amount) {
    const els = [document.documentElement, document.body];
    els.forEach(el => {
        let size = parseFloat(getComputedStyle(el).fontSize) || 16;
        el.style.fontSize = (size + amount) + 'px';
    });
}

function resetFontSize() {
    document.documentElement.style.fontSize = '';
    document.body.style.fontSize = '';
}
</script>

<?php require_once __DIR__ . '/meta_notificacao.php'; ?>
<?php require_once __DIR__ . '/caixa_lembrete.php'; ?>
<main>

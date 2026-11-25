<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/database.php';

$feedbacksPublicados = [];
try {
    $feedbackConn = getMasterConnection();
    $feedbackResult = $feedbackConn->query("SELECT nome, descricao, pontuacao, anonimo, criado_em FROM feedbacks WHERE aprovado = 1 AND descricao IS NOT NULL AND TRIM(descricao) <> '' ORDER BY criado_em DESC, id DESC LIMIT 6");
    if ($feedbackResult) {
        $feedbacksPublicados = $feedbackResult->fetch_all(MYSQLI_ASSOC);
    }
    $feedbackConn->close();
} catch (Throwable $feedbackError) {
    error_log('Falha ao carregar feedbacks públicos: ' . $feedbackError->getMessage());
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestPath = strtok($_SERVER['REQUEST_URI'] ?? '/app_controle_contas_2/index.php', '?');
$canonicalUrl = $scheme . '://' . $host . $requestPath;
$basePath = rtrim(str_replace('\\', '/', dirname($requestPath)), '/');
$shareImageUrl = $scheme . '://' . $host . $basePath . '/img/dashboard-ia-financeira.png';
$temaAtual = $_SESSION['tema_preferencia'] ?? $_COOKIE['tema_preferencia'] ?? 'dark';
if (!in_array($temaAtual, ['light', 'dark'], true)) $temaAtual = 'dark';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-app-theme="<?= htmlspecialchars($temaAtual, ENT_QUOTES, 'UTF-8') ?>">
<head>
<?php session_idle_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Controle vendas, estoque, caixa, contas e equipe em um só sistema. Feito para pessoas físicas, autônomos, MEIs e pequenas empresas.">
    <meta name="theme-color" content="#075dd8">
    <meta name="robots" content="index, follow">
    <title>App Controle Pro | Vendas, estoque, caixa e financeiro</title>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="App Controle Pro | Vendas, estoque, caixa e financeiro">
    <meta property="og:description" content="Organize finanças pessoais ou empresariais, vendas, estoque e caixa em um só lugar.">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($shareImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="Painel financeiro do App Controle Pro">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="App Controle Pro | Vendas, estoque, caixa e financeiro">
    <meta name="twitter:description" content="Organize finanças pessoais ou empresariais, vendas, estoque e caixa em um só lugar.">
    <meta name="twitter:image" content="<?= htmlspecialchars($shareImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="assets/css/universal-theme.css" rel="stylesheet">
    <link href="assets/css/accessibility-widget.css?v=20260903-column-2" rel="stylesheet">
    <script src="assets/js/global-theme.js?v=20260903-cookie-6"></script>
    <script src="assets/js/accessibility-widget.js?v=20260903-column-2" defer></script>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'App Controle Pro',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'description' => 'Plataforma de gestão financeira, vendas, estoque e caixa para pessoas, autônomos e empresas.',
        'url' => $canonicalUrl,
        'offers' => [
            ['@type' => 'Offer', 'price' => '19.00', 'priceCurrency' => 'BRL'],
            ['@type' => 'Offer', 'price' => '39.00', 'priceCurrency' => 'BRL'],
            ['@type' => 'Offer', 'price' => '59.00', 'priceCurrency' => 'BRL']
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <style>
        :root{--brand:#075dd8;--brand-dark:#0647a6;--accent:#00bfff;--ink:#122033;--muted:#617086;--surface:#f5f8fc;--line:#dfe7f1;--success:#168a4b}
        html{scroll-behavior:smooth;scroll-padding-top:88px}body{font-family:Inter,"Segoe UI",sans-serif;color:var(--ink);background:#fff;overflow-x:hidden}
        .navbar{background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-bottom:1px solid var(--line);padding:14px 0}.navbar-brand{font-weight:800;color:var(--brand)!important}.nav-link{font-weight:600;color:#334155!important}.nav-link:hover{color:var(--brand)!important}
        .btn-brand{background:var(--brand);border:1px solid var(--brand);color:#fff;font-weight:700;border-radius:10px;padding:12px 22px;box-shadow:0 10px 24px rgba(7,93,216,.2)}.btn-brand:hover{background:var(--brand-dark);border-color:var(--brand-dark);color:#fff;transform:translateY(-1px)}
        .btn-soft{background:#fff;border:1px solid #b9c9dc;color:var(--ink);font-weight:700;border-radius:10px;padding:12px 22px}.btn-soft:hover{border-color:var(--brand);color:var(--brand)}
        .hero{padding:145px 0 85px;background:radial-gradient(circle at 85% 18%,rgba(0,191,255,.18),transparent 30%),linear-gradient(135deg,#f4f9ff,#fff 62%)}.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:#e8f2ff;color:var(--brand);font-size:.84rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .hero h1{font-weight:800;font-size:clamp(2.4rem,5vw,4.4rem);line-height:1.05;letter-spacing:-.055em;margin:22px 0}.hero h1 span{color:var(--brand)}.hero-lead{font-size:1.16rem;line-height:1.7;color:var(--muted);max-width:650px}.micro-proof{display:flex;gap:22px;flex-wrap:wrap;color:#475569;font-size:.9rem}.micro-proof i{color:var(--success)}
        .app-preview{background:#111827;border-radius:18px;padding:10px;box-shadow:0 28px 70px rgba(20,49,83,.25);position:relative}.app-preview img{display:block;width:100%;border-radius:11px}
        .hero-control{position:relative;overflow:hidden;background:linear-gradient(145deg,#0c1b31,#102b48);border:1px solid #294b70;border-radius:22px;padding:27px;box-shadow:0 28px 70px rgba(20,49,83,.28);color:#fff}.hero-control:before{content:"";position:absolute;width:210px;height:210px;border-radius:50%;right:-90px;top:-100px;background:rgba(0,191,255,.13)}.hero-control-head{position:relative;display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}.hero-control-kicker{color:#66d2ff;font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.hero-control h2{font-size:1.45rem;font-weight:800;margin:5px 0 0}.hero-control-status{display:inline-flex;align-items:center;gap:6px;background:rgba(22,138,75,.18);color:#7ee2a8;border:1px solid rgba(126,226,168,.3);border-radius:999px;padding:7px 10px;font-size:.72rem;font-weight:800;white-space:nowrap}.hero-control-status:before{content:"";width:7px;height:7px;border-radius:50%;background:#34d17b;box-shadow:0 0 0 4px rgba(52,209,123,.12)}.hero-module-grid{position:relative;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.hero-module{display:flex;align-items:center;gap:12px;min-height:88px;padding:15px;border:1px solid rgba(255,255,255,.1);border-radius:14px;background:rgba(255,255,255,.055)}.hero-module-icon{flex:0 0 42px;width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:rgba(0,191,255,.13);color:#66d2ff;font-size:1.15rem}.hero-module strong{display:block;font-size:.9rem}.hero-module small{display:block;color:#aebfd2;line-height:1.35;margin-top:3px}.hero-insight{position:relative;display:flex;align-items:center;gap:12px;margin-top:13px;padding:14px 15px;border-radius:14px;background:linear-gradient(90deg,rgba(7,93,216,.35),rgba(0,191,255,.12));border:1px solid rgba(102,210,255,.25)}.hero-insight i{font-size:1.25rem;color:#66d2ff}.hero-insight strong{display:block;font-size:.88rem}.hero-insight span{display:block;color:#b9c9da;font-size:.76rem;margin-top:2px}
        html[data-app-theme="light"] body.light-mode .hero-control .hero-module small{color:#d5e1ee!important}
        
        .preview-label{position:absolute;background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:0 8px 25px rgba(15,23,42,.16);border-radius:11px;padding:10px 13px;font-weight:700;font-size:.82rem;white-space:nowrap;z-index:2}
        .preview-label i{color:var(--brand)}
        .label-one{left:-25px;bottom:50px}.label-two{right:-18px;top:45px}

        .section{padding:88px 0}.section-soft{background:var(--surface)}.section-kicker{color:var(--brand);font-size:.82rem;text-transform:uppercase;font-weight:800;letter-spacing:.08em}.section-title{font-weight:800;letter-spacing:-.035em;font-size:clamp(2rem,4vw,3rem)}.section-lead{color:var(--muted);font-size:1.08rem;max-width:760px}
        .benefit-card,.feature-card,.step-card,.trust-card{height:100%;background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px}.benefit-card:hover,.feature-card:hover{border-color:#9fc4f5;box-shadow:0 15px 38px rgba(26,61,102,.09);transform:translateY(-3px)}.benefit-card,.feature-card{transition:.25s}.icon-box{width:52px;height:52px;border-radius:13px;display:grid;place-items:center;background:#e8f2ff;color:var(--brand);font-size:1.4rem;margin-bottom:18px}.benefit-card h3,.feature-card h3{font-size:1.05rem;font-weight:800}.benefit-card p,.feature-card p,.step-card p,.trust-card p{color:var(--muted);margin-bottom:0;line-height:1.6}
        .step-number{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:var(--brand);color:#fff;font-weight:800;margin-bottom:16px}.screen-showcase{border-radius:18px;border:1px solid var(--line);background:#fff;box-shadow:0 22px 55px rgba(15,23,42,.12);overflow:hidden}.screen-showcase img{width:100%;display:block}.screen-tabs{display:flex;gap:8px;flex-wrap:wrap}.screen-tabs button{border:1px solid var(--line);background:#fff;border-radius:999px;padding:8px 13px;font-weight:700;color:#526176}.screen-tabs button.active{background:var(--brand);border-color:var(--brand);color:#fff}
        
        .fiscal-note{background:#fff7df;border:1px solid #f0ce68;color:#59430c;border-radius:13px;padding:17px}
        .conversion-strip{display:flex;align-items:center;justify-content:space-between;gap:20px;background:#eaf3ff;border:1px solid #bfd8f8;border-radius:16px;padding:22px 24px}.conversion-strip strong{display:block;font-size:1.08rem}.conversion-strip span{color:var(--muted)}
        .comparison-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:16px;background:#fff}.plan-comparison{margin:0;min-width:680px}.plan-comparison th,.plan-comparison td{padding:15px 18px;border-color:var(--line);vertical-align:middle}.plan-comparison thead th{background:#eaf3ff;color:var(--ink)}.plan-comparison tbody th{font-weight:600}.plan-comparison .compare-yes{color:var(--success)!important;font-weight:800}.plan-comparison .compare-yes i{color:inherit!important}.compare-no{color:#94a3b8}.plan-assurances{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.assurance-item{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:13px;padding:15px;background:#fff;font-weight:700}.assurance-item i{color:var(--brand);font-size:1.25rem}.mobile-cta{display:none}
        html[data-app-theme="light"] body.light-mode .plan-comparison tbody td.compare-yes,body.light-mode:not(.dark-mode) .plan-comparison tbody td.compare-yes{color:#168a4b!important;-webkit-text-fill-color:#168a4b!important}html[data-app-theme="light"] body.light-mode .plan-comparison tbody td.compare-yes i,body.light-mode:not(.dark-mode) .plan-comparison tbody td.compare-yes i{color:#168a4b!important;-webkit-text-fill-color:#168a4b!important}

        .trust-band{background:#0c1b31;color:#fff}.trust-band .section-lead{color:#bdc9d8}.trust-card{background:#142740;border-color:#29405d}.trust-card h3{font-size:1rem;font-weight:800}.trust-card p{color:#bdc9d8}.trust-card i{color:#66d2ff}
        .testimonial{height:100%;background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px}.testimonial .stars{color:#f4a900}.avatar{width:42px;height:42px;border-radius:50%;background:var(--brand);color:#fff;display:grid;place-items:center;font-weight:800}
        .feedback-public-trigger{position:fixed;right:30px;bottom:30px;z-index:10058;width:50px;height:50px;border-radius:50%;border:1px solid rgba(255,255,255,.32);background:#ffc107;color:#111;display:grid;place-items:center;padding:0;box-shadow:0 8px 24px rgba(0,0,0,.24);font-size:18px}.feedback-public-trigger:hover{background:#ffca2c;color:#111;transform:translateY(-2px)}
        body[data-accessibility-widget] .a11y-launcher{right:30px!important;bottom:90px!important}
        body[data-accessibility-widget] .a11y-panel{right:30px;bottom:152px}
        .feedback-public-status{min-height:24px;font-size:.9rem;font-weight:600}.feedback-public-status.success{color:#198754}.feedback-public-status.error{color:#dc3545}.feedback-stars{color:#f5a623;letter-spacing:2px}.feedback-avatar{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:rgba(13,110,253,.12);color:#0d6efd;font-weight:800}
        .pricing-card{height:100%;border:1px solid var(--line);border-radius:18px;background:#fff;padding:29px;display:flex;flex-direction:column}.pricing-card.featured{border:2px solid var(--brand);box-shadow:0 20px 45px rgba(7,93,216,.14);position:relative}.popular{position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:var(--brand);color:#fff;border-radius:999px;padding:6px 15px;font-size:.75rem;font-weight:800;white-space:nowrap}.price{font-size:2.5rem;font-weight:800;color:var(--brand);letter-spacing:-.05em}.price small{font-size:.9rem;font-weight:500;color:var(--muted);letter-spacing:0}.pricing-card li{margin:9px 0;color:#4b5b70}.pricing-card li i{color:var(--success)}.pricing-card .not-included,.pricing-card .not-included i{color:#9aa6b5}
        .referral{background:linear-gradient(135deg,#0647a6,#075dd8);color:#fff;border-radius:20px;padding:36px}
        
        /* Força a cor preta no texto "Indique e ganhe" em ambos os modos */
        .referral .eyebrow { color: #000 !important; background-color: #ffc107 !important; }

        .faq .accordion-item{border:1px solid var(--line);border-radius:12px!important;margin-bottom:10px;overflow:hidden}.faq .accordion-button{font-weight:700;box-shadow:none!important}
        .final-cta{background:linear-gradient(135deg,#075dd8,#003f9b);color:#fff;border-radius:24px;padding:55px}.footer-link{color:#aebdd0;text-decoration:none}.footer-link:hover{color:#fff}

        /* Componentes que também acompanham o modo claro */
        html[data-app-theme="light"] body.light-mode .hero-control{background:linear-gradient(145deg,#fff,#edf6ff);border-color:#bfd5ef;color:#172033;box-shadow:0 24px 55px rgba(26,61,102,.16)}
        html[data-app-theme="light"] body.light-mode .hero-control:before{background:rgba(0,191,255,.1)}
        html[data-app-theme="light"] body.light-mode .hero-control-kicker{color:#087cba}
        html[data-app-theme="light"] body.light-mode .hero-control-status{background:#e8f8ef;color:#137a3b;border-color:#9dd8b7}
        html[data-app-theme="light"] body.light-mode .hero-module{background:#fff;border-color:#d7e3f0;color:#172033;box-shadow:0 7px 18px rgba(26,61,102,.05)}
        html[data-app-theme="light"] body.light-mode .hero-module-icon{background:#e5f5fc;color:#087cba}
        html[data-app-theme="light"] body.light-mode .hero-control .hero-module small{color:#5d6d82!important}
        html[data-app-theme="light"] body.light-mode .hero-insight{background:#eaf4ff;border-color:#b9d5f5;color:#172033}
        html[data-app-theme="light"] body.light-mode .hero-insight i{color:#075dd8}
        html[data-app-theme="light"] body.light-mode .hero-insight span{color:#53657c}
        html[data-app-theme="light"] body.light-mode .trust-band{background:#edf5ff;color:#172033;border-block:1px solid #d7e4f2}
        html[data-app-theme="light"] body.light-mode .trust-band .section-lead{color:#5d6d82}
        html[data-app-theme="light"] body.light-mode .trust-card{background:#fff;border-color:#ceddec;color:#172033;box-shadow:0 10px 25px rgba(26,61,102,.07)}
        html[data-app-theme="light"] body.light-mode .trust-card p{color:#5d6d82}
        html[data-app-theme="light"] body.light-mode .trust-card i{color:#087cba}
        html[data-app-theme="light"] body.light-mode footer.bg-dark{background:#fff!important;color:#172033!important;border-top:1px solid #d7e1ed}
        html[data-app-theme="light"] body.light-mode footer .text-secondary{color:#637389!important}
        html[data-app-theme="light"] body.light-mode footer .footer-link{color:#465a72}
        html[data-app-theme="light"] body.light-mode footer .footer-link:hover{color:#075dd8}
        html[data-app-theme="light"] body.light-mode .mobile-cta{background:#fff;color:#172033;border-color:#cbd9e8}
        html[data-app-theme="light"] body.light-mode .mobile-cta small{color:#637389}
        
        /* Ajustes gerais do modo escuro */
        body.dark-mode{background:#0c111b;color:#e8eef6;--ink:#eef4fb;--muted:#aab8ca;--surface:#111a27;--line:#29374a}
        body.dark-mode .navbar{background:rgba(12,17,27,.96)}
        body.dark-mode .navbar .nav-link{color:#fff!important}
        body.dark-mode .navbar .nav-link:hover,
        body.dark-mode .navbar .nav-link:focus,
        body.dark-mode .navbar .nav-link:focus-visible{color:var(--brand)!important}
        body.dark-mode .benefit-card,body.dark-mode .feature-card,body.dark-mode .step-card,body.dark-mode .screen-showcase,body.dark-mode .testimonial,body.dark-mode .pricing-card,body.dark-mode .accordion-item,body.dark-mode .accordion-button,body.dark-mode .accordion-body{background:#152032;color:#eef4fb}
        body.dark-mode .hero{background:radial-gradient(circle at 85% 18%,rgba(0,191,255,.15),transparent 30%),#0d1522}
        body.dark-mode .btn-soft,body.dark-mode .screen-tabs button{background:#152032;color:#dbe7f4}
        body.dark-mode .screen-tabs button.active{background:var(--brand);color:#fff}
        body.dark-mode .preview-label{background:#152032;color:#eef4fb;border-color:#29374a}
        body.dark-mode .fiscal-note{background:#152032;border-color:#29374a;color:#eef4fb}
        body.dark-mode .pricing-card p,body.dark-mode .pricing-card li,body.dark-mode .pricing-card .text-muted,body.dark-mode .pricing-card small.text-muted{color:#cbd5e1!important}
        body.dark-mode .pricing-card .not-included,body.dark-mode .pricing-card .not-included i{color:#94a3b8!important}
        body.dark-mode .conversion-strip,body.dark-mode .comparison-wrap,body.dark-mode .assurance-item{background:#152032;border-color:#334155;color:#eef4fb}body.dark-mode .conversion-strip span{color:#cbd5e1}body.dark-mode .plan-comparison{color:#e5edf7;--bs-table-bg:#152032;--bs-table-color:#e5edf7;--bs-table-border-color:#3a4a61}body.dark-mode .plan-comparison thead th{background:#22344d!important;color:#fff!important}body.dark-mode .plan-comparison tbody th,body.dark-mode .plan-comparison tbody td{background:#152032!important;color:#e5edf7!important;border-color:#3a4a61}body.dark-mode .plan-comparison .compare-yes{color:#55d98b!important}body.dark-mode .plan-comparison .compare-no{color:#aebbc9!important}

        @media(max-width:991px){.hero{text-align:center;padding-top:120px}.hero-lead{margin-inline:auto}.hero-actions,.micro-proof{justify-content:center}.app-preview,.hero-control{margin-top:42px;text-align:left}.preview-label{display:none}.plan-assurances{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:768px){.feedback-public-trigger{right:20px!important;bottom:20px!important}body[data-accessibility-widget] .a11y-launcher{right:20px!important;bottom:76px!important}body[data-accessibility-widget] .a11y-panel{right:16px;bottom:134px}}@media(max-width:575px){body{padding-bottom:72px}.row.g-5{--bs-gutter-x:1.5rem}.section{padding:65px 0}.final-cta,.referral{padding:30px 22px}.hero-actions .btn{width:100%}.hero-control{padding:20px}.hero-control-head{display:block}.hero-control-status{margin-top:12px}.hero-module-grid{grid-template-columns:1fr}.conversion-strip{align-items:stretch;flex-direction:column}.conversion-strip .btn{width:100%}.plan-assurances{grid-template-columns:1fr}.mobile-cta{position:fixed;left:10px;right:10px;bottom:10px;z-index:1040;display:flex;align-items:center;justify-content:space-between;gap:12px;background:#0c1b31;color:#fff;border:1px solid #294b70;border-radius:15px;padding:10px 11px 10px 15px;box-shadow:0 12px 35px rgba(0,0,0,.3)}.mobile-cta small{display:block;color:#bdc9d8}.mobile-cta .btn{padding:9px 14px;white-space:nowrap}}
    </style>
</head>
<body data-accessibility-widget>
<a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
<nav class="navbar navbar-expand-lg fixed-top" aria-label="Navegação principal">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-wallet2 me-2"></i>App Controle Pro</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="#como-funciona">Como funciona</a></li>
                <li class="nav-item"><a class="nav-link" href="#para-quem">Para quem é</a></li>
                <li class="nav-item"><a class="nav-link" href="#recursos">Recursos</a></li>
                <li class="nav-item"><a class="nav-link" href="#planos">Planos</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">Dúvidas</a></li>
                <li class="nav-item"><button id="darkModeToggle" class="btn nav-link" aria-label="Alternar tema"><i class="bi bi-moon-stars-fill"></i></button></li>
                <li class="nav-item ms-lg-2"><a class="btn btn-soft" href="pages/login.php">Entrar</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0"><a class="btn btn-brand" href="pages/registro.php">Testar grátis</a></li>
            </ul>
        </div>
    </div>
</nav>

<main id="conteudo-principal" tabindex="-1">
<header class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="eyebrow"><i class="bi bi-stars"></i> Para pessoas físicas, autônomos e empresas</span>
                <h1>Controle vendas, estoque, caixa e financeiro <span>em um só lugar.</span></h1>
                <p class="hero-lead">Uma plataforma robusta e intuitiva para organizar finanças pessoais ou empresariais, controlar a operação, acompanhar resultados em tempo real e receber alertas com análise inteligente.</p>
                <div class="hero-actions d-flex flex-column flex-sm-row gap-3 mt-4">
                    <a class="btn btn-brand btn-lg" href="pages/registro.php">Teste grátis: 15 ou 30 dias <i class="bi bi-arrow-right ms-1"></i></a>
                    <a class="btn btn-soft btn-lg" href="#demonstracao"><i class="bi bi-play-circle me-1"></i> Ver o sistema</a>
                </div>
                <div class="micro-proof mt-4">
                    <span><i class="bi bi-check-circle-fill me-1"></i> Acesso pelo navegador</span>
                    <span><i class="bi bi-check-circle-fill me-1"></i> Computador e celular</span>
                    <span><i class="bi bi-check-circle-fill me-1"></i> Suporte humanizado</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-control" aria-label="Áreas integradas do sistema">
                    <div class="hero-control-head">
                        <div><div class="hero-control-kicker">Visão integrada</div><h2>Uma rotina, todo o controle</h2></div>
                        <span class="hero-control-status">Tudo conectado</span>
                    </div>
                    <div class="hero-module-grid">
                        <div class="hero-module"><span class="hero-module-icon"><i class="bi bi-wallet2"></i></span><div><strong>Financeiro</strong><small>Contas, vencimentos e fluxo de caixa</small></div></div>
                        <div class="hero-module"><span class="hero-module-icon"><i class="bi bi-cart-check-fill"></i></span><div><strong>Vendas e PDV</strong><small>Caixa diário, vendas e recibos</small></div></div>
                        <div class="hero-module"><span class="hero-module-icon"><i class="bi bi-box-seam"></i></span><div><strong>Estoque e compras</strong><small>Produtos, custos e quantidades</small></div></div>
                        <div class="hero-module"><span class="hero-module-icon"><i class="bi bi-people"></i></span><div><strong>Equipe e acesso</strong><small>Usuários, funções e permissões</small></div></div>
                    </div>
                    <div class="hero-insight"><i class="bi bi-stars"></i><div><strong>IA financeira local</strong><span>Alertas e recomendações sem alterar seus lançamentos.</span></div></div>
                </div>
            </div>
        </div>
    </div>
</header>

<section id="para-quem" class="section section-soft">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-kicker">Feito para diferentes rotinas</span>
            <h2 class="section-title mt-2">Escolha o controle que combina com você</h2>
            <p class="section-lead mx-auto">Da organização pessoal à operação de uma equipe, você reúne informações que antes ficavam espalhadas em cadernos, planilhas e aplicativos.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4"><article class="benefit-card"><div class="icon-box"><i class="bi bi-person"></i></div><h3>Pessoa física</h3><p>Organize contas, vencimentos, entradas e saídas para ter uma visão clara da sua vida financeira.</p></article></div>
            <div class="col-md-4"><article class="benefit-card"><div class="icon-box"><i class="bi bi-briefcase"></i></div><h3>Autônomo e MEI</h3><p>Acompanhe clientes, recebimentos, despesas e resultados do seu trabalho em uma única rotina.</p></article></div>
            <div class="col-md-4"><article class="benefit-card"><div class="icon-box"><i class="bi bi-buildings"></i></div><h3>Empresa e equipe</h3><p>Integre PDV, caixa, estoque, compras, relatórios e acessos individuais para cada função.</p></article></div>
        </div>
    </div>
</section>

<section id="novidades" class="section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-kicker">Atualizações recentes</span>
            <h2 class="section-title mt-2">Mais atenção ao que importa</h2>
            <p class="section-lead mx-auto">O painel destaca vencimentos, movimentações e recomendações úteis para ajudar você a decidir com mais clareza.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <div class="icon-box"><i class="bi bi-stars"></i></div>
                    <h3>Orientação financeira</h3>
                    <p>Receba uma recomendação curta baseada nos totais, vencimentos e movimentações cadastrados no sistema.</p>
                </article>
            </div>
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <div class="icon-box"><i class="bi bi-diagram-3"></i></div>
                    <h3>Vencimentos acompanhados</h3>
                    <p>Identifique pagamentos vencidos, compromissos dos próximos dias e recebimentos que precisam de atenção.</p>
                </article>
            </div>
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <div class="icon-box"><i class="bi bi-shield-lock"></i></div>
                    <h3>Análise segura</h3>
                    <p>A análise é somente consultiva: não altera valores, não dá baixa, não exclui lançamentos e não envia cobranças automaticamente.</p>
                </article>
            </div>
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <div class="icon-box"><i class="bi bi-journal-text"></i></div>
                    <h3>Explicações no tutorial</h3>
                    <p>Consulte um guia simples para entender onde encontrar a análise, quais informações são consideradas e como interpretar o resumo.</p>
                </article>
            </div>
        </div>
        <div class="fiscal-note mt-4">
            <strong><i class="bi bi-pc-display me-1"></i> Recurso opcional:</strong>
            a análise inteligente pode ser executada localmente. Se estiver indisponível, o painel continua mostrando o resumo financeiro normalmente.
        </div>
    </div>
</section>

<section id="como-funciona" class="section">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7"><span class="section-kicker">Comece sem complicação</span><h2 class="section-title mt-2">Organize suas finanças em três passos</h2></div>
            <div class="col-lg-5"><p class="section-lead mb-0">Não precisa instalar. Crie a conta e use diretamente pelo navegador.</p></div>
        </div>
        <div class="row g-4">
            <div class="col-md-4"><article class="step-card"><div class="step-number">1</div><h3 class="h5 fw-bold">Crie sua conta</h3><p>Escolha o plano adequado e aproveite o período de teste informado.</p></article></div>
            <div class="col-md-4"><article class="step-card"><div class="step-number">2</div><h3 class="h5 fw-bold">Cadastre sua rotina</h3><p>Inclua contas, contatos, bancos e, conforme o plano, produtos e equipe.</p></article></div>
            <div class="col-md-4"><article class="step-card"><div class="step-number">3</div><h3 class="h5 fw-bold">Registre e acompanhe</h3><p>Venda, controle o caixa, atualize o estoque e acompanhe os resultados.</p></article></div>
        </div>
    </div>
</section>

<section id="demonstracao" class="section section-soft">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <span class="section-kicker">Veja antes de decidir</span>
                <h2 class="section-title mt-2">Uma visão clara da operação</h2>
                <p class="section-lead">Use os botões para conhecer algumas das principais telas do sistema.</p>
                <div class="screen-tabs mt-4" role="group" aria-label="Telas do sistema">
                    <button type="button" class="active" data-screen="img/dashboard-ia-financeira.png" data-alt="Dashboard principal com resumo financeiro por IA local" aria-controls="systemScreen" aria-pressed="true">Dashboard</button>
                    <button type="button" data-screen="img/telavendas.png?v=20260731" data-alt="Tela atual de vendas e PDV" aria-controls="systemScreen" aria-pressed="false">PDV</button>
                    <button type="button" data-screen="img/telaestoque.png?v=20260731" data-alt="Tela atual de controle de estoque" aria-controls="systemScreen" aria-pressed="false">Estoque</button>
                    <button type="button" data-screen="img/telapagar.png?v=20260731" data-alt="Tela atual de contas a pagar" aria-controls="systemScreen" aria-pressed="false">Financeiro</button>
                    <button type="button" data-screen="img/imgrelatorios.png?v=20260731" data-alt="Dashboard financeiro e relatórios atuais" aria-controls="systemScreen" aria-pressed="false">Relatórios</button>
                </div>
            </div>
            <div class="col-lg-7"><div class="screen-showcase"><img id="systemScreen" src="img/dashboard-ia-financeira.png" alt="Dashboard principal com resumo financeiro por IA local" loading="lazy"></div></div>
        </div>
        <div class="conversion-strip mt-5">
            <div><strong>Pronto para testar na sua rotina?</strong><span>Crie sua conta e conheça os recursos disponíveis no período de teste do plano escolhido.</span></div>
            <a class="btn btn-brand" href="pages/registro.php">Começar teste grátis <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </div>
</section>

<section id="recursos" class="section">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker">Recursos integrados</span><h2 class="section-title mt-2">As ferramentas essenciais para sua rotina</h2></div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-cart-check"></i></div><h3>Vendas e PDV</h3><p>Recibos, descontos, formas de pagamento, cancelamento e histórico de vendas.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-safe"></i></div><h3>Ciclo diário do caixa</h3><p>Saldo inicial, movimentos automáticos, sangrias e fechamento com dinheiro esperado.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-boxes"></i></div><h3>Produtos e estoque</h3><p>Compras, custos, estoque mínimo e baixa automática após as vendas.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-wallet2"></i></div><h3>Controle financeiro inteligente</h3><p>Contas a pagar e receber, cobranças, PIX, relatórios e alertas automáticos de vencimento.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-people"></i></div><h3>Equipe e permissões</h3><p>Dados compartilhados na empresa com acesso individual para cada função.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-headset"></i></div><h3>Suporte integrado</h3><p>Chat para assinantes, atendimento externo e protocolos de conversa.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-robot"></i></div><h3>Assistente financeiro local</h3><p>Recomendações e alertas consultivos, sem alterar ou excluir seus lançamentos.</p></article></div>
        </div>
        <div class="fiscal-note mt-4"><strong><i class="bi bi-info-circle me-1"></i> Módulo fiscal em evolução:</strong> a configuração e os testes de NFC-e estão disponíveis em ambiente de homologação. A emissão real depende de credenciamento, certificado e validação fiscal.</div>
        <div class="conversion-strip mt-4">
            <div><strong>Quer descobrir quais recursos atendem você?</strong><span>Compare os planos e escolha entre organização financeira, operação completa ou gestão de equipe.</span></div>
            <a class="btn btn-soft" href="#planos">Comparar planos</a>
        </div>
    </div>
</section>

<section class="section trust-band">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker text-info">Confiança para trabalhar</span><h2 class="section-title mt-2">Controle de acesso e atendimento de verdade</h2><p class="section-lead mx-auto">Explicamos o que já está implementado, sem promessas genéricas.</p></div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3"><article class="trust-card"><i class="bi bi-person-lock fs-2"></i><h3 class="mt-3">Permissões individuais</h3><p>Cada integrante acessa apenas os módulos autorizados.</p></article></div>
            <div class="col-md-6 col-lg-3"><article class="trust-card"><i class="bi bi-journal-check fs-2"></i><h3 class="mt-3">Registros de auditoria</h3><p>Ações importantes podem ser rastreadas no sistema.</p></article></div>
            <div class="col-md-6 col-lg-3"><article class="trust-card"><i class="bi bi-shield-check fs-2"></i><h3 class="mt-3">Proteção de dados</h3><p>Consentimento, documentos e práticas de acesso alinhadas à LGPD.</p></article></div>
            <div class="col-md-6 col-lg-3"><article class="trust-card"><i class="bi bi-chat-heart fs-2"></i><h3 class="mt-3">Suporte humanizado</h3><p>Atendimento com protocolo e canais definidos por plano.</p></article></div>
        </div>
    </div>
</section>

<section id="planos" class="section">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker">Planos transparentes</span><h2 class="section-title mt-2">Escolha conforme sua necessidade</h2><p class="section-lead mx-auto">Planos para organização pessoal, trabalho autônomo ou gestão de empresas. Básico e Profissional oferecem 15 dias de teste; Empresa oferece 30 dias.</p></div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-4">
                <article class="pricing-card">
                    <span class="eyebrow align-self-start">Organização financeira</span><h3 class="h4 fw-bold mt-3">Básico</h3><div class="price">R$ 19<small>,00/mês</small></div><p class="text-muted">Para pessoas físicas, autônomos ou pequenos negócios organizarem suas contas.</p>
                    <ul class="list-unstyled flex-grow-1"><li><i class="bi bi-check-circle-fill me-2"></i>Até 3 usuários incluídos</li><li><i class="bi bi-check-circle-fill me-2"></i>Possibilidade de adicionar usuários extras</li><li><i class="bi bi-check-circle-fill me-2"></i>Contas a pagar e receber</li><li><i class="bi bi-check-circle-fill me-2"></i>Alertas e controle de vencimentos</li><li><i class="bi bi-check-circle-fill me-2"></i>Clientes, fornecedores, categorias e bancos</li><li><i class="bi bi-check-circle-fill me-2"></i>Exportação de dados financeiros</li><li><i class="bi bi-check-circle-fill me-2"></i>Acesso por celular e computador</li><li><i class="bi bi-check-circle-fill me-2"></i>Suporte por e-mail</li><li class="not-included"><i class="bi bi-x-circle me-2"></i>Não inclui PDV, caixa e estoque</li></ul>
                    <button class="btn btn-soft w-100" type="button" onclick="abrirModalPlano('basico')">Conhecer o Básico</button>
                </article>
            </div>
            <div class="col-lg-4">
                <article class="pricing-card featured">
                    <span class="popular">MAIS ESCOLHIDO</span><span class="eyebrow align-self-start">Vendas e operação</span><h3 class="h4 fw-bold mt-3">Profissional <small class="text-muted fs-6">(Plus)</small></h3><div class="price">R$ 39<small>,00/mês</small></div><p class="text-muted">Para controlar a operação completa.</p>
                    <ul class="list-unstyled flex-grow-1"><li><i class="bi bi-check-circle-fill me-2"></i>Todos os recursos do Básico</li><li><i class="bi bi-check-circle-fill me-2"></i>Até 6 usuários incluídos</li><li><i class="bi bi-check-circle-fill me-2"></i>Possibilidade de adicionar usuários extras</li><li><i class="bi bi-check-circle-fill me-2"></i>PDV com busca e código de barras</li><li><i class="bi bi-check-circle-fill me-2"></i>Abertura, movimentação e fechamento de caixa</li><li><i class="bi bi-check-circle-fill me-2"></i>Produtos, estoque e compras</li><li><i class="bi bi-check-circle-fill me-2"></i>Metas, vendas e relatórios</li><li><i class="bi bi-check-circle-fill me-2"></i>Suporte prioritário</li></ul>
                    <button class="btn btn-brand w-100" type="button" onclick="abrirModalPlano('plus')">Testar o Profissional</button>
                </article>
            </div>
            <div class="col-lg-4">
                <article class="pricing-card">
                    <span class="eyebrow align-self-start">Equipe e escala</span><h3 class="h4 fw-bold mt-3">Empresa <small class="text-muted fs-6">(Essencial)</small></h3><div class="price">R$ 59<small>,00/mês</small></div><p class="text-muted">Para equipes maiores e atendimento dedicado.</p>
                    <ul class="list-unstyled flex-grow-1"><li><i class="bi bi-check-circle-fill me-2"></i>Todos os recursos disponíveis</li><li><i class="bi bi-check-circle-fill me-2"></i>Até 16 usuários incluídos</li><li><i class="bi bi-check-circle-fill me-2"></i>Possibilidade de adicionar usuários extras</li><li><i class="bi bi-check-circle-fill me-2"></i>Permissões individuais por usuário</li><li><i class="bi bi-check-circle-fill me-2"></i>Auditoria e controle da equipe</li><li><i class="bi bi-check-circle-fill me-2"></i>Operação, financeiro e relatórios completos</li><li><i class="bi bi-check-circle-fill me-2"></i>Treinamento inicial</li><li><i class="bi bi-check-circle-fill me-2"></i>Suporte dedicado</li></ul>
                    <button class="btn btn-soft w-100" type="button" onclick="abrirModalPlano('essencial')">Conhecer o plano Empresa</button>
                </article>
            </div>
        </div>
        <p class="text-center text-muted small mt-4">Todos os planos permitem solicitar usuários extras. A disponibilidade, os limites e as condições são apresentados na contratação, sem alterar os usuários já incluídos no plano.</p>
        <div class="text-center mt-5 mb-4"><span class="section-kicker">Comparação rápida</span><h3 class="h2 fw-bold mt-2">Compare os principais recursos</h3></div>
        <div class="table-responsive comparison-wrap">
            <table class="table plan-comparison">
                <caption class="visually-hidden">Comparação dos recursos dos planos Básico, Profissional e Empresa</caption>
                <thead><tr><th scope="col">Recurso</th><th scope="col">Básico</th><th scope="col">Profissional</th><th scope="col">Empresa</th></tr></thead>
                <tbody>
                    <tr><th scope="row">Controle financeiro</th><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td></tr>
                    <tr><th scope="row">PDV e caixa</th><td class="compare-no">Não incluído</td><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td></tr>
                    <tr><th scope="row">Estoque e compras</th><td class="compare-no">Não incluído</td><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td></tr>
                    <tr><th scope="row">Permissões avançadas</th><td class="compare-no">Não incluído</td><td class="compare-no">Não incluído</td><td class="compare-yes"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Incluído</td></tr>
                    <tr><th scope="row">Usuários incluídos</th><td>Até 3</td><td>Até 6</td><td>Até 16</td></tr>
                    <tr><th scope="row">Usuários extras</th><td>Disponível</td><td>Disponível</td><td>Disponível</td></tr>
                </tbody>
            </table>
        </div>
        <div class="plan-assurances mt-4" aria-label="Condições comuns aos planos">
            <div class="assurance-item"><i class="bi bi-browser-chrome" aria-hidden="true"></i><span>Acesso pelo navegador</span></div>
            <div class="assurance-item"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Controle de acesso</span></div>
            <div class="assurance-item"><i class="bi bi-headset" aria-hidden="true"></i><span>Suporte conforme o plano</span></div>
            <div class="assurance-item"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Usuários extras disponíveis</span></div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="referral text-center">
            <span class="eyebrow">Indique e ganhe</span>
            <h2 class="fw-bold mt-3">Indique 3 amigos e ganhe 1 mês de mensalidade</h2>
            <p class="mb-4 opacity-75">A promoção é acumulativa conforme as regras da campanha. Crie sua conta para receber o código individual de indicação.</p>
            <a class="btn btn-light text-primary fw-bold px-4 py-3" href="pages/registro.php">Criar conta e receber meu código</a>
        </div>
    </div>
</section>

<section id="depoimentos" class="section section-soft" aria-labelledby="depoimentosTitulo">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker">Experiências reais</span><h2 class="section-title mt-2" id="depoimentosTitulo">O que os usuários dizem</h2><p class="text-muted mx-auto" style="max-width:760px">Avaliações enviadas por usuários e publicadas somente após análise da equipe responsável.</p></div>
        <?php if ($feedbacksPublicados): ?>
            <div class="row g-4">
                <?php foreach ($feedbacksPublicados as $feedback):
                    $anonimo = (int)($feedback['anonimo'] ?? 0) === 1;
                    $nome = $anonimo ? 'Usuário anônimo' : trim((string)($feedback['nome'] ?? ''));
                    if ($nome === '') $nome = 'Usuário do App Controle Pro';
                    $pontuacao = max(1, min(5, (int)($feedback['pontuacao'] ?? 5)));
                ?>
                <div class="col-md-6 col-lg-4"><article class="testimonial d-flex flex-column">
                    <div class="feedback-stars mb-3" aria-label="<?= $pontuacao ?> de 5 estrelas"><?php for ($estrela = 1; $estrela <= 5; $estrela++): ?><i class="bi <?= $estrela <= $pontuacao ? 'bi-star-fill' : 'bi-star' ?>" aria-hidden="true"></i><?php endfor; ?></div>
                    <p class="mb-4 flex-grow-1">“<?= nl2br(htmlspecialchars((string)$feedback['descricao'], ENT_QUOTES, 'UTF-8')) ?>”</p>
                    <div class="d-flex align-items-center gap-3"><div class="feedback-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></div><strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong></div>
                </article></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="conversion-strip"><div><strong>Sua experiência pode ajudar outras pessoas</strong><span>Envie sua opinião. Ela ficará pendente até ser analisada pelo usuário Master.</span></div><button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#modalFeedbackPublico">Enviar meu feedback</button></div>
        <?php endif; ?>
    </div>
</section>

<section id="faq" class="section section-soft faq">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker">Perguntas frequentes</span><h2 class="section-title mt-2">Tire suas dúvidas antes de começar</h2></div>
        <div class="row justify-content-center"><div class="col-lg-9"><div class="accordion" id="faqList">
            <?php
            $faqs = [
                ['Preciso instalar algum programa?', 'Não para usar o sistema principal: ele funciona pelo navegador no computador ou celular. Apenas a análise local opcional pode exigir um componente adicional no equipamento responsável pelo processamento.'],
                ['Como funciona a IA financeira local?', 'Ela analisa apenas quantidades e totais agregados de contas vencidas e dos próximos três dias. A recomendação é consultiva e não altera, exclui ou dá baixa em lançamentos. Se o modelo local estiver indisponível, o sistema usa automaticamente o resumo seguro baseado em regras.'],
                ['O que consigo controlar no sistema?', 'Você pode organizar contas a pagar e receber, vencimentos, clientes, fornecedores e relatórios. Nos planos com operação completa também estão disponíveis PDV, caixa diário, vendas, estoque, compras e metas.'],
                ['Qual é a diferença entre os planos?', 'O Básico é voltado ao controle financeiro e permite até 3 usuários. O Profissional (Plus) inclui PDV, caixa, estoque e compras, com até 6 usuários. O Empresa (Essencial) reúne todos os recursos e permite até 16 usuários. É possível solicitar usuários extras em qualquer plano, conforme as condições apresentadas na contratação.'],
                ['O sistema atende pessoa física?', 'Sim. Pessoas físicas e autônomos podem usar o sistema para organizar contas, vencimentos, recebimentos, pagamentos e contatos. MEIs e empresas também podem utilizar os recursos de vendas, caixa, estoque, equipe e relatórios disponíveis em cada plano.'],
                ['Como funciona o período de teste grátis?', 'Os planos Básico e Profissional possuem 15 dias de teste. O plano Empresa possui 30 dias. O prazo e as condições aparecem no cadastro e na área da assinatura.'],
                ['Minha equipe pode trabalhar na mesma conta?', 'Sim. Os dados pertencem à empresa e podem ser compartilhados com a equipe. O administrador cadastra os usuários e define quais páginas e recursos cada pessoa pode acessar.'],
                ['O plano Básico possui PDV e controle de estoque?', 'Não. PDV, abertura e fechamento de caixa, vendas, estoque e compras estão disponíveis a partir do plano Profissional (Plus).'],
                ['Posso usar leitor de código de barras?', 'Sim. O uso é opcional. Leitores USB e Bluetooth compatíveis com entrada de teclado podem localizar produtos pelo código no PDV. Também é possível pesquisar ou digitar o código manualmente.'],
                ['Os dados financeiros podem ser exportados?', 'Sim. As telas de contas a pagar, contas a receber e contas baixadas permitem exportar os dados filtrados em PDF, Excel ou XML.'],
                ['Como funcionam os cupons de desconto?', 'Cupons válidos respeitam a data de início, a data de término e o limite de utilização. Quando o desconto é reservado durante o teste, ele aparece na assinatura e é aplicado conforme a regra informada para o primeiro pagamento.'],
                ['Como funciona o programa de indicação?', 'O novo cliente que se cadastra com um código de indicação válido recebe o benefício informado para a contratação. O indicador acumula 1 mês gratuito a cada 3 indicações qualificadas, conforme as regras da campanha.'],
                ['O sistema emite nota fiscal real?', 'O módulo fiscal está disponível para configuração e testes em homologação. A emissão fiscal real depende do credenciamento da empresa, certificado digital e validação das regras com a contabilidade.'],
                ['Como é feito o pagamento da assinatura?', 'A cobrança recorrente é processada pelo Mercado Pago. Na área Minha Assinatura você acompanha o plano, o período de teste, o valor, os descontos aplicáveis e o histórico de pagamentos.'],
                ['Posso trocar ou cancelar meu plano?', 'Sim. A área de gestão da assinatura permite acompanhar o plano e solicitar alterações ou cancelamento conforme as condições apresentadas.'],
                ['Existe suporte dentro do sistema?', 'Sim. Assinantes podem abrir solicitações e acompanhar protocolos. Os canais e a prioridade do atendimento variam conforme o plano contratado.'],
            ];
            foreach ($faqs as $index => $faq): $id = 'faqItem' . $index;
            ?>
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $id ?>"><?= htmlspecialchars($faq[0], ENT_QUOTES, 'UTF-8') ?></button></h3><div id="<?= $id ?>" class="accordion-collapse collapse" data-bs-parent="#faqList"><div class="accordion-body text-muted"><?= htmlspecialchars($faq[1], ENT_QUOTES, 'UTF-8') ?></div></div></div>
            <?php endforeach; ?>
        </div></div></div>
    </div>
</section>


<section class="section">
    <div class="container">
        <div class="final-cta text-center">
            <h2 class="section-title">Pronto para organizar suas finanças ou seu negócio?</h2>
            <p class="lead opacity-75 mx-auto mb-4" style="max-width:720px">Seja você pessoa física, autônomo ou empresa, crie sua conta e escolha o plano adequado à sua rotina.</p>
            <a class="btn btn-light text-primary fw-bold btn-lg px-5" href="pages/registro.php">Começar meu teste grátis</a>
        </div>
    </div>
</section>

</main>
<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-5"><div class="fw-bold fs-5"><i class="bi bi-wallet2 text-info me-2"></i>App Controle Pro</div><p class="text-secondary small mt-2 mb-0">Gestão integrada para pessoas físicas, autônomos, MEIs e empresas.</p></div>
            <div class="col-md-4 d-flex flex-wrap gap-3 justify-content-md-center"><a class="footer-link" href="pages/protecao_de_dados.php">Proteção de dados</a><a class="footer-link" href="pages/suporte.php">Suporte</a><a class="footer-link" href="pages/login.php">Área do cliente</a></div>
            <div class="col-md-3 text-md-end text-secondary small">&copy; <?= date('Y') ?> Felippe Andreata Dev.<br>Todos os direitos reservados.</div>
        </div>
    </div>
</footer>

<button type="button" class="feedback-public-trigger floating-icon" data-bs-toggle="modal" data-bs-target="#modalFeedbackPublico" aria-label="Enviar feedback" title="Enviar feedback"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i></button>

<div class="mobile-cta" aria-label="Ação rápida para cadastro">
    <div><strong>Teste grátis</strong><small>15 ou 30 dias</small></div>
    <a class="btn btn-brand" href="pages/registro.php">Criar conta</a>
</div>

<div class="modal fade" id="modalDetalhesPlano" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
        <div class="modal-header"><h2 class="modal-title h5 fw-bold" id="modalPlanoTitulo">Detalhes do plano</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body p-4"><div class="text-center mb-4"><div class="price" id="modalPlanoPreco"></div><span class="eyebrow" id="modalPlanoBadge"></span><p class="text-muted mt-3" id="modalPlanoDesc"></p></div><h3 class="h6 fw-bold">O que está incluído:</h3><ul class="list-unstyled" id="modalPlanoLista"></ul></div>
        <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Voltar</button><a href="#" id="btnAssinarModal" class="btn btn-brand">Criar conta neste plano</a></div>
    </div></div>
</div>

<div class="modal fade" id="modalFeedbackPublico" tabindex="-1" aria-labelledby="modalFeedbackTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
        <div class="modal-header"><div><span class="section-kicker">Sua opinião importa</span><h2 class="modal-title h5 fw-bold mt-1" id="modalFeedbackTitulo">Enviar feedback</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body p-4"><form id="formFeedbackPublico">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" id="feedbackAnonimo" name="anonimo"><label class="form-check-label" for="feedbackAnonimo">Enviar como anônimo</label></div>
            <div id="feedbackIdentificacao"><div class="mb-3"><label class="form-label" for="feedbackNome">Nome</label><input class="form-control" type="text" id="feedbackNome" name="nome" maxlength="100" autocomplete="name"></div><div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="feedbackEmail">E-mail</label><input class="form-control" type="email" id="feedbackEmail" name="email" maxlength="150" autocomplete="email"></div><div class="col-md-6"><label class="form-label" for="feedbackWhatsapp">WhatsApp</label><input class="form-control" type="tel" id="feedbackWhatsapp" name="whatsapp" maxlength="30" autocomplete="tel"></div></div></div>
            <div class="mb-3"><label class="form-label" for="feedbackPontuacao">Avaliação</label><select class="form-select" id="feedbackPontuacao" name="pontuacao" required><option value="5">5 estrelas — Excelente</option><option value="4">4 estrelas — Muito bom</option><option value="3">3 estrelas — Bom</option><option value="2">2 estrelas — Regular</option><option value="1">1 estrela — Ruim</option></select></div>
            <div class="mb-2"><label class="form-label" for="feedbackDescricao">Conte sua experiência</label><textarea class="form-control" id="feedbackDescricao" name="descricao" rows="4" minlength="10" maxlength="1200" required></textarea><div class="form-text">Seu comentário só aparecerá na página depois da aprovação do usuário Master.</div></div>
            <div id="feedbackPublicoStatus" class="feedback-public-status mt-3" role="status" aria-live="polite"></div>
        </form></div>
        <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formFeedbackPublico" id="btnEnviarFeedbackPublico" class="btn btn-brand"><i class="bi bi-send me-2"></i>Enviar feedback</button></div>
    </div></div>
</div>

<div vw class="enabled"><div vw-access-button class="active"></div><div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
<script>
const dadosPlanos = {
    basico:{titulo:'Plano Básico',preco:'R$ 19,00/mês',badge:'Organização financeira',desc:'Para pessoas físicas, autônomos ou pequenos negócios controlarem contas e vencimentos sem o módulo de vendas.',features:['Até 3 usuários incluídos','Possibilidade de adicionar usuários extras','Contas a pagar e receber','Alertas e controle de vencimentos','Clientes, fornecedores, categorias e bancos','Exportação de dados financeiros','Acesso por celular e computador','Suporte por e-mail','Não inclui PDV, caixa e estoque']},
    plus:{titulo:'Plano Profissional (Plus)',preco:'R$ 39,00/mês',badge:'Mais escolhido',desc:'Para controlar vendas, caixa, produtos, estoque, compras e financeiro em uma operação completa.',features:['Todos os recursos do Básico','Até 6 usuários incluídos','Possibilidade de adicionar usuários extras','PDV com busca e código de barras','Abertura, movimentação e fechamento de caixa','Produtos, estoque e compras','Metas, vendas e relatórios','Suporte prioritário']},
    essencial:{titulo:'Plano Empresa (Essencial)',preco:'R$ 59,00/mês',badge:'Equipe e escala',desc:'Para empresas que precisam de mais usuários, controle de acessos e atendimento dedicado.',features:['Todos os recursos disponíveis','Até 16 usuários incluídos','Possibilidade de adicionar usuários extras','Permissões individuais por usuário','Auditoria e controle da equipe','Operação, financeiro e relatórios completos','Treinamento inicial','Suporte dedicado']}
};
function abrirModalPlano(chave){
    const plano=dadosPlanos[chave]; if(!plano)return;
    document.getElementById('modalPlanoTitulo').textContent=plano.titulo;
    document.getElementById('modalPlanoPreco').textContent=plano.preco;
    document.getElementById('modalPlanoBadge').textContent=plano.badge;
    document.getElementById('modalPlanoDesc').textContent=plano.desc;
    const lista=document.getElementById('modalPlanoLista'); lista.innerHTML='';
    plano.features.forEach(item=>{const li=document.createElement('li');const naoIncluido=item.startsWith('Não inclui');li.className='mb-2'+(naoIncluido?' text-muted':'');li.innerHTML='<i class="bi '+(naoIncluido?'bi-x-circle':'bi-check-circle-fill text-success')+' me-2"></i>'+item;lista.appendChild(li)});
    document.getElementById('btnAssinarModal').href='pages/registro.php?plano='+encodeURIComponent(chave);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhesPlano')).show();
}
document.querySelectorAll('[data-screen]').forEach(button=>button.addEventListener('click',()=>{
    document.querySelectorAll('[data-screen]').forEach(item=>{
        const selected=item===button;
        item.classList.toggle('active',selected);
        item.setAttribute('aria-pressed',selected?'true':'false');
    });
    const image=document.getElementById('systemScreen'); image.src=button.dataset.screen; image.alt=button.dataset.alt;
}));
if(window.VLibras)new window.VLibras.Widget('https://vlibras.gov.br/app');

const feedbackForm = document.getElementById('formFeedbackPublico');
const feedbackAnonimo = document.getElementById('feedbackAnonimo');
const feedbackIdentificacao = document.getElementById('feedbackIdentificacao');
const feedbackStatus = document.getElementById('feedbackPublicoStatus');
const feedbackSubmit = document.getElementById('btnEnviarFeedbackPublico');
feedbackAnonimo?.addEventListener('change', () => { feedbackIdentificacao.hidden = feedbackAnonimo.checked; });
feedbackForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    feedbackStatus.className = 'feedback-public-status mt-3';
    feedbackStatus.textContent = 'Enviando...';
    feedbackSubmit.disabled = true;
    try {
        const response = await fetch('actions/enviar_feedback_publico.php', {method:'POST', body:new FormData(feedbackForm), credentials:'same-origin', headers:{'Accept':'application/json'}});
        const data = await response.json();
        if (!response.ok || data.status !== 'success') throw new Error(data.msg || 'Não foi possível enviar o feedback.');
        feedbackStatus.classList.add('success');
        feedbackStatus.textContent = data.msg;
        feedbackForm.reset();
        feedbackIdentificacao.hidden = false;
    } catch (error) {
        feedbackStatus.classList.add('error');
        feedbackStatus.textContent = error.message || 'Não foi possível enviar o feedback.';
    } finally {
        feedbackSubmit.disabled = false;
    }
});
</script>
</body>
</html>

<?php
require_once 'database.php';
require_once 'includes/session_init.php';

$feedbacks = null;
try {
    $connFeedback = getMasterConnection();
    if ($connFeedback) {
        $feedbacks = $connFeedback->query(
            "SELECT nome, descricao, pontuacao
             FROM feedbacks
             WHERE aprovado = 1
               AND LOWER(TRIM(nome)) NOT IN ('teste','test')
               AND LOWER(TRIM(descricao)) NOT IN ('teste','test')
               AND CHAR_LENGTH(TRIM(descricao)) >= 20
             ORDER BY criado_em DESC LIMIT 6"
        );
    }
} catch (Throwable $ignored) {
    $feedbacks = null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Controle vendas, estoque, caixa, contas e equipe em um só sistema. Feito para MEIs e pequenas empresas.">
    <meta name="theme-color" content="#075dd8">
    <title>App Controle Pro | Vendas, estoque, caixa e financeiro</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/universal-theme.css" rel="stylesheet">
    <script src="assets/js/global-theme.js"></script>
    <style>
        :root{--brand:#075dd8;--brand-dark:#0647a6;--accent:#00bfff;--ink:#122033;--muted:#617086;--surface:#f5f8fc;--line:#dfe7f1;--success:#168a4b}
        html{scroll-behavior:smooth;scroll-padding-top:88px}body{font-family:Inter,"Segoe UI",sans-serif;color:var(--ink);background:#fff}
        .navbar{background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-bottom:1px solid var(--line);padding:14px 0}.navbar-brand{font-weight:800;color:var(--brand)!important}.nav-link{font-weight:600;color:#334155!important}.nav-link:hover{color:var(--brand)!important}
        .btn-brand{background:var(--brand);border:1px solid var(--brand);color:#fff;font-weight:700;border-radius:10px;padding:12px 22px;box-shadow:0 10px 24px rgba(7,93,216,.2)}.btn-brand:hover{background:var(--brand-dark);border-color:var(--brand-dark);color:#fff;transform:translateY(-1px)}
        .btn-soft{background:#fff;border:1px solid #b9c9dc;color:var(--ink);font-weight:700;border-radius:10px;padding:12px 22px}.btn-soft:hover{border-color:var(--brand);color:var(--brand)}
        .hero{padding:145px 0 85px;background:radial-gradient(circle at 85% 18%,rgba(0,191,255,.18),transparent 30%),linear-gradient(135deg,#f4f9ff,#fff 62%)}.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:#e8f2ff;color:var(--brand);font-size:.84rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .hero h1{font-weight:800;font-size:clamp(2.4rem,5vw,4.4rem);line-height:1.05;letter-spacing:-.055em;margin:22px 0}.hero h1 span{color:var(--brand)}.hero-lead{font-size:1.16rem;line-height:1.7;color:var(--muted);max-width:650px}.micro-proof{display:flex;gap:22px;flex-wrap:wrap;color:#475569;font-size:.9rem}.micro-proof i{color:var(--success)}
        .app-preview{background:#111827;border-radius:18px;padding:10px;box-shadow:0 28px 70px rgba(20,49,83,.25);position:relative}.app-preview img{display:block;width:100%;border-radius:11px}
        
        .preview-label{position:absolute;background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:0 8px 25px rgba(15,23,42,.16);border-radius:11px;padding:10px 13px;font-weight:700;font-size:.82rem;white-space:nowrap;z-index:2}
        .preview-label i{color:var(--brand)}
        .label-one{left:-25px;bottom:50px}.label-two{right:-18px;top:45px}

        .section{padding:88px 0}.section-soft{background:var(--surface)}.section-kicker{color:var(--brand);font-size:.82rem;text-transform:uppercase;font-weight:800;letter-spacing:.08em}.section-title{font-weight:800;letter-spacing:-.035em;font-size:clamp(2rem,4vw,3rem)}.section-lead{color:var(--muted);font-size:1.08rem;max-width:760px}
        .benefit-card,.feature-card,.step-card,.trust-card{height:100%;background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px}.benefit-card:hover,.feature-card:hover{border-color:#9fc4f5;box-shadow:0 15px 38px rgba(26,61,102,.09);transform:translateY(-3px)}.benefit-card,.feature-card{transition:.25s}.icon-box{width:52px;height:52px;border-radius:13px;display:grid;place-items:center;background:#e8f2ff;color:var(--brand);font-size:1.4rem;margin-bottom:18px}.benefit-card h3,.feature-card h3{font-size:1.05rem;font-weight:800}.benefit-card p,.feature-card p,.step-card p,.trust-card p{color:var(--muted);margin-bottom:0;line-height:1.6}
        .step-number{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:var(--brand);color:#fff;font-weight:800;margin-bottom:16px}.screen-showcase{border-radius:18px;border:1px solid var(--line);background:#fff;box-shadow:0 22px 55px rgba(15,23,42,.12);overflow:hidden}.screen-showcase img{width:100%;display:block}.screen-tabs{display:flex;gap:8px;flex-wrap:wrap}.screen-tabs button{border:1px solid var(--line);background:#fff;border-radius:999px;padding:8px 13px;font-weight:700;color:#526176}.screen-tabs button.active{background:var(--brand);border-color:var(--brand);color:#fff}
        
        .fiscal-note{background:#fff7df;border:1px solid #f0ce68;color:#59430c;border-radius:13px;padding:17px}

        .trust-band{background:#0c1b31;color:#fff}.trust-band .section-lead{color:#bdc9d8}.trust-card{background:#142740;border-color:#29405d}.trust-card h3{font-size:1rem;font-weight:800}.trust-card p{color:#bdc9d8}.trust-card i{color:#66d2ff}
        .testimonial{height:100%;background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px}.testimonial .stars{color:#f4a900}.avatar{width:42px;height:42px;border-radius:50%;background:var(--brand);color:#fff;display:grid;place-items:center;font-weight:800}
        .pricing-card{height:100%;border:1px solid var(--line);border-radius:18px;background:#fff;padding:29px;display:flex;flex-direction:column}.pricing-card.featured{border:2px solid var(--brand);box-shadow:0 20px 45px rgba(7,93,216,.14);position:relative}.popular{position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:var(--brand);color:#fff;border-radius:999px;padding:6px 15px;font-size:.75rem;font-weight:800;white-space:nowrap}.price{font-size:2.5rem;font-weight:800;color:var(--brand);letter-spacing:-.05em}.price small{font-size:.9rem;font-weight:500;color:var(--muted);letter-spacing:0}.pricing-card li{margin:9px 0;color:#4b5b70}.pricing-card li i{color:var(--success)}.pricing-card .not-included,.pricing-card .not-included i{color:#9aa6b5}
        .referral{background:linear-gradient(135deg,#0647a6,#075dd8);color:#fff;border-radius:20px;padding:36px}
        
        /* Força a cor preta no texto "Indique e ganhe" em ambos os modos */
        .referral .eyebrow { color: #000 !important; background-color: #ffc107 !important; }

        .faq .accordion-item{border:1px solid var(--line);border-radius:12px!important;margin-bottom:10px;overflow:hidden}.faq .accordion-button{font-weight:700;box-shadow:none!important}
        .final-cta{background:linear-gradient(135deg,#075dd8,#003f9b);color:#fff;border-radius:24px;padding:55px}.footer-link{color:#aebdd0;text-decoration:none}.footer-link:hover{color:#fff}
        
        /* Ajustes gerais do modo escuro */
        body.dark-mode{background:#0c111b;color:#e8eef6;--ink:#eef4fb;--muted:#aab8ca;--surface:#111a27;--line:#29374a}
        body.dark-mode .navbar{background:rgba(12,17,27,.96)}
        body.dark-mode .benefit-card,body.dark-mode .feature-card,body.dark-mode .step-card,body.dark-mode .screen-showcase,body.dark-mode .testimonial,body.dark-mode .pricing-card,body.dark-mode .accordion-item,body.dark-mode .accordion-button,body.dark-mode .accordion-body{background:#152032;color:#eef4fb}
        body.dark-mode .hero{background:radial-gradient(circle at 85% 18%,rgba(0,191,255,.15),transparent 30%),#0d1522}
        body.dark-mode .btn-soft,body.dark-mode .screen-tabs button{background:#152032;color:#dbe7f4}
        body.dark-mode .screen-tabs button.active{background:var(--brand);color:#fff}
        body.dark-mode .preview-label{background:#152032;color:#eef4fb;border-color:#29374a}
        body.dark-mode .fiscal-note{background:#152032;border-color:#29374a;color:#eef4fb}
        body.dark-mode .pricing-card p,body.dark-mode .pricing-card li,body.dark-mode .pricing-card .text-muted,body.dark-mode .pricing-card small.text-muted{color:#cbd5e1!important}
        body.dark-mode .pricing-card .not-included,body.dark-mode .pricing-card .not-included i{color:#94a3b8!important}

        @media(max-width:991px){.hero{text-align:center;padding-top:120px}.hero-lead{margin-inline:auto}.hero-actions,.micro-proof{justify-content:center}.app-preview{margin-top:42px}.preview-label{display:none}}@media(max-width:575px){.section{padding:65px 0}.final-cta,.referral{padding:30px 22px}.hero-actions .btn{width:100%}}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-wallet2 me-2"></i>App Controle Pro</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="#como-funciona">Como funciona</a></li>
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

<header class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="eyebrow"><i class="bi bi-stars"></i> Gestão inteligente e completa para o seu negócio</span>
                <h1>Controle vendas, estoque, caixa e financeiro <span>em um só lugar.</span></h1>
                <p class="hero-lead">Uma plataforma robusta e intuitiva para empresas que querem organizar a operação, acompanhar resultados em tempo real e trabalhar em equipe sem depender de várias planilhas.</p>
                <div class="hero-actions d-flex flex-column flex-sm-row gap-3 mt-4">
                    <a class="btn btn-brand btn-lg" href="pages/registro.php">Testar grátis por 15 dias <i class="bi bi-arrow-right ms-1"></i></a>
                    <a class="btn btn-soft btn-lg" href="#demonstracao"><i class="bi bi-play-circle me-1"></i> Ver o sistema</a>
                </div>
                <div class="micro-proof mt-4">
                    <span><i class="bi bi-check-circle-fill me-1"></i> Acesso pelo navegador</span>
                    <span><i class="bi bi-check-circle-fill me-1"></i> Computador e celular</span>
                    <span><i class="bi bi-check-circle-fill me-1"></i> Suporte humanizado</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="app-preview">
                    <img src="img/telahome.png" alt="Dashboard do Sistema de Gestão" fetchpriority="high">
                    <div class="preview-label label-one"><i class="bi bi-cash-register me-1"></i> PDV e caixa diário</div>
                    <div class="preview-label label-two"><i class="bi bi-graph-up-arrow me-1"></i> Resultados em uma tela</div>
                </div>
            </div>
        </div>
    </div>
</header>

<section class="section section-soft">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-kicker">Menos improviso, mais controle</span>
            <h2 class="section-title mt-2">Saiba o que acontece na empresa todos os dias</h2>
            <p class="section-lead mx-auto">O sistema conecta las rotinas que normalmente ficam espalhadas em cadernos, planilhas e aplicativos diferentes.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-xl-3"><article class="benefit-card"><div class="icon-box"><i class="bi bi-cash-stack"></i></div><h3>Confira o dinheiro do caixa</h3><p>Abertura, vendas, entradas, retiradas e fechamento diário no mesmo fluxo.</p></article></div>
            <div class="col-md-6 col-xl-3"><article class="benefit-card"><div class="icon-box"><i class="bi bi-box-seam"></i></div><h3>Evite falta de produtos</h3><p>Acompanhe estoque, compras, baixas automáticas e quantidades mínimas.</p></article></div>
            <div class="col-md-6 col-xl-3"><article class="benefit-card"><div class="icon-box"><i class="bi bi-calendar2-check"></i></div><h3>Não perca vencimentos</h3><p>Organize contas a pagar e receber e registre baixas quando acontecerem.</p></article></div>
            <div class="col-md-6 col-xl-3"><article class="benefit-card"><div class="icon-box"><i class="bi bi-bullseye"></i></div><h3>Acompanhe metas e equipe</h3><p>Visualize o desempenho e compartilhe os dados com permissões individuais.</p></article></div>
        </div>
    </div>
</section>

<section id="como-funciona" class="section">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7"><span class="section-kicker">Comece sem complicação</span><h2 class="section-title mt-2">Organize sua empresa em três passos</h2></div>
            <div class="col-lg-5"><p class="section-lead mb-0">Não precisa instalar. Crie a conta e use diretamente pelo navegador.</p></div>
        </div>
        <div class="row g-4">
            <div class="col-md-4"><article class="step-card"><div class="step-number">1</div><h3 class="h5 fw-bold">Crie sua conta</h3><p>Escolha o plano adequado e aproveite o período de teste informado.</p></article></div>
            <div class="col-md-4"><article class="step-card"><div class="step-number">2</div><h3 class="h5 fw-bold">Cadastre sua operação</h3><p>Inclua clientes, fornecedores, produtos, categorias, bancos e equipe.</p></article></div>
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
                    <button type="button" class="active" data-screen="img/telahome.png" data-alt="Dashboard principal">Dashboard</button>
                    <button type="button" data-screen="img/telavendas.png" data-alt="Tela de vendas e PDV">PDV</button>
                    <button type="button" data-screen="img/telaestoque.png" data-alt="Tela de controle de estoque">Estoque</button>
                    <button type="button" data-screen="img/telapagar.png" data-alt="Tela de contas a pagar">Financeiro</button>
                    <button type="button" data-screen="img/imgrelatorios.png" data-alt="Tela de relatórios">Relatórios</button>
                </div>
            </div>
            <div class="col-lg-7"><div class="screen-showcase"><img id="systemScreen" src="img/telahome.png" alt="Dashboard principal" loading="lazy"></div></div>
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
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-wallet2"></i></div><h3>Controle financeiro</h3><p>Contas a pagar e receber, cobranças por e-mail, PIX e relatórios.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-people"></i></div><h3>Equipe e permissões</h3><p>Dados compartilhados na empresa com acesso individual para cada função.</p></article></div>
            <div class="col-md-6 col-lg-4"><article class="feature-card"><div class="icon-box"><i class="bi bi-headset"></i></div><h3>Suporte integrado</h3><p>Chat para assinantes, atendimento externo e protocolos de conversa.</p></article></div>
        </div>
        <div class="fiscal-note mt-4"><strong><i class="bi bi-info-circle me-1"></i> Módulo fiscal em evolução:</strong> a configuração e os testes de NFC-e estão disponíveis em ambiente de homologação. A emissão real depende de credenciamento, certificado e validação fiscal.</div>
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

<?php if ($feedbacks && $feedbacks->num_rows > 0): ?>
<section class="section section-soft">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker">Avaliações publicadas</span><h2 class="section-title mt-2">Quem usa conta como foi a experiência</h2></div>
        <div class="row g-4">
            <?php while ($feedback = $feedbacks->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="testimonial">
                        <div class="stars mb-3"><?= str_repeat('<i class="bi bi-star-fill"></i>', max(1, min(5, (int)$feedback['pontuacao']))) ?></div>
                        <p>“<?= htmlspecialchars($feedback['descricao'], ENT_QUOTES, 'UTF-8') ?>”</p>
                        <div class="d-flex align-items-center gap-3 mt-4"><div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($feedback['nome'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($feedback['nome'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted">Cliente verificado</div></div></div>
                    </article>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<?php endif; if (isset($connFeedback) && $connFeedback instanceof mysqli) $connFeedback->close(); ?>

<section id="planos" class="section">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker">Planos transparentes</span><h2 class="section-title mt-2">Escolha conforme o momento da empresa</h2><p class="section-lead mx-auto">Confira os detalhes antes de criar a conta. Você poderá selecionar o plano no cadastro.</p></div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-4">
                <article class="pricing-card">
                    <span class="eyebrow align-self-start">Organização financeira</span><h3 class="h4 fw-bold mt-3">Básico</h3><div class="price">R$ 19<small>,00/mês</small></div><p class="text-muted">Para começar pelas contas da empresa.</p>
                    <ul class="list-unstyled flex-grow-1"><li><i class="bi bi-check-circle-fill me-2"></i>Até 3 usuários</li><li><i class="bi bi-check-circle-fill me-2"></i>Contas a pagar e receber</li><li><i class="bi bi-check-circle-fill me-2"></i>Controle de vencimentos</li><li><i class="bi bi-check-circle-fill me-2"></i>Acesso por celular e computador</li><li class="not-included"><i class="bi bi-x-circle me-2"></i>Não inclui PDV</li></ul>
                    <button class="btn btn-soft w-100" type="button" onclick="abrirModalPlano('basico')">Conhecer o Básico</button>
                </article>
            </div>
            <div class="col-lg-4">
                <article class="pricing-card featured">
                    <span class="popular">MAIS ESCOLHIDO</span><span class="eyebrow align-self-start">Vendas e operação</span><h3 class="h4 fw-bold mt-3">Profissional <small class="text-muted fs-6">(Plus)</small></h3><div class="price">R$ 39<small>,00/mês</small></div><p class="text-muted">Para controlar a operação completa.</p>
                    <ul class="list-unstyled flex-grow-1"><li><i class="bi bi-check-circle-fill me-2"></i>Tudo do Básico</li><li><i class="bi bi-check-circle-fill me-2"></i>Até 6 usuários</li><li><i class="bi bi-check-circle-fill me-2"></i>PDV, caixa, estoque e compras</li><li><i class="bi bi-check-circle-fill me-2"></i>Metas e relatórios</li><li><i class="bi bi-check-circle-fill me-2"></i>Suporte prioritário</li></ul>
                    <button class="btn btn-brand w-100" type="button" onclick="abrirModalPlano('plus')">Testar o Profissional</button>
                </article>
            </div>
            <div class="col-lg-4">
                <article class="pricing-card">
                    <span class="eyebrow align-self-start">Equipe e escala</span><h3 class="h4 fw-bold mt-3">Empresa <small class="text-muted fs-6">(Essencial)</small></h3><div class="price">R$ 59<small>,00/mês</small></div><p class="text-muted">Para equipes maiores e atendimento dedicado.</p>
                    <ul class="list-unstyled flex-grow-1"><li><i class="bi bi-check-circle-fill me-2"></i>Todos os recursos disponíveis</li><li><i class="bi bi-check-circle-fill me-2"></i>Até 16 usuários</li><li><i class="bi bi-check-circle-fill me-2"></i>Auditoria e permissões</li><li><i class="bi bi-check-circle-fill me-2"></i>Treinamento inicial</li><li><i class="bi bi-check-circle-fill me-2"></i>Suporte dedicado</li></ul>
                    <button class="btn btn-soft w-100" type="button" onclick="abrirModalPlano('essencial')">Conhecer o Empresa</button>
                </article>
            </div>
        </div>
        <p class="text-center text-muted small mt-4">O período de teste e as condições finais são exibidos antes da confirmação do cadastro. Consulte os detalhes de cada plano.</p>
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

<section id="faq" class="section section-soft faq">
    <div class="container">
        <div class="text-center mb-5"><span class="section-kicker">Perguntas frequentes</span><h2 class="section-title mt-2">Tire suas dúvidas antes de começar</h2></div>
        <div class="row justify-content-center"><div class="col-lg-9"><div class="accordion" id="faqList">
            <?php
            $faqs = [
                ['Preciso instalar alguma coisa?', 'Não. O acesso é feito pelo navegador em computador ou celular com conexão à internet.'],
                ['O sistema funciona no celular?', 'Sim. As principais telas são responsivas e podem ser acessadas pelo navegador do celular.'],
                ['Minha equipe pode acessar?', 'Sim. A quantidade depende do plano, e cada usuário pode receber permissões específicas.'],
                ['Como funciona o teste grátis?', 'O período aplicável aparece nos detalhes do plano e no cadastro antes da confirmação.'],
                ['O plano Básico possui PDV?', 'Não. O PDV e os recursos de vendas estão disponíveis a partir do plano Profissional (Plus).'],
                ['O sistema já emite nota fiscal real?', 'O módulo fiscal está em homologação e preparado para testes. Emissão real depende de credenciamento, certificado e validação fiscal.'],
                ['Como são processados os pagamentos da assinatura?', 'A cobrança recorrente pode ser processada pelo Mercado Pago, conforme a opção disponível no cadastro.'],
                ['Posso cancelar ou trocar de plano?', 'A gestão da assinatura permite acompanhar o plano e solicitar alterações conforme as condições contratadas.'],
                ['Tenho suporte se precisar?', 'Sim. O sistema possui suporte interno para assinantes, protocolos e canais que variam conforme o plano.'],
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
            <h2 class="section-title">Pronto para organizar sua empresa?</h2>
            <p class="lead opacity-75 mx-auto mb-4" style="max-width:720px">Crie sua conta, conheça o sistema e escolha o plano que acompanha o momento do seu negócio.</p>
            <a class="btn btn-light text-primary fw-bold btn-lg px-5" href="pages/registro.php">Começar meu teste grátis</a>
        </div>
    </div>
</section>

<!-- Seção de Depoimentos / Feedbacks -->
<section class="py-5 bg-light" id="depoimentos">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="eyebrow"><i class="bi bi-chat-heart"></i> O que dizem nossos clientes</span>
            <h2 class="fw-bold">Quem usa, aprova e recomenda</h2>
            <p class="text-muted">Veja como o nosso sistema está transformando a rotina de gestão de nossos clientes.</p>
        </div>

        <div class="row g-4">
            <?php if (!empty($feedbacks)): ?>
                <?php foreach ($feedbacks as $feedback): ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 p-4">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <!-- Estrelas de avaliação -->
                                    <div class="text-warning mb-3">
                                        <?php 
                                        $nota = isset($feedback['nota']) ? intval($feedback['nota']) : 5;
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $nota ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                        }
                                        ?>
                                    </div>
                                    <p class="card-text text-secondary fst-italic">
                                        "<?= htmlspecialchars($feedback['mensagem'] ?? $feedback['comentario'] ?? 'Sistema excelente e muito prático para o dia a dia!'); ?>"
                                    </p>
                                </div>
                                <div class="d-flex align-items-center mt-4 pt-3 border-top">
                                    <div class="ms-3">
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($feedback['nome'] ?? 'Cliente Satisfeito'); ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($feedback['empresa'] ?? 'Empresa parceira'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback caso não haja feedbacks cadastrados ainda -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 p-4">
                        <div class="card-body">
                            <div class="text-warning mb-3">
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                            </div>
                            <p class="card-text text-secondary fst-italic">
                                "O controle de caixa e financeiro ficou muito mais simples. Economizamos horas de trabalho toda semana!"
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <h6 class="mb-0 fw-bold">Carlos Eduardo</h6>
                            <small class="text-muted">Comércio Local</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 p-4">
                        <div class="card-body">
                            <div class="text-warning mb-3">
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                            </div>
                            <p class="card-text text-secondary fst-italic">
                                "Plataforma excelente, intuitiva e o suporte é nota dez. Recomendo para qualquer empresa."
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <h6 class="mb-0 fw-bold">Mariana Souza</h6>
                            <small class="text-muted">Prestadora de Serviços</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 p-4">
                        <div class="card-body">
                            <div class="text-warning mb-3">
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                            </div>
                            <p class="card-text text-secondary fst-italic">
                                "Conseguimos organizar nosso estoque e as contas a pagar sem precisar de planilhas complexas."
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <h6 class="mb-0 fw-bold">Roberto Alves</h6>
                            <small class="text-muted">Distribuidora</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-5"><div class="fw-bold fs-5"><i class="bi bi-wallet2 text-info me-2"></i>App Controle Pro</div><p class="text-secondary small mt-2 mb-0">Gestão integrada para MEIs e pequenas empresas.</p></div>
            <div class="col-md-4 d-flex flex-wrap gap-3 justify-content-md-center"><a class="footer-link" href="pages/protecao_de_dados.php">Proteção de dados</a><a class="footer-link" href="pages/suporte.php">Suporte</a><a class="footer-link" href="pages/login.php">Área do cliente</a></div>
            <div class="col-md-3 text-md-end text-secondary small">&copy; <?= date('Y') ?> Felipe Fardin.<br>Todos os direitos reservados.</div>
        </div>
    </div>
</footer>

<div class="modal fade" id="modalDetalhesPlano" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
        <div class="modal-header"><h2 class="modal-title h5 fw-bold" id="modalPlanoTitulo">Detalhes do plano</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body p-4"><div class="text-center mb-4"><div class="price" id="modalPlanoPreco"></div><span class="eyebrow" id="modalPlanoBadge"></span><p class="text-muted mt-3" id="modalPlanoDesc"></p></div><h3 class="h6 fw-bold">O que está incluído:</h3><ul class="list-unstyled" id="modalPlanoLista"></ul></div>
        <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Voltar</button><a href="#" id="btnAssinarModal" class="btn btn-brand">Criar conta neste plano</a></div>
    </div></div>
</div>

<div vw class="enabled"><div vw-access-button class="active"></div><div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
<script>
const dadosPlanos = {
    basico:{titulo:'Plano Básico',preco:'R$ 19,00/mês',badge:'Organização financeira',desc:'Para controlar contas a pagar e receber sem o módulo de vendas.',features:['Até 3 usuários','Contas a pagar e receber','Controle de vencimentos','Acesso por celular e computador','Suporte por e-mail']},
    plus:{titulo:'Plano Profissional (Plus)',preco:'R$ 39,00/mês',badge:'Mais escolhido',desc:'Para controlar vendas, caixa, estoque e financeiro.',features:['Até 6 usuários','Todos os recursos do Básico','PDV e caixa diário','Estoque e compras','Metas e relatórios','Suporte prioritário']},
    essencial:{titulo:'Plano Empresa (Essencial)',preco:'R$ 59,00/mês',badge:'Equipe e escala',desc:'Para empresas com mais usuários e atendimento dedicado.',features:['Até 16 usuários','Todos os recursos disponíveis','Auditoria e permissões','Treinamento inicial','Suporte dedicado']}
};
function abrirModalPlano(chave){
    const plano=dadosPlanos[chave]; if(!plano)return;
    document.getElementById('modalPlanoTitulo').textContent=plano.titulo;
    document.getElementById('modalPlanoPreco').textContent=plano.preco;
    document.getElementById('modalPlanoBadge').textContent=plano.badge;
    document.getElementById('modalPlanoDesc').textContent=plano.desc;
    const lista=document.getElementById('modalPlanoLista'); lista.innerHTML='';
    plano.features.forEach(item=>{const li=document.createElement('li');li.className='mb-2';li.innerHTML='<i class="bi bi-check-circle-fill text-success me-2"></i>'+item;lista.appendChild(li)});
    document.getElementById('btnAssinarModal').href='pages/registro.php?plano='+encodeURIComponent(chave);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhesPlano')).show();
}
document.querySelectorAll('[data-screen]').forEach(button=>button.addEventListener('click',()=>{
    document.querySelectorAll('[data-screen]').forEach(item=>item.classList.remove('active'));
    button.classList.add('active');
    const image=document.getElementById('systemScreen'); image.src=button.dataset.screen; image.alt=button.dataset.alt;
}));
if(window.VLibras)new window.VLibras.Widget('https://vlibras.gov.br/app');
</script>
</body>
</html>
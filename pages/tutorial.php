<?php
require_once '../includes/session_init.php';
require_once '../database.php';

if (empty($_SESSION['usuario_logado']) && empty($_SESSION['super_admin'])) {
    header('Location: login.php');
    exit;
}

$superAdminData = $_SESSION['super_admin'] ?? null;
$nomeUsuario = $_SESSION['nome'] ?? (is_array($superAdminData) ? ($superAdminData['nome'] ?? 'Super Admin') : 'Usuário');
$perfil = $_SESSION['nivel_acesso'] ?? (!empty($_SESSION['super_admin']) ? 'super administrador' : 'padrão');
include_once '../includes/header.php';
?>

<style>
:root{--t-accent:#00bfff;--t-card:#1b1d20;--t-card2:#23262a;--t-border:#34383e;--t-text:#eef2f5;--t-muted:#aeb6bf;--t-ok:#35c46a;--t-warn:#ffc145}
.tutorial-shell{max-width:1450px;margin:24px auto 105px;padding:0 22px;color:var(--t-text);font-family:Inter,"Segoe UI",Arial,sans-serif}
.tutorial-hero{background:linear-gradient(135deg,#064e70,#087ea4 52%,#0d6efd);border-radius:18px;padding:34px;box-shadow:0 16px 40px rgba(0,0,0,.28);margin-bottom:22px}
.tutorial-hero h1{font-size:clamp(28px,4vw,44px);margin:0 0 9px;color:#fff}.tutorial-hero p{max-width:920px;font-size:17px;line-height:1.6;margin:0;color:#eaf8ff}
.profile-chip{display:inline-flex;gap:8px;align-items:center;margin-top:18px;background:rgba(255,255,255,.16);padding:8px 13px;border-radius:999px}
.tutorial-search{margin-top:22px;position:relative;max-width:720px}.tutorial-search i{position:absolute;left:16px;top:15px;color:#607080}
.tutorial-search input{width:100%;box-sizing:border-box;padding:13px 15px 13px 44px;border-radius:10px;border:1px solid #c8d9e4;background:#fff;color:#17212b;font-size:16px}
.tutorial-search-status{display:block;margin-top:8px;color:#eaf8ff;font-size:14px;min-height:20px}
.tutorial-layout{display:grid;grid-template-columns:285px minmax(0,1fr);gap:24px}.tutorial-nav{position:sticky;top:90px;align-self:start;max-height:calc(100vh - 120px);overflow:auto;background:var(--t-card);border:1px solid var(--t-border);border-radius:14px;padding:15px}
.tutorial-nav strong{display:block;color:var(--t-accent);padding:8px 10px 12px}.tutorial-nav a{display:flex;gap:9px;align-items:center;color:#d8dee4;text-decoration:none;padding:10px;border-radius:8px;font-size:14px}
.tutorial-nav a:hover,.tutorial-nav a.active{background:#113747;color:#65d5ff}.guide-section{scroll-margin-top:100px;;border:1px solid var(--t-border);border-radius:14px;padding:26px;margin-bottom:22px}
.guide-section.hidden-by-search{display:none}.section-title{display:flex;gap:13px;align-items:center;margin-bottom:18px}.section-title .icon{width:46px;height:46px;display:grid;place-items:center;border-radius:12px;background:#103846;color:var(--t-accent);font-size:21px}
.section-title h2{margin:0;color:#fff;font-size:24px}.section-title p{margin:4px 0 0;color:var(--t-muted)}.guide-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.guide-card{background:var(--t-card2);border:1px solid var(--t-border);border-radius:11px;padding:18px}.guide-card h3{margin:0 0 10px;color:#fff;font-size:17px}
.guide-card p,.guide-card li{color:#c4cbd2;line-height:1.58;font-size:14px}.guide-card ol,.guide-card ul{padding-left:21px;margin-bottom:8px}.guide-card li{margin:6px 0}
.guide-link{display:inline-flex;align-items:center;gap:7px;margin-top:8px;background:#087ea4;color:#fff!important;text-decoration:none;padding:9px 12px;border-radius:7px;font-weight:700;font-size:13px}.guide-link:hover{background:#0a96c3}
.note{border-left:4px solid var(--t-accent);background:#102c37;padding:12px 14px;border-radius:7px;margin:14px 0;color:#d8f5ff;line-height:1.5}.note.ok{border-color:var(--t-ok);background:#143421;color:#d9ffe5}.note.warn{border-color:var(--t-warn);background:#3d3215;color:#fff1bd}
.workflow{display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;margin:16px 0}.workflow span{flex:1;min-width:130px;background:#222b31;border:1px solid #3a4851;border-radius:9px;padding:13px;text-align:center;color:#e7f7ff}.workflow i{display:block;color:var(--t-accent);font-size:20px;margin-bottom:6px}
.routine-table{width:100%;border-collapse:collapse;background:#202327}.routine-table th,.routine-table td{border-bottom:1px solid #3b4045;padding:12px;text-align:left;vertical-align:top}.routine-table th{color:var(--t-accent)}
.search-empty{display:none;text-align:center;padding:35px;background:var(--t-card);border-radius:12px;color:var(--t-muted)}.search-empty.visible{display:block}

/* Paleta específica do tutorial no modo claro. */
html[data-app-theme="light"] .tutorial-shell,
body.light-mode .tutorial-shell { color:#172033; }
html[data-app-theme="light"] :is(.guide-section,.guide-card,.tutorial-nav,.search-empty),
body.light-mode :is(.guide-section,.guide-card,.tutorial-nav,.search-empty) {
    background:#fff;
    color:#172033;
    border-color:#d5dfeb;
}
html[data-app-theme="light"] :is(.section-title h2,.guide-card h3,.routine-table td),
body.light-mode :is(.section-title h2,.guide-card h3,.routine-table td) { color:#172033 !important; }
html[data-app-theme="light"] :is(.section-title p,.guide-card p,.guide-card li,.search-empty),
body.light-mode :is(.section-title p,.guide-card p,.guide-card li,.search-empty) { color:#344054 !important; }
html[data-app-theme="light"] .guide-card :is(strong,b),
body.light-mode .guide-card :is(strong,b) { color:#101828; }
html[data-app-theme="light"] .tutorial-nav strong,
body.light-mode .tutorial-nav strong { color:#087ea4; }
html[data-app-theme="light"] .tutorial-nav a,
body.light-mode .tutorial-nav a { color:#344054; }
html[data-app-theme="light"] .tutorial-nav a:hover,
html[data-app-theme="light"] .tutorial-nav a.active,
body.light-mode .tutorial-nav a:hover,
body.light-mode .tutorial-nav a.active { background:#eaf7fd; color:#05668d; }
html[data-app-theme="light"] .workflow span,
body.light-mode .workflow span { background:#f4f8fc; color:#25364a; border-color:#d5dfeb; }
html[data-app-theme="light"] .routine-table,
body.light-mode .routine-table { background:#fff; }
html[data-app-theme="light"] .routine-table :is(th,td),
body.light-mode .routine-table :is(th,td) { border-color:#d5dfeb; }
html[data-app-theme="light"] .routine-table th,
body.light-mode .routine-table th { color:#05668d; background:#f4f8fc; }
/* Os avisos mantêm fundo escuro/colorido e, portanto, precisam de texto claro. */
html[data-app-theme="light"] .note,
html[data-app-theme="light"] .note :is(p,li,strong,b),
body.light-mode .note,
body.light-mode .note :is(p,li,strong,b) { color:#fff !important; }
@media(max-width:980px){.tutorial-layout{grid-template-columns:1fr}.tutorial-nav{position:static;max-height:none}.tutorial-nav div{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:680px){.tutorial-shell{padding:0 12px}.tutorial-hero,.guide-section{padding:20px}.guide-grid{grid-template-columns:1fr}.tutorial-nav div{grid-template-columns:1fr}.routine-table{display:block;overflow:auto}}
</style>

<div class="tutorial-shell">
    <section class="tutorial-hero">
        <h1>Manual atualizado do sistema</h1>
        <p>Olá, <?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?>. Este guia acompanha a organização atual do dashboard e explica as rotinas de caixa, PDV, estoque, financeiro, IA local, metas, equipe, fiscal e suporte.</p>
        <span class="profile-chip"><i class="fas fa-user-shield"></i> Perfil atual: <?= htmlspecialchars(ucfirst($perfil), ENT_QUOTES, 'UTF-8') ?></span>
        <div class="tutorial-search">
            <i class="fas fa-search"></i>
            <input id="tutorialSearch" type="search" placeholder="Pesquisar: IA local, abrir caixa, PDV, metas, PIX..." autocomplete="off">
            <small id="tutorialSearchStatus" class="tutorial-search-status" aria-live="polite"></small>
        </div>
    </section>

    <div class="tutorial-layout">
        <aside class="tutorial-nav" aria-label="Índice do tutorial">
            <strong><i class="fas fa-list"></i> Conteúdo</strong>
            <div>
                <a href="#dashboard"><i class="fas fa-table-cells-large"></i> Dashboard e atalhos</a>
                <a href="#ia-financeira"><i class="fas fa-wand-magic-sparkles"></i> IA financeira local</a>
                <a href="#primeiros-passos"><i class="fas fa-rocket"></i> Primeiros passos</a>
                <a href="#equipe"><i class="fas fa-users-gear"></i> Equipe e permissões</a>
                <a href="#cadastros"><i class="fas fa-address-book"></i> Cadastros</a>
                <a href="#estoque-compras"><i class="fas fa-boxes-stacked"></i> Estoque e compras</a>
                <a href="#caixa-pdv"><i class="fas fa-cash-register"></i> Abertura e PDV</a>
                <a href="#movimentacoes"><i class="fas fa-money-bill-transfer"></i> Movimentações</a>
                <a href="#fechamento"><i class="fas fa-lock"></i> Fechamento</a>
                <a href="#metas"><i class="fas fa-bullseye"></i> Metas</a>
                <a href="#financeiro"><i class="fas fa-wallet"></i> Financeiro</a>
                <a href="#fiscal"><i class="fas fa-file-invoice"></i> Área fiscal</a>
                <a href="#assinatura"><i class="fas fa-credit-card"></i> Assinatura</a>
                <a href="#lgpd"><i class="fas fa-shield-halved"></i> Proteção de dados</a>
                <a href="#suporte"><i class="fas fa-headset"></i> Suporte</a>
                <a href="#rotinas"><i class="fas fa-calendar-check"></i> Rotina recomendada</a>
            </div>
        </aside>

        <main class="tutorial-content">
            <div id="searchEmpty" class="search-empty"><i class="fas fa-search"></i> Nenhum tópico encontrado. Tente outra palavra.</div>

            <section id="dashboard" class="guide-section" data-keywords="dashboard início atalhos operação financeiro cadastros gestão configurações">
                <div class="section-title"><div class="icon"><i class="fas fa-table-cells-large"></i></div><div><h2>Dashboard e novos grupos de atalhos</h2><p>Os recursos agora estão organizados conforme a rotina de trabalho.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Operação</h3><p>Reúne PDV, abertura do caixa, movimentações, fechamento, vendas do período, compras e estoque. É a primeira aba porque concentra o trabalho diário.</p></article>
                    <article class="guide-card"><h3>Financeiro</h3><p>Contém contas a pagar, contas pagas, contas a receber e contas recebidas. Use a baixa somente quando o pagamento ou recebimento realmente acontecer.</p></article>
                    <article class="guide-card"><h3>Cadastros e Gestão</h3><p>Cadastros reúne clientes, fornecedores, bancos, chaves PIX e categorias. Gestão reúne relatórios, lembretes, equipe, permissões e troca de usuário.</p></article>
                    <article class="guide-card"><h3>Configurações</h3><p>Use para acessar perfil, configuração fiscal, assinatura, proteção de dados e suporte.</p><a class="guide-link" href="home.php"><i class="fas fa-home"></i> Abrir dashboard</a></article>
                </div>
            </section>

            <section id="ia-financeira" class="guide-section" data-keywords="ia inteligência artificial ollama gemma resumo automático workflow contas vencidas previsão privacidade cache offline">
                <div class="section-title"><div class="icon"><i class="fas fa-wand-magic-sparkles"></i></div><div><h2>IA financeira local</h2><p>Um resumo inteligente e privado das prioridades financeiras da empresa.</p></div></div>
                <div class="workflow"><span><i class="fas fa-database"></i>Consulta os totais</span><span><i class="fas fa-calculator"></i>Calcula o risco</span><span><i class="fas fa-brain"></i>IA local analisa</span><span><i class="fas fa-lightbulb"></i>Exibe a recomendação</span></div>
                <div class="guide-grid">
                    <article class="guide-card">
                        <h3>Onde encontrar</h3>
                        <p>Abra o dashboard. O card do resumo automático aparece depois das ações rápidas e antes dos indicadores de saldo, receitas e despesas.</p>
                        <p>Quando o modelo gerar a recomendação, o canto do card mostrará <strong>“IA local”</strong> e o horário da atualização.</p>
                        <a class="guide-link" href="home.php"><i class="fas fa-home"></i> Ver IA no dashboard</a>
                    </article>
                    <article class="guide-card">
                        <h3>O que é analisado</h3>
                        <ul><li>Contas a pagar vencidas.</li><li>Pagamentos dos próximos três dias.</li><li>Recebimentos atrasados.</li><li>Recebimentos dos próximos três dias.</li><li>Diferença prevista entre entradas e saídas.</li></ul>
                        <div class="note">A qualidade do resumo depende dos valores, vencimentos e baixas estarem atualizados.</div>
                    </article>
                    <article class="guide-card">
                        <h3>Privacidade e segurança</h3>
                        <p>O modelo Gemma é executado pelo Ollama no próprio computador. Para gerar a recomendação, ele recebe somente quantidades e totais financeiros agregados.</p>
                        <ul><li>Não recebe senhas nem credenciais.</li><li>Não envia os dados financeiros para uma API externa.</li><li>Não dá baixa, altera ou exclui lançamentos.</li><li>Não envia cobranças automaticamente.</li></ul>
                    </article>
                    <article class="guide-card">
                        <h3>IA local ou Automação local</h3>
                        <p><strong>IA local:</strong> o Ollama respondeu e o texto foi produzido pelo modelo instalado no computador.</p>
                        <p><strong>Automação local:</strong> o sistema usou a recomendação segura baseada em regras porque a IA estava desligada, ocupada ou demorou além do limite.</p>
                        <div class="note ok">O painel continua funcionando normalmente mesmo sem a IA.</div>
                    </article>
                    <article class="guide-card">
                        <h3>Atualização e cache</h3>
                        <p>A recomendação fica guardada por 15 minutos para evitar lentidão e processamento repetido. Depois desse período, uma nova visita ao dashboard pode gerar uma análise atualizada.</p>
                        <p>Na primeira execução, aguarde alguns segundos enquanto o modelo é carregado na memória.</p>
                    </article>
                    <article class="guide-card">
                        <h3>Se a IA não aparecer</h3>
                        <ol><li>Confirme que o aplicativo Ollama está aberto na área de notificação do Windows.</li><li>Atualize o dashboard com <strong>Ctrl + F5</strong>.</li><li>Aguarde até 15 segundos.</li><li>Confira se o card informa “IA local” ou “Automação local”.</li></ol>
                        <p>Se continuar em automação local, o resumo por regras permanece válido e seguro.</p>
                    </article>
                </div>
            </section>

            <section id="primeiros-passos" class="guide-section" data-keywords="início configuração empresa primeiros passos">
                <div class="section-title"><div class="icon"><i class="fas fa-rocket"></i></div><div><h2>Primeiros passos</h2><p>Prepare os dados antes de começar a vender.</p></div></div>
                <div class="workflow"><span><i class="fas fa-building"></i>1. Perfil da empresa</span><span><i class="fas fa-tags"></i>2. Categorias</span><span><i class="fas fa-building-columns"></i>3. Bancos e PIX</span><span><i class="fas fa-users"></i>4. Pessoas</span><span><i class="fas fa-box"></i>5. Produtos</span></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Configuração inicial</h3><ol><li>Revise os dados da empresa e do responsável.</li><li>Crie categorias de receitas e despesas.</li><li>Cadastre bancos e chaves PIX.</li><li>Cadastre clientes, fornecedores e produtos.</li><li>Se for testar emissão fiscal, complete os dados fiscais.</li></ol><a class="guide-link" href="perfil.php">Abrir perfil</a></article>
                    <article class="guide-card"><h3>Ligação entre os módulos</h3><p>Compras aumentam o estoque. Vendas reduzem o estoque, alimentam metas e geram registros financeiros. Pagamentos, recebimentos e movimentos do PDV aparecem nos controles correspondentes.</p><div class="note">Cadastros completos evitam clientes “N/D”, cobranças sem e-mail e documentos incompletos.</div></article>
                </div>
            </section>

            <section id="equipe" class="guide-section" data-keywords="equipe usuários extras permissões compartilhamento">
                <div class="section-title"><div class="icon"><i class="fas fa-users-gear"></i></div><div><h2>Equipe e permissões</h2><p>Usuários extras trabalham com os dados da mesma empresa.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Adicionar usuário</h3><ol><li>Abra Gestão e clique em “Equipe e permissões”.</li><li>Cadastre os dados da pessoa.</li><li>Escolha o nível de acesso.</li><li>Marque somente as páginas necessárias.</li></ol><a class="guide-link" href="usuarios.php">Gerenciar equipe</a></article>
                    <article class="guide-card"><h3>Dados compartilhados</h3><p>Clientes, fornecedores, produtos, estoque, vendas, bancos e financeiro pertencem à empresa. Cada usuário mantém sua identidade e enxerga somente os módulos autorizados.</p></article>
                    <article class="guide-card"><h3>Trocar usuário</h3><p>Administradores podem usar o atalho “Trocar usuário” para acessar o contexto permitido de outro integrante da equipe.</p></article>
                    <article class="guide-card"><h3>Segurança</h3><p>Não conceda perfil administrativo para quem precisa apenas vender ou consultar. Revise os acessos quando a função da pessoa mudar.</p></article>
                </div>
            </section>

            <section id="cadastros" class="guide-section" data-keywords="clientes fornecedores categorias bancos pix cadastros">
                <div class="section-title"><div class="icon"><i class="fas fa-address-book"></i></div><div><h2>Cadastros essenciais</h2><p>Informações reaproveitadas nos demais módulos.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Clientes e fornecedores</h3><p>Informe nome, documento, telefone, e-mail e endereço. O e-mail é necessário para cobranças e os dados fiscais são importantes para notas.</p><a class="guide-link" href="cadastrar_pessoa_fornecedor.php">Abrir cadastro</a></article>
                    <article class="guide-card"><h3>Categorias financeiras</h3><p>Separe receitas e despesas em grupos claros, como vendas, serviços, aluguel, transporte e impostos.</p><a class="guide-link" href="categorias.php">Abrir categorias</a></article>
                    <article class="guide-card"><h3>Bancos e chaves PIX</h3><p>As chaves cadastradas ficam disponíveis no envio de cobranças. Confira o titular antes de utilizar.</p><a class="guide-link" href="banco_cadastro.php">Abrir bancos</a></article>
                    <article class="guide-card"><h3>Evite duplicidade</h3><p>Pesquise antes de criar uma nova pessoa, categoria ou produto. Duplicidades prejudicam relatórios e históricos.</p></article>
                </div>
            </section>

            <section id="estoque-compras" class="guide-section" data-keywords="estoque produto sku ncm cfop compra fornecedor">
                <div class="section-title"><div class="icon"><i class="fas fa-boxes-stacked"></i></div><div><h2>Produtos, estoque e compras</h2><p>Controle entradas, saídas, custos e níveis mínimos.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Cadastrar produto</h3><ul><li>Use código ou SKU único.</li><li>Informe preço de compra e venda.</li><li>Defina estoque atual e mínimo.</li><li>Para emissão fiscal, revise NCM e CFOP.</li></ul><a class="guide-link" href="controle_estoque.php">Produtos e estoque</a></article>
                    <article class="guide-card"><h3>Registrar compra</h3><ol><li>Selecione o fornecedor.</li><li>Adicione produtos, quantidades e custos.</li><li>Confira e confirme.</li></ol><p>A compra aumenta o estoque e pode gerar uma conta a pagar.</p><a class="guide-link" href="compras.php">Registrar compra</a></article>
                </div>
            </section>

            <section id="caixa-pdv" class="guide-section" data-keywords="abrir caixa saldo inicial pdv venda dinheiro pix cartão receber">
                <div class="section-title"><div class="icon"><i class="fas fa-cash-register"></i></div><div><h2>Abertura diária e vendas no PDV</h2><p>O caixa deve ser aberto antes da primeira venda de cada dia.</p></div></div>
                <div class="workflow"><span><i class="fas fa-lock-open"></i>Abrir caixa</span><span><i class="fas fa-coins"></i>Informar saldo inicial</span><span><i class="fas fa-cart-shopping"></i>Registrar vendas</span><span><i class="fas fa-lock"></i>Fechar no fim do dia</span></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Abrir caixa</h3><ol><li>Acesse Operação → “PDV e abertura do caixa”.</li><li>Clique em “Abrir caixa”.</li><li>Informe o dinheiro inicial para troco ou deixe R$ 0,00.</li><li>Adicione uma observação se necessário.</li><li>Confirme a abertura.</li></ol><div class="note ok">O saldo inicial é registrado automaticamente como entrada de abertura.</div></article>
                    <article class="guide-card"><h3>Realizar venda</h3><ol><li>Selecione o cliente.</li><li>Adicione produtos e quantidades.</li><li>Informe o desconto.</li><li>Escolha dinheiro, PIX, cartão ou “A receber”.</li><li>Finalize e gere o recibo.</li></ol><a class="guide-link" href="vendas.php">Abrir PDV</a></article>
                    <article class="guide-card"><h3>Registros automáticos</h3><ul><li>O estoque é reduzido.</li><li>A venda entra nos relatórios e na meta ativa.</li><li>Pagamentos imediatos entram nas movimentações do caixa.</li><li>“A receber” cria o lançamento financeiro e não aumenta o dinheiro físico do caixa.</li></ul></article>
                    <article class="guide-card"><h3>Depois do fechamento</h3><p>Não é possível registrar novas vendas no mesmo dia depois de fechar o caixa. Um novo ciclo poderá ser aberto no dia seguinte.</p></article>
                </div>
            </section>

            <section id="movimentacoes" class="guide-section" data-keywords="movimentações caixa entrada retirada sangria reforço saldo dinheiro">
                <div class="section-title"><div class="icon"><i class="fas fa-money-bill-transfer"></i></div><div><h2>Movimentações do caixa</h2><p>Acompanhe automaticamente tudo que altera o dinheiro do caixa.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Entradas automáticas</h3><p>O saldo inicial e as vendas pagas no momento aparecem automaticamente. A tela identifica origem, descrição, forma de pagamento, entrada e saída.</p></article>
                    <article class="guide-card"><h3>Entrada manual</h3><p>Use quando adicionar dinheiro ao caixa, por exemplo um reforço de troco. Informe valor e descrição.</p></article>
                    <article class="guide-card"><h3>Retirada manual</h3><p>Use para sangria ou outra retirada real. O valor será subtraído do dinheiro esperado no fechamento.</p></article>
                    <article class="guide-card"><h3>Proteção do histórico</h3><p>Aberturas e vendas automáticas não podem ser editadas ou excluídas manualmente. Isso preserva a conciliação.</p><a class="guide-link" href="lancamento_caixa.php">Ver movimentações</a></article>
                </div>
            </section>

            <section id="fechamento" class="guide-section" data-keywords="fechamento caixa saldo esperado dinheiro vendas fechar lembrete">
                <div class="section-title"><div class="icon"><i class="fas fa-lock"></i></div><div><h2>Fechamento do caixa</h2><p>Encerre formalmente a operação diária.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>O que conferir</h3><ul><li>Saldo inicial.</li><li>Vendas em dinheiro.</li><li>PIX, cartões e outras formas.</li><li>Entradas e retiradas manuais.</li><li>Dinheiro esperado no caixa.</li><li>Lista de vendas do dia.</li></ul></article>
                    <article class="guide-card"><h3>Como fechar</h3><ol><li>Abra Operação → “Fechamento do caixa”.</li><li>Confira os totais.</li><li>Clique em “Fechar caixa de hoje”.</li><li>Confirme o encerramento.</li></ol><p>O fechamento é permitido mesmo sem vendas, caso exista uma abertura com saldo inicial.</p><a class="guide-link" href="fechamento_caixa.php">Abrir fechamento</a></article>
                    <article class="guide-card"><h3>Lembrete automático</h3><p>Se o caixa permanecer aberto, o sistema apresenta um aviso para evitar que o dia termine sem fechamento.</p></article>
                    <article class="guide-card"><h3>Cancelamento de venda</h3><p>Quando aplicável, o cancelamento devolve os itens ao estoque e remove os lançamentos financeiros e de caixa vinculados.</p></article>
                </div>
            </section>

            <section id="metas" class="guide-section" data-keywords="meta vendas ciclo histórico equipe parabéns">
                <div class="section-title"><div class="icon"><i class="fas fa-bullseye"></i></div><div><h2>Ciclos de metas</h2><p>A meta continua acumulando vendas mesmo depois de atingir 100%.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Criar meta</h3><p>No PDV, informe o novo objetivo. Se já existir uma meta ativa, ela é encerrada e guardada no histórico.</p></article>
                    <article class="guide-card"><h3>Meta atingida</h3><p>O percentual pode ultrapassar 100%. A equipe recebe a mensagem de parabéns uma única vez por ciclo.</p></article>
                    <article class="guide-card"><h3>Histórico</h3><p>Consulte meta, total realizado, percentual, início, conquista, encerramento e responsável.</p><a class="guide-link" href="vendas.php">Consultar no PDV</a></article>
                    <article class="guide-card"><h3>Ciclo recomendado</h3><p>Crie uma nova meta somente no início do próximo período planejado. Até lá, deixe o excedente continuar contando.</p></article>
                </div>
            </section>

            <section id="financeiro" class="guide-section" data-keywords="financeiro contas pagar receber cobrança email pix baixa ia resumo automático">
                <div class="section-title"><div class="icon"><i class="fas fa-wallet"></i></div><div><h2>Financeiro</h2><p>Controle obrigações, recebimentos e cobranças.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Contas a pagar</h3><p>Registre fornecedor, categoria, descrição, valor e vencimento. Dê baixa apenas depois do pagamento.</p><a class="guide-link" href="contas_pagar.php">Contas a pagar</a></article>
                    <article class="guide-card"><h3>Contas a receber</h3><p>Registre ou acompanhe o cliente e o vencimento. A baixa confirma o recebimento.</p><a class="guide-link" href="contas_receber.php">Contas a receber</a></article>
                    <article class="guide-card"><h3>Enviar cobrança</h3><p>Use um cliente com e-mail válido, selecione uma chave PIX cadastrada, escreva a mensagem e anexe um arquivo quando necessário.</p></article>
                    <article class="guide-card"><h3>Pendente e baixada</h3><p>Cadastrar uma conta não significa que ela foi paga ou recebida. Use baixa e estorno conforme o evento real.</p></article>
                    <article class="guide-card"><h3>Resumo automático com IA</h3><p>O dashboard combina contas vencidas e próximas do vencimento para destacar riscos e sugerir uma prioridade. A IA é consultiva: confira os lançamentos antes de decidir.</p><a class="guide-link" href="#ia-financeira">Entender a IA local</a></article>
                </div>
            </section>

            <section id="fiscal" class="guide-section" data-keywords="fiscal nfce nfe nota danfe homologação produção certificado csc ncm cfop">
                <div class="section-title"><div class="icon"><i class="fas fa-file-invoice"></i></div><div><h2>Área fiscal e NFC-e</h2><p>O sistema permanece preparado para testes antes da produção.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Dados necessários</h3><ul><li>CNPJ, razão social, endereço, IE e regime tributário.</li><li>Certificado A1 e senha.</li><li>CSC e identificador para NFC-e.</li><li>NCM, CFOP e tributação dos produtos.</li></ul><a class="guide-link" href="configuracao_fiscal.php">Configuração fiscal</a></article>
                    <article class="guide-card"><h3>Homologação</h3><p>É o ambiente de teste e não gera documento comercial válido. Continue usando-o até concluir credenciamento e validação contábil.</p></article>
                    <article class="guide-card"><h3>DANFE</h3><p>O DANFE fica disponível quando existe nota fiscal autorizada ou representação de teste gerada corretamente. Recibo não substitui documento fiscal.</p></article>
                    <article class="guide-card"><h3>Antes da produção</h3><p>Revise impostos e regras com a contabilidade. A mudança de ambiente deve ser consciente e planejada.</p></article>
                </div>
            </section>

            <section id="assinatura" class="guide-section" data-keywords="assinatura plano cupom desconto indicação mercado pago">
                <div class="section-title"><div class="icon"><i class="fas fa-credit-card"></i></div><div><h2>Assinatura, planos e cupons</h2><p>Gerencie o acesso da empresa aos recursos contratados.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Plano atual</h3><p>O plano define recursos e quantidade de usuários extras. Consulte status, renovação e histórico na área da assinatura.</p><a class="guide-link" href="minha_assinatura.php">Minha assinatura</a></article>
                    <article class="guide-card"><h3>Cupons</h3><p>Cupons públicos podem ser usados conforme a campanha. Cupons internos são direcionados pela administração a clientes específicos.</p></article>
                    <article class="guide-card"><h3>Indique e ganhe</h3><p>O código de indicação vincula o novo cadastro ao indicador. O ranking depende do registro correto e das condições da campanha.</p></article>
                    <article class="guide-card"><h3>Pagamento recorrente</h3><p>Acompanhe cobranças pelo provedor configurado e mantenha os webhooks ativos para atualizar a assinatura automaticamente.</p></article>
                </div>
            </section>

            <section id="lgpd" class="guide-section" data-keywords="lgpd privacidade consentimento documento registro proteção dados">
                <div class="section-title"><div class="icon"><i class="fas fa-shield-halved"></i></div><div><h2>Proteção de dados e documentos</h2><p>Registros de consentimento e responsabilidades da empresa.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Consentimento</h3><p>O aceite no cadastro registra usuário, documento, data, IP de origem e arquivo correspondente quando aplicável.</p><a class="guide-link" href="protecao_de_dados.php">Proteção de dados</a></article>
                    <article class="guide-card"><h3>Boas práticas</h3><ul><li>Conceda somente acessos necessários.</li><li>Não compartilhe senhas.</li><li>Mantenha dados atualizados.</li><li>Exporte ou exclua informações somente com autorização.</li></ul></article>
                </div>
            </section>

            <section id="suporte" class="guide-section" data-keywords="suporte chat interno externo protocolo conversa pdf whatsapp email">
                <div class="section-title"><div class="icon"><i class="fas fa-headset"></i></div><div><h2>Suporte e protocolos</h2><p>Use o atendimento interno para assinantes e o externo para não assinantes.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Suporte interno</h3><p>O chat atualiza as mensagens automaticamente e gera um protocolo ao encerrar.</p><a class="guide-link" href="solicitar_meu_suporte.php">Solicitar suporte</a></article>
                    <article class="guide-card"><h3>Suporte externo</h3><p>Atende pessoas sem assinatura ou sem acesso. A resposta pode seguir por chat, e-mail ou WhatsApp, conforme a opção informada.</p><a class="guide-link" href="suporte.php">Suporte externo</a></article>
                    <article class="guide-card"><h3>Cópia da conversa</h3><p>O protocolo identifica o atendimento. A administração pode localizar o chat encerrado e gerar uma cópia em PDF.</p></article>
                    <article class="guide-card"><h3>Anexos</h3><p>O chat aceita imagens JPEG, PNG e WebP, além de PDF. Evite enviar dados sensíveis sem necessidade.</p></article>
                </div>
            </section>

            <section id="rotinas" class="guide-section" data-keywords="rotina diária semanal mensal checklist segurança">
                <div class="section-title"><div class="icon"><i class="fas fa-calendar-check"></i></div><div><h2>Rotina recomendada</h2><p>Um ciclo simples para manter dados e caixa confiáveis.</p></div></div>
                <table class="routine-table">
                    <thead><tr><th>Frequência</th><th>O que fazer</th><th>Objetivo</th></tr></thead>
                    <tbody>
                        <tr><td>Início do dia</td><td>Abrir o caixa e informar o saldo inicial de dinheiro.</td><td>Começar o PDV com valor conhecido.</td></tr>
                        <tr><td>Durante o dia</td><td>Registrar vendas, compras, pagamentos, recebimentos, reforços e retiradas no momento em que acontecem.</td><td>Evitar esquecimentos e divergências.</td></tr>
                        <tr><td>Fim do dia</td><td>Conferir saldo inicial, movimentos, formas de pagamento e fechar o caixa.</td><td>Encerrar formalmente a operação.</td></tr>
                        <tr><td>Semanal</td><td>Revisar o resumo da IA, contas vencidas, estoque mínimo, cadastros incompletos e permissões.</td><td>Reduzir atrasos e falhas.</td></tr>
                        <tr><td>Início de cada ciclo</td><td>Criar a nova meta e arquivar automaticamente a anterior.</td><td>Manter objetivos e histórico claros.</td></tr>
                        <tr><td>Mensal</td><td>Conferir relatórios, dados fiscais, assinatura e exportações necessárias.</td><td>Apoiar decisões e obrigações.</td></tr>
                    </tbody>
                </table>
                <div class="note ok"><strong>Regra de ouro:</strong> registre cada operação uma única vez, confira antes de confirmar e faça o fechamento do caixa todos os dias em que ele for aberto.</div>
                <a class="guide-link" href="home.php"><i class="fas fa-home"></i> Voltar ao dashboard</a>
            </section>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('tutorialSearch');
    const sections = Array.from(document.querySelectorAll('.guide-section'));
    const links = Array.from(document.querySelectorAll('.tutorial-nav a'));
    const empty = document.getElementById('searchEmpty');
    const status = document.getElementById('tutorialSearchStatus');

    const normalize = value => value
        .toLocaleLowerCase('pt-BR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    function filterTutorial() {
        const term = normalize(search.value.trim());
        const primaryMatches = term
            ? sections.filter(section => {
                const title = section.querySelector('h2')?.textContent || '';
                return normalize(`${section.id} ${section.dataset.keywords || ''} ${title}`).includes(term);
            })
            : [];
        const prioritizePrimary = primaryMatches.length > 0;
        let visible = 0;

        sections.forEach(section => {
            const title = section.querySelector('h2')?.textContent || '';
            const primary = normalize(`${section.id} ${section.dataset.keywords || ''} ${title}`);
            const fullText = normalize(`${section.textContent} ${section.dataset.keywords || ''}`);
            const show = !term || (prioritizePrimary ? primary.includes(term) : fullText.includes(term));
            section.classList.toggle('hidden-by-search', !show);
            const link = links.find(item => item.getAttribute('href') === `#${section.id}`);
            if (link) link.hidden = !show;
            if (show) {
                visible++;
            }
        });

        empty.classList.toggle('visible', visible === 0);
        status.textContent = term
            ? `${visible} tópico${visible === 1 ? '' : 's'} encontrado${visible === 1 ? '' : 's'}.`
            : '';
    }

    search.addEventListener('input', filterTutorial);
    search.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        document.querySelector('.guide-section:not(.hidden-by-search)')?.scrollIntoView({behavior: 'smooth', block: 'start'});
    });

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            links.forEach(link => link.classList.toggle('active', link.getAttribute('href') === '#' + entry.target.id));
        });
    }, {rootMargin: '-20% 0px -65% 0px'});
    sections.forEach(section => observer.observe(section));
});
</script>

<?php include_once '../includes/footer.php'; ?>

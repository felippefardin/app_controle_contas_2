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
.tutorial-shell{max-width:1450px;margin:24px auto 105px;padding:0 22px;color:var(--t-text);font-family:Inter,"Segoe UI",Arial,sans-serif}.tutorial-hero{background:linear-gradient(135deg,#064e70,#087ea4 52%,#0d6efd);border-radius:18px;padding:34px;box-shadow:0 16px 40px rgba(0,0,0,.28);margin-bottom:22px}.tutorial-hero h1{font-size:clamp(28px,4vw,44px);margin:0 0 9px;color:#fff}.tutorial-hero p{max-width:850px;font-size:17px;line-height:1.6;margin:0;color:#eaf8ff}.profile-chip{display:inline-flex;gap:8px;align-items:center;margin-top:18px;background:rgba(255,255,255,.16);padding:8px 13px;border-radius:999px}.tutorial-search{margin-top:22px;position:relative;max-width:720px}.tutorial-search i{position:absolute;left:16px;top:15px;color:#607080}.tutorial-search input{width:100%;box-sizing:border-box;padding:13px 15px 13px 44px;border-radius:10px;border:1px solid #c8d9e4;background:#fff;color:#17212b;font-size:16px}.tutorial-layout{display:grid;grid-template-columns:275px minmax(0,1fr);gap:24px}.tutorial-nav{position:sticky;top:90px;align-self:start;max-height:calc(100vh - 120px);overflow:auto;background:var(--t-card);border:1px solid var(--t-border);border-radius:14px;padding:15px}.tutorial-nav strong{display:block;color:var(--t-accent);padding:8px 10px 12px}.tutorial-nav a{display:flex;gap:9px;align-items:center;color:#d8dee4;text-decoration:none;padding:10px;border-radius:8px;font-size:14px}.tutorial-nav a:hover,.tutorial-nav a.active{background:#113747;color:#65d5ff}.tutorial-content{min-width:0}.guide-section{scroll-margin-top:100px;background:var(--t-card);border:1px solid var(--t-border);border-radius:14px;padding:26px;margin-bottom:22px}.guide-section.hidden-by-search{display:none}.section-title{display:flex;gap:13px;align-items:center;margin-bottom:18px}.section-title .icon{width:46px;height:46px;display:grid;place-items:center;border-radius:12px;background:#103846;color:var(--t-accent);font-size:21px}.section-title h2{margin:0;color:#fff;font-size:24px}.section-title p{margin:4px 0 0;color:var(--t-muted)}.guide-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.guide-card{background:var(--t-card2);border:1px solid var(--t-border);border-radius:11px;padding:18px}.guide-card h3{margin:0 0 10px;color:#fff;font-size:17px}.guide-card p,.guide-card li{color:#c4cbd2;line-height:1.55;font-size:14px}.guide-card ol,.guide-card ul{padding-left:21px;margin-bottom:8px}.guide-card li{margin:6px 0}.guide-link{display:inline-flex;align-items:center;gap:7px;margin-top:8px;background:#087ea4;color:#fff!important;text-decoration:none;padding:9px 12px;border-radius:7px;font-weight:700;font-size:13px}.guide-link:hover{background:#0a96c3}.note{border-left:4px solid var(--t-accent);background:#102c37;padding:12px 14px;border-radius:7px;margin:14px 0;color:#d8f5ff;line-height:1.5}.note.ok{border-color:var(--t-ok);background:#143421;color:#d9ffe5}.note.warn{border-color:var(--t-warn);background:#3d3215;color:#fff1bd}.workflow{display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;margin:16px 0}.workflow span{flex:1;min-width:130px;background:#222b31;border:1px solid #3a4851;border-radius:9px;padding:13px;text-align:center;color:#e7f7ff}.workflow i{display:block;color:var(--t-accent);font-size:20px;margin-bottom:6px}.routine-table{width:100%;border-collapse:collapse;background:#202327}.routine-table th,.routine-table td{border-bottom:1px solid #3b4045;padding:12px;text-align:left;vertical-align:top}.routine-table th{color:var(--t-accent)}.search-empty{display:none;text-align:center;padding:35px;background:var(--t-card);border-radius:12px;color:var(--t-muted)}.search-empty.visible{display:block}@media(max-width:980px){.tutorial-layout{grid-template-columns:1fr}.tutorial-nav{position:static;max-height:none}.tutorial-nav div{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.tutorial-shell{padding:0 12px}.tutorial-hero,.guide-section{padding:20px}.guide-grid{grid-template-columns:1fr}.tutorial-nav div{grid-template-columns:1fr}.routine-table{display:block;overflow:auto}}
</style>

<div class="tutorial-shell">
    <section class="tutorial-hero">
        <h1>Manual completo do sistema</h1>
        <p>Olá, <?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?>. Use este guia para configurar a empresa, trabalhar em equipe e executar as rotinas de vendas, estoque, financeiro, fiscal e suporte com segurança.</p>
        <span class="profile-chip"><i class="fas fa-user-shield"></i> Perfil atual: <?= htmlspecialchars(ucfirst($perfil), ENT_QUOTES, 'UTF-8') ?></span>
        <div class="tutorial-search"><i class="fas fa-search"></i><input id="tutorialSearch" type="search" placeholder="Pesquisar: metas, fechamento, PIX, estoque, usuários..." autocomplete="off"></div>
    </section>

    <div class="tutorial-layout">
        <aside class="tutorial-nav" aria-label="Índice do tutorial">
            <strong><i class="fas fa-list"></i> Conteúdo</strong>
            <div>
                <a href="#primeiros-passos"><i class="fas fa-rocket"></i> Primeiros passos</a>
                <a href="#equipe"><i class="fas fa-users-cog"></i> Equipe e permissões</a>
                <a href="#cadastros"><i class="fas fa-address-book"></i> Cadastros básicos</a>
                <a href="#estoque-compras"><i class="fas fa-boxes"></i> Estoque e compras</a>
                <a href="#vendas"><i class="fas fa-cash-register"></i> Vendas e recibos</a>
                <a href="#metas"><i class="fas fa-bullseye"></i> Ciclos de metas</a>
                <a href="#caixa"><i class="fas fa-lock"></i> Fechamento de caixa</a>
                <a href="#financeiro"><i class="fas fa-wallet"></i> Financeiro</a>
                <a href="#fiscal"><i class="fas fa-file-invoice"></i> Área fiscal</a>
                <a href="#assinatura"><i class="fas fa-tags"></i> Assinatura e cupons</a>
                <a href="#lgpd"><i class="fas fa-shield-alt"></i> LGPD e documentos</a>
                <a href="#suporte"><i class="fas fa-headset"></i> Suporte</a>
                <a href="#rotinas"><i class="fas fa-calendar-check"></i> Rotinas recomendadas</a>
            </div>
        </aside>

        <main class="tutorial-content">
            <div id="searchEmpty" class="search-empty"><i class="fas fa-search"></i> Nenhum tópico encontrado. Tente outra palavra.</div>

            <section id="primeiros-passos" class="guide-section" data-keywords="inicio primeiros passos configuração empresa">
                <div class="section-title"><div class="icon"><i class="fas fa-rocket"></i></div><div><h2>Primeiros passos</h2><p>Prepare a base antes de começar a operar.</p></div></div>
                <div class="workflow"><span><i class="fas fa-building"></i>1. Dados da empresa</span><span><i class="fas fa-tags"></i>2. Categorias</span><span><i class="fas fa-university"></i>3. Bancos e PIX</span><span><i class="fas fa-users"></i>4. Clientes e fornecedores</span><span><i class="fas fa-box"></i>5. Produtos</span></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Configuração inicial</h3><ol><li>Revise seus dados no perfil.</li><li>Cadastre categorias de receita e despesa.</li><li>Cadastre contas bancárias e chaves PIX.</li><li>Inclua clientes, fornecedores e produtos.</li></ol><a class="guide-link" href="perfil.php"><i class="fas fa-user-edit"></i> Abrir perfil</a></article>
                    <article class="guide-card"><h3>Como os módulos se conectam</h3><p>Compras aumentam o estoque. Vendas reduzem o estoque e podem gerar movimentação financeira. Contas baixadas alimentam os resultados e relatórios.</p><div class="note">Cadastros completos evitam nomes “N/D”, cobranças sem e-mail e documentos fiscais incompletos.</div></article>
                </div>
            </section>

            <section id="equipe" class="guide-section" data-keywords="equipe usuários extras permissões compartilhamento administrador">
                <div class="section-title"><div class="icon"><i class="fas fa-users-cog"></i></div><div><h2>Equipe, usuários extras e permissões</h2><p>Todos trabalham na mesma empresa com acessos controlados.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Adicionar um usuário</h3><ol><li>Acesse “Usuários”.</li><li>Cadastre nome, e-mail, CPF e senha.</li><li>Escolha o nível de acesso.</li><li>Marque somente as páginas necessárias.</li></ol><a class="guide-link" href="usuarios.php"><i class="fas fa-users"></i> Gerenciar usuários</a></article>
                    <article class="guide-card"><h3>Dados compartilhados</h3><p>Produtos, clientes, fornecedores, estoque, vendas, bancos e financeiro pertencem à empresa. Usuários extras veem os mesmos registros quando possuem permissão.</p><div class="note ok">O usuário mantém sua identidade e suas permissões; apenas o conteúdo empresarial é compartilhado.</div></article>
                </div>
                <div class="note warn"><strong>Segurança:</strong> não forneça perfil administrativo para quem precisa apenas vender ou consultar. Revise as permissões sempre que a função da pessoa mudar.</div>
            </section>

            <section id="cadastros" class="guide-section" data-keywords="clientes fornecedores categorias bancos pix cadastros">
                <div class="section-title"><div class="icon"><i class="fas fa-address-book"></i></div><div><h2>Cadastros básicos</h2><p>Informações usadas em todos os outros módulos.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Clientes e fornecedores</h3><p>Informe nome, documento, telefone, e-mail e endereço. O e-mail é necessário para cobranças; os dados fiscais são importantes para notas.</p><a class="guide-link" href="cadastrar_pessoa_fornecedor.php">Abrir pessoas e fornecedores</a></article>
                    <article class="guide-card"><h3>Categorias</h3><p>Separe receitas e despesas em grupos claros, como vendas, serviços, aluguel, transporte e impostos.</p><a class="guide-link" href="categorias.php">Abrir categorias</a></article>
                    <article class="guide-card"><h3>Contas bancárias e PIX</h3><p>Cadastre os bancos e suas chaves PIX. As chaves disponíveis poderão ser incluídas nas cobranças enviadas aos clientes.</p><a class="guide-link" href="banco_cadastro.php">Abrir bancos</a></article>
                    <article class="guide-card"><h3>Boa prática</h3><p>Evite duplicar a mesma pessoa, categoria ou produto. Pesquise antes de criar um novo cadastro.</p></article>
                </div>
            </section>

            <section id="estoque-compras" class="guide-section" data-keywords="estoque produto sku ncm cfop compra fornecedor entrada quantidade">
                <div class="section-title"><div class="icon"><i class="fas fa-boxes"></i></div><div><h2>Produtos, estoque e compras</h2><p>Controle entradas, saídas, custos e níveis mínimos.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Cadastrar produto</h3><ul><li>Use um código/SKU único.</li><li>Informe preço de compra e venda.</li><li>Defina estoque atual e mínimo.</li><li>Para emissão fiscal, preencha NCM e CFOP corretamente.</li></ul><a class="guide-link" href="controle_estoque.php">Abrir estoque</a></article>
                    <article class="guide-card"><h3>Registrar compra</h3><ol><li>Selecione ou cadastre o fornecedor.</li><li>Adicione os produtos, quantidades e custos.</li><li>Confirme a compra.</li></ol><p>A confirmação aumenta o estoque, registra a movimentação e pode gerar conta a pagar.</p><a class="guide-link" href="compras.php">Registrar compra</a></article>
                </div>
                <div class="note">Confira quantidades e custos antes de confirmar. O custo informado influencia a análise de resultados.</div>
            </section>

            <section id="vendas" class="guide-section" data-keywords="vendas pdv recibo desconto cliente pagamento pix cartão receber cancelar">
                <div class="section-title"><div class="icon"><i class="fas fa-cash-register"></i></div><div><h2>Vendas no PDV e recibos</h2><p>Do atendimento do cliente à comprovação da venda.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Realizar uma venda</h3><ol><li>Selecione o cliente.</li><li>Adicione produtos e quantidades.</li><li>Informe desconto, se houver.</li><li>Escolha dinheiro, PIX, cartão ou “A receber”.</li><li>Finalize e gere o recibo.</li></ol><a class="guide-link" href="vendas.php">Abrir PDV</a></article>
                    <article class="guide-card"><h3>O que acontece ao finalizar</h3><ul><li>O estoque é reduzido.</li><li>A venda entra no fechamento do dia.</li><li>“A receber” gera o lançamento financeiro correspondente.</li><li>O valor entra no ciclo de meta ativo.</li></ul></article>
                    <article class="guide-card"><h3>Recibo</h3><p>O recibo apresenta cliente, produtos, valores, desconto e forma de pagamento. Use “Imprimir Recibo” para imprimir ou salvar como PDF pelo navegador.</p></article>
                    <article class="guide-card"><h3>Cancelamento</h3><p>Na tela de fechamento, abra a venda e solicite o cancelamento. O sistema devolve os itens ao estoque e remove os lançamentos vinculados quando aplicável.</p></article>
                </div>
            </section>

            <section id="metas" class="guide-section" data-keywords="meta metas vendas ciclo histórico objetivo equipe parabéns">
                <div class="section-title"><div class="icon"><i class="fas fa-bullseye"></i></div><div><h2>Ciclos de metas de vendas</h2><p>Acompanhe objetivos sem interromper a contagem quando forem atingidos.</p></div></div>
                <div class="workflow"><span><i class="fas fa-plus"></i>Criar meta</span><span><i class="fas fa-chart-line"></i>Somar vendas</span><span><i class="fas fa-trophy"></i>Atingir e continuar</span><span><i class="fas fa-archive"></i>Nova meta arquiva a anterior</span></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Nova meta</h3><p>No PDV, clique em “Nova meta” e informe o valor. Se já houver uma meta ativa, ela será encerrada com o total alcançado e guardada no histórico.</p></article>
                    <article class="guide-card"><h3>Depois de atingir</h3><p>O percentual pode ultrapassar 100%, o excedente continua crescendo e a equipe recebe uma mensagem de parabéns uma única vez por ciclo.</p></article>
                    <article class="guide-card"><h3>Histórico</h3><p>O botão “Histórico” mostra meta, realizado, percentual, início, data da conquista, encerramento e responsável pela criação.</p><a class="guide-link" href="vendas.php">Consultar metas no PDV</a></article>
                    <article class="guide-card"><h3>Ciclo recomendado</h3><p>Crie uma meta com prazo interno definido pela empresa. Não crie outra somente porque atingiu a atual; deixe o excedente acumular até o início do próximo ciclo planejado.</p></article>
                </div>
            </section>

            <section id="caixa" class="guide-section" data-keywords="caixa fechamento fechar lembrete vendas dia imprimir conferir">
                <div class="section-title"><div class="icon"><i class="fas fa-lock"></i></div><div><h2>Fechamento de caixa</h2><p>Confirme formalmente os totais do PDV ao terminar o dia.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Como fechar</h3><ol><li>Abra “Fechamento de Caixa”.</li><li>Selecione a data.</li><li>Confira formas de pagamento, total e vendas.</li><li>Clique em “Confirmar fechamento do caixa”.</li></ol><a class="guide-link" href="fechamento_caixa.php">Abrir fechamento</a></article>
                    <article class="guide-card"><h3>Lembrete automático</h3><p>Se houver vendas sem fechamento, o sistema avisa após as 18h ou no acesso seguinte. O aviso desaparece após a confirmação.</p><div class="note warn">Se ocorrer uma nova venda depois de fechar, o dia volta a ficar pendente e deve ser confirmado novamente.</div></article>
                </div>
            </section>

            <section id="financeiro" class="guide-section" data-keywords="financeiro contas pagar receber baixar cobrança email pix banco vencimento">
                <div class="section-title"><div class="icon"><i class="fas fa-wallet"></i></div><div><h2>Financeiro</h2><p>Controle obrigações, recebimentos e cobranças.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Contas a pagar</h3><p>Registre fornecedor, categoria, documento, descrição, valor e vencimento. Clique em baixar somente quando o pagamento acontecer.</p><a class="guide-link" href="contas_pagar.php">Abrir contas a pagar</a></article>
                    <article class="guide-card"><h3>Contas a receber</h3><p>Registre o cliente e acompanhe o vencimento. A baixa confirma que o valor foi recebido.</p><a class="guide-link" href="contas_receber.php">Abrir contas a receber</a></article>
                    <article class="guide-card"><h3>Enviar cobrança</h3><p>Vincule um cliente com e-mail válido, escolha uma chave PIX cadastrada, escreva a mensagem e anexe um arquivo quando necessário.</p></article>
                    <article class="guide-card"><h3>Pendente x baixada</h3><p>Cadastrar uma conta não significa que ela foi paga ou recebida. Use a ação de baixa para concluir e o estorno quando precisar desfazer a baixa.</p></article>
                </div>
            </section>

            <section id="fiscal" class="guide-section" data-keywords="fiscal nfce nfe nota danfe homologação produção certificado csc ncm cfop">
                <div class="section-title"><div class="icon"><i class="fas fa-file-invoice"></i></div><div><h2>Área fiscal e NFC-e</h2><p>Prepare os dados em teste antes de uma emissão real.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Configuração necessária</h3><ul><li>CNPJ, razão social, endereço, IE e regime tributário.</li><li>Certificado digital A1 e senha.</li><li>CSC e identificador do CSC para NFC-e.</li><li>NCM, CFOP e tributação dos produtos.</li></ul><a class="guide-link" href="configuracao_fiscal.php">Abrir configuração fiscal</a></article>
                    <article class="guide-card"><h3>Homologação e produção</h3><p>Homologação serve para testes e não produz documento fiscal com validade comercial. Produção exige credenciamento e configuração fiscal revisada por profissional responsável.</p><div class="note warn">Antes de emitir documentos reais, valide regras e impostos com sua contabilidade.</div></article>
                    <article class="guide-card"><h3>DANFE</h3><p>O DANFE fica disponível quando existe uma nota fiscal gerada com os dados necessários. Recibo de venda não substitui nota fiscal.</p></article>
                    <article class="guide-card"><h3>Antes de emitir</h3><p>Confira cliente, produtos, quantidades, valores e tributação. Correções posteriores podem exigir cancelamento ou procedimento fiscal específico.</p></article>
                </div>
            </section>

            <section id="assinatura" class="guide-section" data-keywords="assinatura plano cupom desconto indique ganhe interno cliente ranking">
                <div class="section-title"><div class="icon"><i class="fas fa-tags"></i></div><div><h2>Assinatura, planos e cupons</h2><p>Recursos comerciais vinculados à conta da empresa.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Planos e usuários extras</h3><p>O plano define os recursos e a quantidade disponível. Usuários extras comprados podem ser cadastrados e receber permissões individuais.</p><a class="guide-link" href="assinar.php">Ver assinatura</a></article>
                    <article class="guide-card"><h3>Cupons</h3><p>Cupons públicos são usados no cadastro ou assinatura conforme suas regras. Cupons internos são direcionados a clientes selecionados pela administração.</p></article>
                    <article class="guide-card"><h3>Indique e ganhe</h3><p>O código de indicação identifica quem recomendou o novo cadastro. O ranking depende do registro correto do código e das condições definidas na campanha.</p></article>
                    <article class="guide-card"><h3>Histórico financeiro</h3><p>Consulte cobranças e pagamentos vinculados à assinatura na área de histórico.</p><a class="guide-link" href="historico_pagamento.php">Ver histórico</a></article>
                </div>
            </section>

            <section id="lgpd" class="guide-section" data-keywords="lgpd privacidade consentimento assinatura documento registro proteção dados">
                <div class="section-title"><div class="icon"><i class="fas fa-shield-alt"></i></div><div><h2>Proteção de dados e documentos</h2><p>Registros de consentimento e responsabilidades da empresa.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Consentimento no cadastro</h3><p>O aceite de proteção de dados registra usuário, documento, data, origem e arquivo correspondente quando o cadastro é concluído.</p><a class="guide-link" href="protecao_de_dados.php">Ler proteção de dados</a></article>
                    <article class="guide-card"><h3>Boas práticas</h3><ul><li>Conceda apenas os acessos necessários.</li><li>Não compartilhe senhas.</li><li>Mantenha dados de clientes atualizados.</li><li>Exporte ou exclua informações somente com autorização.</li></ul></article>
                </div>
            </section>

            <section id="suporte" class="guide-section" data-keywords="suporte chat interno externo protocolo conversa pdf whatsapp email logs">
                <div class="section-title"><div class="icon"><i class="fas fa-headset"></i></div><div><h2>Suporte e protocolos</h2><p>Escolha o canal correto e guarde o número do atendimento.</p></div></div>
                <div class="guide-grid">
                    <article class="guide-card"><h3>Suporte interno</h3><p>É destinado a assinantes autenticados. O chat online atualiza mensagens automaticamente e gera protocolo ao encerrar.</p><a class="guide-link" href="solicitar_meu_suporte.php">Solicitar suporte</a></article>
                    <article class="guide-card"><h3>Suporte externo</h3><p>Atende pessoas sem assinatura ou sem acesso à conta. A resposta pode seguir pelo canal informado, como e-mail ou WhatsApp.</p><a class="guide-link" href="suporte.php">Abrir página de suporte</a></article>
                    <article class="guide-card"><h3>Cópia da conversa</h3><p>O protocolo identifica o atendimento. A administração pode localizar chats encerrados nos Logs de Chat e gerar o histórico em PDF.</p></article>
                    <article class="guide-card"><h3>Anexos</h3><p>O chat aceita imagens JPEG, PNG e WebP, além de PDF. Evite enviar dados sensíveis sem necessidade.</p></article>
                </div>
            </section>

            <section id="rotinas" class="guide-section" data-keywords="rotina diária semanal mensal checklist segurança backup">
                <div class="section-title"><div class="icon"><i class="fas fa-calendar-check"></i></div><div><h2>Rotina recomendada</h2><p>Um ciclo simples para manter os dados confiáveis.</p></div></div>
                <table class="routine-table">
                    <thead><tr><th>Frequência</th><th>O que fazer</th><th>Por quê</th></tr></thead>
                    <tbody>
                        <tr><td>Durante o dia</td><td>Registrar vendas, compras, pagamentos e recebimentos no momento em que acontecem.</td><td>Evita esquecimento e divergência.</td></tr>
                        <tr><td>Fim do dia</td><td>Conferir vendas e confirmar o fechamento do caixa.</td><td>Garante que o PDV foi revisado.</td></tr>
                        <tr><td>Semanal</td><td>Revisar contas vencidas, estoque mínimo, cadastros incompletos e permissões.</td><td>Reduz atrasos e falhas operacionais.</td></tr>
                        <tr><td>Início de cada ciclo</td><td>Arquivar a meta anterior criando uma nova meta de vendas.</td><td>Mantém histórico e objetivos claros.</td></tr>
                        <tr><td>Mensal</td><td>Conferir relatórios, dados fiscais, assinatura e exportações necessárias.</td><td>Apoia decisões e obrigações da empresa.</td></tr>
                    </tbody>
                </table>
                <div class="note ok"><strong>Regra de ouro:</strong> registre cada operação uma única vez, confira antes de confirmar e use as ações de baixa, fechamento e cancelamento conforme o evento real.</div>
                <a class="guide-link" href="home.php"><i class="fas fa-home"></i> Voltar à página inicial</a>
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

    search.addEventListener('input', function () {
        const term = this.value.trim().toLocaleLowerCase('pt-BR');
        let visible = 0;
        sections.forEach(section => {
            const haystack = (section.textContent + ' ' + (section.dataset.keywords || '')).toLocaleLowerCase('pt-BR');
            const show = !term || haystack.includes(term);
            section.classList.toggle('hidden-by-search', !show);
            if (show) visible++;
        });
        empty.classList.toggle('visible', visible === 0);
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

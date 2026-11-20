<?php
require_once '../includes/session_init.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestPath = strtok($_SERVER['REQUEST_URI'] ?? '/app_controle_contas_2/pages/protecao_de_dados.php', '?');
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($requestPath))), '/');

$pageTitle = 'Proteção de Dados e Privacidade | App Controle Pro';
$pageDescription = 'Entenda como o App Controle Pro trata e protege dados pessoais e como exercer seus direitos previstos na LGPD.';
$pageCanonical = $scheme . '://' . $host . $requestPath;
$pageOgImage = $scheme . '://' . $host . $basePath . '/img/dashboard-ia-financeira.png';
$pageStylesheet = '../assets/css/protecao-dados.css?v=20260804-5';
$pageBodyClass = 'privacy-page';
$homeUrl = '../index.php';
$loggedEmail = (string) ($_SESSION['email'] ?? $_SESSION['usuario_email'] ?? '');

include '../includes/header.php';
?>

<div class="privacy-shell">
    <section class="privacy-hero" aria-labelledby="privacy-title">
        <span class="privacy-kicker"><i class="fas fa-shield-alt" aria-hidden="true"></i> Privacidade e transparência</span>
        <h1 id="privacy-title">Política de Proteção de Dados</h1>
        <p class="privacy-lead">Entenda, em linguagem simples, como tratamos seus dados pessoais e como você pode exercer seus direitos previstos na Lei Geral de Proteção de Dados.</p>
        <div class="policy-meta" aria-label="Informações da política">
            <span><i class="fas fa-calendar-check" aria-hidden="true"></i> Última atualização: 3 de setembro de 2026</span>
            <span><i class="fas fa-file-lines" aria-hidden="true"></i> Versão 2.1</span>
        </div>
        <button class="privacy-primary-action js-open-lgpd" type="button">
            <i class="fas fa-paper-plane" aria-hidden="true"></i> Solicitar acesso, correção ou exclusão
        </button>
    </section>

    <section class="privacy-summary" aria-label="Resumo da política">
        <article class="summary-card">
            <span class="summary-icon"><i class="fas fa-database" aria-hidden="true"></i></span>
            <h2>Dados coletados</h2>
            <p>Dados de cadastro e informações financeiras necessárias para oferecer os recursos utilizados.</p>
            <a href="#dados">Ver detalhes <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
        </article>
        <article class="summary-card">
            <span class="summary-icon"><i class="fas fa-gears" aria-hidden="true"></i></span>
            <h2>Como usamos</h2>
            <p>Usamos as informações para acesso, organização financeira, relatórios e comunicação essencial.</p>
            <a href="#uso">Ver finalidade <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
        </article>
        <article class="summary-card">
            <span class="summary-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
            <h2>Seus direitos</h2>
            <p>Você pode pedir confirmação, acesso, correção e exclusão nos casos previstos pela LGPD.</p>
            <a href="#direitos">Conhecer direitos <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
        </article>
        <article class="summary-card">
            <span class="summary-icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
            <h2>Contato</h2>
            <p>Nosso canal de privacidade está disponível para dúvidas e solicitações relacionadas aos seus dados.</p>
            <a href="#contato">Falar conosco <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
        </article>
    </section>

    <nav class="privacy-index" aria-label="Índice da política">
        <strong>Nesta página</strong>
        <a href="#compromisso">Compromisso</a>
        <a href="#lgpd">O que é a LGPD</a>
        <a href="#dados">Dados coletados</a>
        <a href="#uso">Como usamos</a>
        <a href="#seguranca">Segurança</a>
        <a href="#cookies">Cookies</a>
        <a href="#direitos">Seus direitos</a>
        <a href="#contato">Contato</a>
    </nav>

    <article class="privacy-content">
        <section id="compromisso" class="policy-section">
            <h2><span>01</span> Nosso compromisso com sua privacidade</h2>
            <p>Sua privacidade é nossa prioridade. Esta página explica como tratamos seus dados pessoais de acordo com a <strong>Lei Geral de Proteção de Dados — LGPD (Lei nº 13.709/2018)</strong>, buscando garantir transparência, segurança e controle sobre suas informações.</p>
        </section>

        <section id="lgpd" class="policy-section">
            <h2><span>02</span> O que é a LGPD?</h2>
            <p>A LGPD é a lei brasileira que estabelece regras sobre a coleta, o armazenamento, o uso e o compartilhamento de dados pessoais. Seu objetivo é proteger os direitos de liberdade e privacidade das pessoas.</p>
        </section>

        <section id="dados" class="policy-section">
            <h2><span>03</span> Quais dados coletamos?</h2>
            <p>Para o funcionamento do <strong>App Controle Pro</strong>, tratamos as informações necessárias aos recursos utilizados pelo usuário:</p>
            <ul>
                <li><strong>Dados de cadastro:</strong> nome, e-mail e outras informações fornecidas para criar a conta, autenticar o acesso e permitir comunicações essenciais.</li>
                <li><strong>Dados financeiros:</strong> contas a pagar e receber, valores, datas e demais registros inseridos para organização e geração de relatórios.</li>
            </ul>
        </section>

        <section id="uso" class="policy-section">
            <h2><span>04</span> Como usamos as informações?</h2>
            <p>Os dados são utilizados para disponibilizar as funções contratadas, autenticar o usuário, organizar informações financeiras, gerar relatórios, enviar comunicações essenciais e atender solicitações de suporte ou de privacidade.</p>
            <p>Não comercializamos dados pessoais nem os utilizamos para finalidades incompatíveis com as informadas nesta política.</p>
        </section>

        <section id="seguranca" class="policy-section">
            <h2><span>05</span> Como protegemos seus dados?</h2>
            <p>Adotamos práticas de segurança para reduzir riscos de acesso não autorizado, alteração, perda ou destruição. As senhas são protegidas por mecanismo de hash seguro e não são armazenadas de forma reversível.</p>
        </section>

        <section id="cookies" class="policy-section">
            <h2><span>06</span> Política de cookies</h2>
            <p>Cookies são pequenos arquivos armazenados no dispositivo para permitir funções do site. Seguimos a <strong>LGPD (Lei nº 13.709/2018)</strong> e as boas práticas do <strong>Guia Orientativo Cookies e Proteção de Dados Pessoais da ANPD</strong>.</p>
            <p>Cookies estritamente necessários são usados para autenticação, segurança, proteção contra fraude e registro da sua escolha. Cookies opcionais somente são ativados após uma manifestação livre e específica. Recusar os opcionais não impede o acesso ao serviço.</p>
            <ul>
                <li><strong>PHPSESSID — necessário:</strong> mantém a sessão e protege o acesso. Duração: até o fechamento do navegador ou encerramento da sessão.</li>
                <li><strong>app_cookie_consent — necessário:</strong> registra as categorias escolhidas para que o aviso não seja repetido. Duração: 180 dias.</li>
                <li><strong>tema_preferencia — preferência:</strong> guarda o modo claro ou escuro quando autorizado. Duração: até 1 ano.</li>
                <li><strong>Análise e marketing:</strong> atualmente o App Controle Pro não utiliza cookies dessas categorias. Se forem adotados futuramente, permanecerão bloqueados até autorização.</li>
            </ul>
            <p>As bases legais aplicáveis incluem a execução do serviço e a segurança para cookies necessários (art. 7º, V e IX, conforme o caso) e o consentimento para cookies opcionais (arts. 7º, I, e 8º). As informações são apresentadas de forma clara conforme os princípios de transparência e livre acesso (arts. 6º e 9º). O consentimento pode ser revogado gratuitamente a qualquer momento.</p>
            <p><a href="https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm" target="_blank" rel="noopener noreferrer">Consultar a LGPD</a> · <a href="https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia_orientativo_cookies_e_protecao_de_dados_pessoais" target="_blank" rel="noopener noreferrer">Consultar o guia da ANPD</a></p>
            <button class="privacy-primary-action" type="button" data-cookie-settings><i class="fas fa-cookie-bite" aria-hidden="true"></i> Rever preferências de cookies</button>
        </section>

        <section id="direitos" class="policy-section">
            <h2><span>07</span> Quais são os seus direitos?</h2>
            <p>Nos termos da LGPD e observadas as hipóteses legais aplicáveis, você pode solicitar:</p>
            <ul class="rights-grid">
                <li><i class="fas fa-magnifying-glass" aria-hidden="true"></i><span><strong>Confirmação e acesso</strong> às informações tratadas.</span></li>
                <li><i class="fas fa-pen" aria-hidden="true"></i><span><strong>Correção</strong> de dados incompletos ou incorretos.</span></li>
                <li><i class="fas fa-trash-can" aria-hidden="true"></i><span><strong>Exclusão</strong> nos casos permitidos pela legislação.</span></li>
                <li><i class="fas fa-circle-info" aria-hidden="true"></i><span><strong>Informações</strong> sobre o tratamento e compartilhamento.</span></li>
            </ul>
        </section>

        <section id="contato" class="policy-section contact-section">
            <div>
                <h2><span>08</span> Fale conosco</h2>
                <p>Para exercer seus direitos ou esclarecer dúvidas sobre o tratamento dos seus dados, utilize nosso canal de privacidade.</p>
                <a href="mailto:contatotech.tecnologia@gmail.com">contatotech.tecnologia@gmail.com</a>
            </div>
            <button class="privacy-primary-action js-open-lgpd" type="button">
                <i class="fas fa-shield-alt" aria-hidden="true"></i> Fazer solicitação
            </button>
        </section>
    </article>
</div>

<div class="lgpd-modal" id="lgpdModal" hidden>
    <div class="lgpd-modal-backdrop" data-close-lgpd></div>
    <section class="lgpd-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="lgpdModalTitle">
        <button class="lgpd-modal-close" type="button" data-close-lgpd aria-label="Fechar formulário"><i class="fas fa-xmark"></i></button>
        <span class="privacy-kicker"><i class="fas fa-user-shield"></i> Solicitação de privacidade</span>
        <h2 id="lgpdModalTitle">Como podemos ajudar?</h2>
        <p>Preencha os dados abaixo. Ao enviar, você receberá um protocolo para acompanhar sua solicitação.</p>
        <form id="lgpdRequestForm" action="../actions/solicitar_lgpd.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <label for="lgpdTipo">Tipo de solicitação</label>
            <select id="lgpdTipo" name="tipo" required>
                <option value="">Selecione uma opção</option>
                <option value="acesso">Solicitar acesso aos meus dados</option>
                <option value="correcao">Solicitar correção dos meus dados</option>
                <option value="exclusao">Solicitar exclusão dos meus dados</option>
            </select>
            <div class="lgpd-form-grid">
                <div><label for="lgpdEmail">E-mail cadastrado no sistema</label><input id="lgpdEmail" name="email" type="email" maxlength="190" value="<?= htmlspecialchars($loggedEmail, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required></div>
                <div><label for="lgpdTelefone">Telefone ou WhatsApp com DDD</label><input id="lgpdTelefone" name="telefone" type="tel" maxlength="30" autocomplete="tel" placeholder="(27) 99999-9999" required></div>
            </div>
            <label for="lgpdDescricao">Descreva sua solicitação</label>
            <textarea id="lgpdDescricao" name="descricao" minlength="10" maxlength="2000" rows="5" placeholder="Explique quais dados deseja acessar, corrigir ou excluir." required></textarea>
            <div class="lgpd-character-count"><span id="lgpdCharacterCount">0</span>/2000 caracteres</div>
            <div class="lgpd-form-message" id="lgpdFormMessage" role="status" aria-live="polite"></div>
            <button class="privacy-primary-action lgpd-submit" type="submit"><i class="fas fa-paper-plane"></i> Enviar solicitação</button>
        </form>
        <div class="lgpd-success" id="lgpdSuccess" hidden>
            <span class="lgpd-success-icon"><i class="fas fa-check"></i></span>
            <h2>Solicitação registrada</h2>
            <p>Guarde o protocolo abaixo. A equipe Master recebeu sua solicitação.</p>
            <strong id="lgpdProtocol"></strong>
            <button class="privacy-primary-action" type="button" data-close-lgpd>Concluir</button>
        </div>
    </section>
</div>

<script>
(() => {
    const modal=document.getElementById('lgpdModal');
    const form=document.getElementById('lgpdRequestForm');
    const success=document.getElementById('lgpdSuccess');
    const message=document.getElementById('lgpdFormMessage');
    const description=document.getElementById('lgpdDescricao');
    const count=document.getElementById('lgpdCharacterCount');
    const firstField=document.getElementById('lgpdTipo');
    let lastTrigger=null;
    const openModal=(trigger)=>{lastTrigger=trigger;modal.hidden=false;document.body.classList.add('lgpd-modal-open');setTimeout(()=>firstField.focus(),50)};
    const closeModal=()=>{modal.hidden=true;document.body.classList.remove('lgpd-modal-open');if(!success.hidden){success.hidden=true;form.hidden=false;form.reset();count.textContent='0'}if(lastTrigger)lastTrigger.focus()};
    document.querySelectorAll('.js-open-lgpd').forEach(button=>button.addEventListener('click',()=>openModal(button)));
    document.querySelectorAll('[data-close-lgpd]').forEach(button=>button.addEventListener('click',closeModal));
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)closeModal()});
    description.addEventListener('input',()=>count.textContent=String(description.value.length));
    form.addEventListener('submit',async event=>{
        event.preventDefault(); message.className='lgpd-form-message'; message.textContent='';
        const submit=form.querySelector('button[type="submit"]'); submit.disabled=true; submit.innerHTML='<i class="fas fa-spinner fa-spin"></i> Enviando...';
        try{
            const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json'}});
            const data=await response.json();
            if(!response.ok||data.status!=='success')throw new Error(data.msg||'Não foi possível enviar.');
            form.hidden=true;success.hidden=false;document.getElementById('lgpdProtocol').textContent=data.protocolo;
        }catch(error){message.classList.add('error');message.textContent=error.message||'Não foi possível enviar a solicitação.'}
        finally{submit.disabled=false;submit.innerHTML='<i class="fas fa-paper-plane"></i> Enviar solicitação'}
    });
})();
</script>

<?php include '../includes/footer.php'; ?>

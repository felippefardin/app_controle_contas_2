(function(){
'use strict';
const COOKIE_NAME='app_cookie_consent';
const VERSION=1;
const MAX_AGE=180*24*60*60;
const scriptUrl=document.currentScript&&document.currentScript.src;
const appBase=scriptUrl?new URL('../../',scriptUrl).pathname.replace(/\/$/,''):'/app_controle_contas_2';
let lastFocus=null;

function readCookie(name){const prefix=name+'=';const item=document.cookie.split(';').map(v=>v.trim()).find(v=>v.startsWith(prefix));return item?decodeURIComponent(item.slice(prefix.length)):''}
function current(){try{const value=JSON.parse(readCookie(COOKIE_NAME));return value&&value.version===VERSION?value:null}catch(error){return null}}
function allows(category){if(category==='necessary')return true;return current()?.[category]===true}
function cookieOptions(){return ';path=/;max-age='+MAX_AGE+';SameSite=Lax'+(location.protocol==='https:'?';Secure':'')}
function expireCookie(name){document.cookie=name+'=;path=/;max-age=0;SameSite=Lax'+(location.protocol==='https:'?';Secure':'')}
function removeDisallowed(state){
    if(!state.preferences){expireCookie('tema_preferencia');try{localStorage.removeItem('app_theme');localStorage.removeItem('theme')}catch(error){}}
    if(!state.analytics){document.cookie.split(';').forEach(item=>{const name=item.split('=')[0].trim();if(/^(_ga|_gid|_gat)/.test(name))expireCookie(name)})}
    if(!state.marketing){['_gcl_au','_fbp'].forEach(expireCookie)}
}
function save(state){const value={version:VERSION,necessary:true,preferences:!!state.preferences,analytics:!!state.analytics,marketing:!!state.marketing,updatedAt:new Date().toISOString()};document.cookie=COOKIE_NAME+'='+encodeURIComponent(JSON.stringify(value))+cookieOptions();removeDisallowed(value);hideBanner();closeSettings();document.dispatchEvent(new CustomEvent('cookieconsentchange',{detail:value}));return value}
function build(){
    if(document.getElementById('cookieConsentBanner'))return;
    const banner=document.createElement('section');banner.id='cookieConsentBanner';banner.className='cookie-consent-banner';banner.hidden=true;banner.setAttribute('role','dialog');banner.setAttribute('aria-labelledby','cookieConsentTitle');banner.setAttribute('aria-describedby','cookieConsentDescription');banner.innerHTML='<div class="cookie-consent-copy"><strong id="cookieConsentTitle">Sua privacidade importa</strong><p id="cookieConsentDescription">Usamos cookies necessários para login e segurança. Com sua permissão, também podemos guardar preferências e usar cookies de medição ou marketing. Você pode recusar os opcionais sem perder o acesso. <a href="'+appBase+'/pages/protecao_de_dados.php#cookies">Política de cookies e LGPD</a>.</p></div><div class="cookie-consent-actions"><button class="cookie-button cookie-button-neutral" type="button" data-cookie-reject>Recusar opcionais</button><button class="cookie-button" type="button" data-cookie-configure>Configurar</button><button class="cookie-button cookie-button-primary" type="button" data-cookie-accept>Aceitar todos</button></div>';
    const modal=document.createElement('div');modal.id='cookieSettingsModal';modal.className='cookie-settings-modal';modal.hidden=true;modal.innerHTML='<div class="cookie-settings-backdrop" data-cookie-close></div><section class="cookie-settings-dialog" role="dialog" aria-modal="true" aria-labelledby="cookieSettingsTitle"><button class="cookie-settings-close" type="button" data-cookie-close aria-label="Fechar">&times;</button><h2 id="cookieSettingsTitle">Preferências de cookies</h2><p class="cookie-settings-intro">Escolha quais categorias autoriza. Sua decisão pode ser alterada a qualquer momento pelo botão de cookie no canto da tela.</p><label class="cookie-category"><span><strong>Necessários</strong> <span class="cookie-required">Sempre ativos</span></span><input type="checkbox" checked disabled aria-label="Cookies necessários sempre ativos"><p>Mantêm a sessão, protegem formulários e registram sua escolha de privacidade. Sem eles, o sistema não funciona com segurança.</p></label><label class="cookie-category"><strong>Preferências</strong><input id="cookiePreferences" type="checkbox"><p>Guardam escolhas feitas por você, como o tema claro ou escuro.</p></label><label class="cookie-category"><strong>Análise</strong><input id="cookieAnalytics" type="checkbox"><p>Permitem medir o uso do site para melhorar o serviço. Atualmente não usamos cookies desta categoria.</p></label><label class="cookie-category"><strong>Marketing</strong><input id="cookieMarketing" type="checkbox"><p>Permitem personalizar anúncios e medir campanhas. Atualmente não usamos cookies desta categoria.</p></label><p><a href="'+appBase+'/pages/protecao_de_dados.php#cookies">Leia finalidades, prazos e bases legais</a>.</p><div class="cookie-settings-actions"><button class="cookie-button cookie-button-neutral" type="button" data-cookie-reject>Recusar opcionais</button><button class="cookie-button cookie-button-primary" type="button" data-cookie-save>Salvar escolhas</button></div></section>';
    const trigger=document.createElement('button');trigger.id='cookiePreferencesTrigger';trigger.className='cookie-preferences-trigger';trigger.type='button';trigger.hidden=true;trigger.title='Rever preferências de cookies';trigger.setAttribute('aria-label','Rever preferências de cookies');trigger.textContent='🍪';
    document.body.append(banner,modal,trigger);
    const actionButtons=document.querySelector('.action-buttons');
    const toolsColumn=document.querySelector('.floating-tools-column');
    const publicTools=document.querySelector('.public-floating-tools');
    if(toolsColumn){
        const support=toolsColumn.querySelector('.btn-support-login');
        support?toolsColumn.insertBefore(trigger,support):toolsColumn.appendChild(trigger);
    }else if(publicTools){
        const feedback=publicTools.querySelector('.feedback-public-trigger');
        feedback?publicTools.insertBefore(trigger,feedback):publicTools.prepend(trigger);
    }else if(actionButtons&&getComputedStyle(actionButtons).display!=='none'){
        trigger.classList.add('cookie-trigger-in-actions');
        actionButtons.appendChild(trigger);
    }else if(document.querySelector('.btn-support-login')){
        trigger.classList.add('cookie-trigger-beside-support');
    }
    banner.querySelector('[data-cookie-accept]').addEventListener('click',()=>save({preferences:true,analytics:true,marketing:true}));
    document.querySelectorAll('[data-cookie-reject]').forEach(button=>button.addEventListener('click',()=>save({preferences:false,analytics:false,marketing:false})));
    banner.querySelector('[data-cookie-configure]').addEventListener('click',openSettings);
    modal.querySelector('[data-cookie-save]').addEventListener('click',()=>save({preferences:modal.querySelector('#cookiePreferences').checked,analytics:modal.querySelector('#cookieAnalytics').checked,marketing:modal.querySelector('#cookieMarketing').checked}));
    modal.querySelectorAll('[data-cookie-close]').forEach(button=>button.addEventListener('click',closeSettings));
    trigger.addEventListener('click',openSettings);
    document.querySelectorAll('[data-cookie-settings]').forEach(button=>button.addEventListener('click',openSettings));
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)closeSettings()});
}
function hideBanner(){const banner=document.getElementById('cookieConsentBanner');const trigger=document.getElementById('cookiePreferencesTrigger');if(banner)banner.hidden=true;if(trigger)trigger.hidden=false}
function openSettings(event){if(event?.preventDefault)event.preventDefault();const modal=document.getElementById('cookieSettingsModal');if(!modal)return;lastFocus=document.activeElement;const state=current()||{};modal.querySelector('#cookiePreferences').checked=!!state.preferences;modal.querySelector('#cookieAnalytics').checked=!!state.analytics;modal.querySelector('#cookieMarketing').checked=!!state.marketing;modal.hidden=false;document.body.classList.add('cookie-modal-open');setTimeout(()=>modal.querySelector('.cookie-settings-close').focus(),20)}
function closeSettings(){const modal=document.getElementById('cookieSettingsModal');if(!modal)return;modal.hidden=true;document.body.classList.remove('cookie-modal-open');if(lastFocus&&lastFocus.focus)lastFocus.focus()}
function init(){build();if(current())hideBanner();else document.getElementById('cookieConsentBanner').hidden=false}
window.AppCookieConsent={allows,current,open:openSettings,save};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();

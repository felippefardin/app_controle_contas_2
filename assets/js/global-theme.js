(function(){
'use strict';
const root=document.documentElement;
const appBase='/app_controle_contas_2';
if(/\/pages\/admin(?:\/|$)/i.test(window.location.pathname))root.classList.add('app-admin-area');
const themeScriptUrl=document.currentScript&&document.currentScript.src;
function ensureFavicon(){if(document.querySelector('link[rel~="icon"]'))return;const link=document.createElement('link');link.rel='icon';link.type='image/svg+xml';link.href=themeScriptUrl?new URL('../favicon.svg',themeScriptUrl).href:appBase+'/assets/favicon.svg';document.head.appendChild(link)}
function readCookie(name){const prefix=name+'=';const item=document.cookie.split(';').map(v=>v.trim()).find(v=>v.startsWith(prefix));return item?decodeURIComponent(item.slice(prefix.length)):''}
function preferenceConsent(){try{const consent=JSON.parse(readCookie('app_cookie_consent'));return consent&&consent.version===1&&consent.preferences===true}catch(error){return false}}
function valid(value){return value==='dark'||value==='light'}
function preferred(){const rendered=root.dataset.appTheme;if(valid(rendered))return rendered;if(preferenceConsent()){const cookie=readCookie('tema_preferencia');if(valid(cookie))return cookie;const stored=localStorage.getItem('app_theme')||localStorage.getItem('theme');if(valid(stored))return stored}return window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'}
function themeSymbol(theme){return theme==='dark'?'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4" fill="currentColor"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>':'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.2 15.2A8.2 8.2 0 0 1 8.8 3.8 8.6 8.6 0 1 0 20.2 15.2Z" fill="currentColor"/></svg>'}
function icons(theme){document.querySelectorAll('#themeToggle i,#darkModeToggle i,[data-theme-icon]').forEach(icon=>{const bootstrap=icon.className.includes('bi ');icon.className=bootstrap?'bi '+(theme==='dark'?'bi-sun-fill':'bi-moon-stars-fill'):'fas '+(theme==='dark'?'fa-sun':'fa-moon')});document.querySelectorAll('[data-theme-symbol]').forEach(symbol=>{symbol.innerHTML=themeSymbol(theme)});document.querySelectorAll('[data-theme-label]').forEach(label=>{label.textContent=theme==='dark'?'Modo claro':'Modo escuro'});document.querySelectorAll('.global-theme-toggle').forEach(button=>{const target=theme==='dark'?'claro':'escuro';button.setAttribute('aria-label','Ativar modo '+target);button.title='Ativar modo '+target})}
function apply(theme,persist){if(!valid(theme))theme='dark';root.dataset.appTheme=theme;if(document.body){document.body.classList.toggle('dark-mode',theme==='dark');document.body.classList.toggle('light-mode',theme==='light')}icons(theme);if(!persist)return;if(preferenceConsent()){localStorage.setItem('app_theme',theme);localStorage.setItem('theme',theme);document.cookie='tema_preferencia='+encodeURIComponent(theme)+';max-age=31536000;path=/;SameSite=Lax'+(location.protocol==='https:'?';Secure':'')}const body=new FormData();body.append('tema',theme);fetch(appBase+'/actions/salvar_tema.php',{method:'POST',body,credentials:'same-origin'}).catch(()=>{})}
function toggle(){apply(root.dataset.appTheme==='dark'?'light':'dark',true)}
ensureFavicon();
apply(preferred(),false);
window.appTheme={apply,toggle,current:()=>root.dataset.appTheme};
document.addEventListener('DOMContentLoaded',()=>{apply(root.dataset.appTheme||preferred(),false);const existing=document.getElementById('themeToggle')||document.getElementById('darkModeToggle');if(existing){if(!existing.hasAttribute('onclick'))existing.addEventListener('click',toggle);icons(root.dataset.appTheme);return}const button=document.createElement('button');button.type='button';button.className='global-theme-toggle';button.innerHTML='<span data-theme-symbol aria-hidden="true"></span>';button.addEventListener('click',toggle);const toolsColumn=document.querySelector('.floating-tools-column');if(toolsColumn)toolsColumn.appendChild(button);else document.body.appendChild(button);icons(root.dataset.appTheme)});
})();

// Aviso e central de preferências de cookies em todas as páginas que usam o tema global.
(() => {
    const source = document.currentScript && document.currentScript.src;
    if (!source) return;
    if (!document.querySelector('link[href*="cookie-consent.css"]')) {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = new URL('../css/cookie-consent.css?v=20260903-6', source).href;
        document.head.appendChild(stylesheet);
    }
    if (!document.querySelector('script[src*="cookie-consent.js"]')) {
        const script = document.createElement('script');
        script.src = new URL('cookie-consent.js?v=20260903-6', source).href;
        document.head.appendChild(script);
    }
})();

// Disponibiliza o painel de acessibilidade também nas páginas independentes
// que carregam apenas o tema global (login, registro, suporte e recuperação).
(() => {
    const source = document.currentScript && document.currentScript.src;
    if (!source) return;

    if (!document.querySelector('link[href*="accessibility-widget.css"]')) {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = new URL('../css/accessibility-widget.css?v=20260903-column-2', source).href;
        document.head.appendChild(stylesheet);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.body.setAttribute('data-accessibility-widget', '');
        if (document.querySelector('script[src*="accessibility-widget.js"]')) return;

        const script = document.createElement('script');
        script.src = new URL('accessibility-widget.js?v=20260903-column-2', source).href;
        document.body.appendChild(script);
    });
})();

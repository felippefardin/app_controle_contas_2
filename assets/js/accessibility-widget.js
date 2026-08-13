(() => {
    'use strict';

    if (window.__appAccessibilityWidgetLoaded) return;
    window.__appAccessibilityWidgetLoaded = true;

    const root = document.documentElement;
    const storageKey = 'app_accessibility_preferences';
    const defaults = { font: 0, contrast: false, links: false, motion: false, guide: false, cursor: false };
    const fontSteps = [50, 62.5, 75, 87.5, 100, 112.5, 125, 137.5, 150];
    let preferences = { ...defaults };
    let guide = null;
    let speaking = false;

    try {
        preferences = { ...defaults, ...JSON.parse(localStorage.getItem(storageKey) || '{}') };
    } catch (_) {}

    function save() {
        localStorage.setItem(storageKey, JSON.stringify(preferences));
    }

    function ensureGuide() {
        if (!guide) {
            guide = document.createElement('div');
            guide.className = 'a11y-reading-guide';
            guide.setAttribute('aria-hidden', 'true');
            document.body.appendChild(guide);
        }
        guide.hidden = !preferences.guide;
    }

    function apply() {
        preferences.font = Math.max(-4, Math.min(4, Number(preferences.font) || 0));
        const fontPercent = fontSteps[preferences.font + 4];
        root.dataset.a11yFontSize = String(fontPercent);
        root.style.setProperty('--a11y-font-size', `${fontPercent}%`);
        root.classList.toggle('a11y-high-contrast', preferences.contrast);
        root.classList.toggle('a11y-highlight-links', preferences.links);
        root.classList.toggle('a11y-reduce-motion', preferences.motion);
        root.classList.toggle('a11y-large-cursor', preferences.cursor);
        ensureGuide();

        if (preferences.motion) {
            if (typeof document.getAnimations === 'function') {
                document.getAnimations().forEach(animation => animation.cancel());
            }
            document.querySelectorAll('video[autoplay], audio[autoplay]').forEach(media => media.pause());
        }
    }

    function announce(message) {
        const status = document.getElementById('a11yStatus');
        if (status) status.textContent = message;
    }

    function button(id, icon, label, hint) {
        return `<button type="button" class="a11y-option" id="${id}" aria-pressed="false"><i class="fas ${icon}" aria-hidden="true"></i><span>${label}</span><small>${hint}</small></button>`;
    }

    function syncButtons() {
        const states = {
            a11yContrast: preferences.contrast,
            a11yLinks: preferences.links,
            a11yMotion: preferences.motion,
            a11yGuide: preferences.guide,
            a11yCursor: preferences.cursor,
            a11ySpeech: speaking
        };
        Object.entries(states).forEach(([id, active]) => {
            document.getElementById(id)?.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const fontPercent = fontSteps[preferences.font + 4];
        const fontDownHint = document.querySelector('#a11yFontDown small');
        const fontUpHint = document.querySelector('#a11yFontUp small');
        if (fontDownHint) fontDownHint.textContent = `Atual: ${fontPercent}%`;
        if (fontUpHint) fontUpHint.textContent = `Atual: ${fontPercent}%`;
        const fontDown = document.getElementById('a11yFontDown');
        const fontUp = document.getElementById('a11yFontUp');
        if (fontDown) fontDown.disabled = preferences.font <= -4;
        if (fontUp) fontUp.disabled = preferences.font >= 4;
        const speechLabel = document.querySelector('#a11ySpeech span');
        if (speechLabel) speechLabel.textContent = speaking ? 'Parar leitura' : 'Ouvir a página';
    }

    function togglePreference(key, messageOn, messageOff) {
        preferences[key] = !preferences[key];
        save();
        apply();
        syncButtons();
        announce(preferences[key] ? messageOn : messageOff);
    }

    function toggleSpeech() {
        if (!('speechSynthesis' in window)) {
            announce('A leitura em voz alta não é compatível com este navegador.');
            return;
        }
        if (speaking) {
            speechSynthesis.cancel();
            speaking = false;
            syncButtons();
            announce('Leitura interrompida.');
            return;
        }
        const main = document.querySelector('main, .home-container');
        const text = (main?.innerText || '').replace(/\s+/g, ' ').trim();
        if (!text) return;
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'pt-BR';
        utterance.rate = .95;
        utterance.onend = utterance.onerror = () => { speaking = false; syncButtons(); };
        speaking = true;
        syncButtons();
        announce('Leitura da página iniciada.');
        speechSynthesis.speak(utterance);
    }

    function init() {
        if (!document.body?.hasAttribute('data-accessibility-widget')) return;

        const launcher = document.createElement('button');
        launcher.type = 'button';
        launcher.className = 'a11y-launcher';
        launcher.setAttribute('aria-label', 'Abrir recursos de acessibilidade');
        launcher.setAttribute('aria-expanded', 'false');
        launcher.setAttribute('aria-controls', 'a11yPanel');
        launcher.innerHTML = '<svg class="a11y-launcher-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="4" r="2.2" fill="currentColor"/><path d="M4 7.5c0-1 .8-1.8 1.8-1.8h12.4a1.8 1.8 0 0 1 0 3.6h-4.1v3.1l2.3 7.1a1.8 1.8 0 0 1-3.4 1.1L12 17.3l-1 3.3a1.8 1.8 0 0 1-3.4-1.1l2.3-7.1V9.3H5.8C4.8 9.3 4 8.5 4 7.5Z" fill="currentColor"/></svg>';

        const panel = document.createElement('section');
        panel.id = 'a11yPanel';
        panel.className = 'a11y-panel';
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-labelledby', 'a11yTitle');
        panel.innerHTML = `
            <div class="a11y-panel-head">
                <div><h2 class="a11y-panel-title" id="a11yTitle">Acessibilidade</h2><p class="a11y-panel-subtitle">Ative somente os recursos de que precisa.</p></div>
                <button type="button" class="a11y-close" aria-label="Fechar recursos de acessibilidade"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="a11y-options">
                ${button('a11yFontDown', 'fa-font', 'Diminuir texto', 'Atual: 100%')}
                ${button('a11yFontUp', 'fa-text-height', 'Aumentar texto', 'Atual: 100%')}
                ${button('a11yContrast', 'fa-circle-half-stroke', 'Alto contraste', 'Reforçar cores')}
                ${button('a11yLinks', 'fa-link', 'Destacar links', 'Sublinhar atalhos')}
                ${button('a11yMotion', 'fa-person-walking', 'Reduzir movimentos', 'Pausar animações')}
                ${button('a11yGuide', 'fa-ruler-horizontal', 'Guia de leitura', 'Acompanhar a linha')}
                ${button('a11yCursor', 'fa-arrow-pointer', 'Cursor maior', 'Facilitar localização')}
                ${button('a11ySpeech', 'fa-volume-high', 'Ouvir a página', 'Leitura em voz alta')}
            </div>
            <button type="button" class="a11y-reset" id="a11yReset"><i class="fas fa-rotate-left" aria-hidden="true"></i> Restaurar acessibilidade</button>
            <p class="a11y-status" id="a11yStatus" aria-live="polite"></p>`;

        document.body.append(panel, launcher);
        apply();
        syncButtons();

        const close = () => {
            panel.hidden = true;
            launcher.setAttribute('aria-expanded', 'false');
            launcher.focus();
        };
        launcher.addEventListener('click', () => {
            const opening = panel.hidden;
            panel.hidden = !opening;
            launcher.setAttribute('aria-expanded', opening ? 'true' : 'false');
            if (opening) panel.querySelector('.a11y-close')?.focus();
        });
        panel.querySelector('.a11y-close')?.addEventListener('click', close);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) close();
        });
        document.addEventListener('mousemove', event => {
            if (preferences.guide && guide) guide.style.top = `${Math.max(0, event.clientY - 17)}px`;
        });
        document.getElementById('a11yFontDown').addEventListener('click', () => {
            preferences.font = Math.max(-4, preferences.font - 1);
            save(); apply(); syncButtons();
            announce(`Tamanho do texto reduzido para ${fontSteps[preferences.font + 4]}%.`);
        });
        document.getElementById('a11yFontUp').addEventListener('click', () => {
            preferences.font = Math.min(4, preferences.font + 1);
            save(); apply(); syncButtons();
            announce(`Tamanho do texto aumentado para ${fontSteps[preferences.font + 4]}%.`);
        });
        document.getElementById('a11yContrast').addEventListener('click', () => togglePreference('contrast', 'Alto contraste ativado.', 'Alto contraste desativado.'));
        document.getElementById('a11yLinks').addEventListener('click', () => togglePreference('links', 'Links destacados.', 'Destaque de links desativado.'));
        document.getElementById('a11yMotion').addEventListener('click', () => togglePreference('motion', 'Movimentos reduzidos.', 'Movimentos restaurados.'));
        document.getElementById('a11yGuide').addEventListener('click', () => togglePreference('guide', 'Guia de leitura ativada.', 'Guia de leitura desativada.'));
        document.getElementById('a11yCursor').addEventListener('click', () => togglePreference('cursor', 'Cursor maior ativado.', 'Cursor maior desativado.'));
        document.getElementById('a11ySpeech').addEventListener('click', toggleSpeech);
        document.getElementById('a11yReset').addEventListener('click', () => {
            if ('speechSynthesis' in window) speechSynthesis.cancel();
            speaking = false;
            preferences = { ...defaults };
            save(); apply(); syncButtons();
            announce('Todos os recursos adicionais foram restaurados.');
        });
    }

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();

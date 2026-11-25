(() => {
    'use strict';

    const script = document.currentScript;
    const endpoint = new URL('../../actions/atividade_sessao.php', script.src);
    const login = new URL('../../pages/login.php?erro=inatividade', script.src);
    const activityInterval = 5000;
    let deadline = Date.now() + Number(script.dataset.remaining) * 1000;
    let expiryTimer;
    let activityTimer;
    let lastSent = -activityInterval;
    let pending = false;
    let busy = false;
    let stopped = false;

    function scheduleExpiry(delay = deadline - Date.now()) {
        clearTimeout(expiryTimer);
        expiryTimer = setTimeout(() => sync(false), Math.max(0, delay));
    }

    function scheduleActivity() {
        if (activityTimer || busy || stopped || !pending) return;

        const delay = Math.max(0, activityInterval - (performance.now() - lastSent));
        activityTimer = setTimeout(() => {
            activityTimer = undefined;
            sync(true);
        }, delay);
    }

    async function sync(recordActivity) {
        if (busy || stopped) return;
        busy = true;

        if (recordActivity) {
            pending = false;
            lastSent = performance.now();
        }

        try {
            const response = await fetch(endpoint, {
                method: recordActivity ? 'POST' : 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-Token': script.dataset.csrf
                },
                cache: 'no-store',
                keepalive: recordActivity,
                signal: AbortSignal.timeout(8000)
            });

            if (response.status === 401 || response.status === 403) {
                stopped = true;
                location.replace(login);
                return;
            }
            if (!response.ok) throw new Error('Falha ao verificar a sessão');

            const { remaining } = await response.json();
            if (!Number.isFinite(remaining)) throw new Error('Prazo da sessão inválido');
            deadline = Date.now() + remaining * 1000;
            scheduleExpiry();
        } catch {
            scheduleExpiry(Math.max(5000, deadline - Date.now()));
        } finally {
            busy = false;
            scheduleActivity();
        }
    }

    function recordActivity(event) {
        if (!event.isTrusted || document.visibilityState !== 'visible') return;
        pending = true;
        scheduleActivity();
    }

    for (const event of ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart', 'touchmove', 'input']) {
        window.addEventListener(event, recordActivity, { capture: true, passive: true });
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') sync(false);
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) sync(false);
    });

    scheduleExpiry();
})();

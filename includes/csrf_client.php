<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<script>
(() => {
    const token = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form').forEach(form => {
            if ((form.method || 'get').toLowerCase() !== 'post' || form.querySelector('[name="csrf_token"]')) return;
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'csrf_token'; input.value = token;
            form.appendChild(input);
        });
    });
    const originalFetch = window.fetch.bind(window);
    window.fetch = (resource, options = {}) => {
        const method = String(options.method || 'GET').toUpperCase();
        const url = new URL(typeof resource === 'string' ? resource : resource.url, location.href);
        if (method !== 'GET' && url.origin === location.origin) {
            const headers = new Headers(options.headers || {});
            headers.set('X-CSRF-Token', token);
            options = {...options, headers};
        }
        return originalFetch(resource, options);
    };
})();
</script>

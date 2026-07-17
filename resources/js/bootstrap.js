import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ─── Laravel Echo + Reverb (real-time WebSocket) ──────────────────────────
// Only initialised when VITE_REVERB_APP_KEY is set in .env
if (import.meta.env.VITE_REVERB_APP_KEY) {
    import('laravel-echo').then(({ default: Echo }) => {
        import('pusher-js').then(({ default: Pusher }) => {
            window.Pusher = Pusher;
            window.Echo = new Echo({
                broadcaster:   'reverb',
                key:           import.meta.env.VITE_REVERB_APP_KEY,
                wsHost:        import.meta.env.VITE_REVERB_HOST        ?? 'localhost',
                wsPort:        import.meta.env.VITE_REVERB_PORT        ?? 8080,
                wssPort:       import.meta.env.VITE_REVERB_PORT        ?? 8080,
                forceTLS:      (import.meta.env.VITE_REVERB_SCHEME     ?? 'http') === 'https',
                enabledTransports: ['ws', 'wss'],
            });
        });
    });
}

// ─── MSW Mock Service Worker (marketplace dev mode) ───────────────────────
// Only active when VITE_MOCK_MARKETPLACE=true in .env.local
if (import.meta.env.VITE_MOCK_MARKETPLACE === 'true') {
    import('./mocks/browser.js').then(({ startMockServiceWorker }) => {
        startMockServiceWorker()
    })
}

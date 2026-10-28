/*
 * LENDR borrower PWA service worker.
 * Served by PwaAssetController from /app/sw.js (scope /app/) — not bundled by Vite.
 *
 *   /build/*      hashed assets       → cache-first (they never change)
 *   /api/v1/me/*  the borrower's data → network-first, cached for offline viewing
 *   /app/*        PWA pages           → network-first, cached for offline viewing
 *
 * Cached borrower data is wiped on logout (see stores/auth.js → 'clear-cache').
 */
const VERSION = 'lendr-pwa-v1'
const CACHES = {
    assets: `${VERSION}-assets`,
    pages: `${VERSION}-pages`,
    data: `${VERSION}-data`,
}

self.addEventListener('install', () => self.skipWaiting())

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (! key.startsWith(VERSION)) await caches.delete(key)
        }
        await self.clients.claim()
    })())
})

// ─── Offline caching ──────────────────────────────────────────────────────────

self.addEventListener('fetch', (event) => {
    const request = event.request
    if (request.method !== 'GET') return

    const url = new URL(request.url)
    if (url.origin !== self.location.origin) return

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, CACHES.assets))
    } else if (url.pathname.startsWith('/api/v1/me/')) {
        event.respondWith(networkFirst(request, CACHES.data))
    } else if (url.pathname.startsWith('/app/') && ! url.pathname.endsWith('/sw.js')) {
        event.respondWith(networkFirst(request, CACHES.pages))
    }
})

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request)
    if (cached) return cached

    const response = await fetch(request)
    if (response.ok) (await caches.open(cacheName)).put(request, response.clone())
    return response
}

async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request)
        if (response.ok) (await caches.open(cacheName)).put(request, response.clone())
        return response
    } catch (error) {
        // Inertia sends `Vary: X-Inertia`, so page JSON and full HTML loads don't get mixed up here.
        const cached = await caches.match(request)
        if (cached) return cached
        if (request.mode === 'navigate') return offlinePage()
        throw error
    }
}

function offlinePage() {
    return new Response(
        `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Offline</title>
        <body style="font-family:system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px;text-align:center;color:#374151">
          <div><h1 style="font-size:20px;margin:0 0 8px">You're offline</h1>
          <p style="margin:0 0 20px;color:#6B7280">This page hasn't been saved on your phone yet. Reconnect and try again.</p>
          <button onclick="location.reload()" style="padding:12px 24px;border:0;border-radius:10px;background:#111827;color:#fff;font-size:15px">Try again</button></div>
        </body>`,
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
    )
}

self.addEventListener('message', (event) => {
    if (event.data === 'clear-cache') {
        event.waitUntil(Promise.all([caches.delete(CACHES.pages), caches.delete(CACHES.data)]))
    }
})

// ─── Push notifications ───────────────────────────────────────────────────────

self.addEventListener('push', (event) => {
    let payload = {}
    try {
        payload = event.data ? event.data.json() : {}
    } catch {
        payload = { body: event.data?.text() }
    }

    event.waitUntil(self.registration.showNotification(payload.title || 'New notification', {
        body: payload.body || '',
        icon: payload.icon,
        badge: payload.badge,
        tag: payload.tag,
        data: { url: payload.url || '/app/notifications' },
    }))
})

self.addEventListener('notificationclick', (event) => {
    event.notification.close()
    const target = new URL(event.notification.data?.url || '/app/notifications', self.location.origin).href

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
        const open = windows.find((client) => new URL(client.url).pathname.startsWith('/app'))

        if (open) {
            await open.focus()
            return open.navigate(target)
        }

        return self.clients.openWindow(target)
    })())
})

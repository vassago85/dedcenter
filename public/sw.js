if (typeof ServiceWorkerGlobalScope === 'undefined') {
    // Loaded as a regular page script — do nothing
} else {

const CACHE_NAME = 'deadcenter-v22';
const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

// Paths that must NEVER be served from cache — these are live, auth-bound,
// or mutating endpoints. Scoring/live/export/auth paths must always hit the
// network so the scoring pipeline stays real-time.
const NEVER_CACHE_PATTERNS = [
    /^\/score(\/|$)/,
    /^\/live(\/|$)/,
    /^\/scoreboard\/[^/]+\/export\//,
    /^\/matches\/[^/]+\/(report|export|my-report|matchbook)(\/|$)/,
    /^\/admin\/matches\/[^/]+\/(report|export|matchbook)(\/|$)/,
    /^\/org\/[^/]+\/matches\/[^/]+\/(report|export|matchbook)(\/|$)/,
    /^\/login$/,
    /^\/logout$/,
    /^\/register$/,
    /^\/mode-switch$/,
    /^\/sanctum\//,
    /^\/broadcasting\//,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(
                names
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Only manage same-origin traffic — let the browser handle cross-origin
    // fetches (fonts, CDNs, analytics) normally.
    if (url.origin !== self.location.origin) return;

    // Never intercept auth/live/scoring/export traffic. These must always
    // go to the network; stale data here would be dangerous.
    if (NEVER_CACHE_PATTERNS.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (request.destination === 'document') {
        event.respondWith(networkFirstWithOfflineFallback(request));
        return;
    }

    event.respondWith(cacheFirst(request));
});

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached || new Response('Offline', { status: 503 });
    }
}

async function networkFirstWithOfflineFallback(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;
        return caches.match('/offline.html') || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/html' } });
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Offline', { status: 503 });
    }
}

// ── Push Notifications ──

self.addEventListener('push', (event) => {
    let data = { title: 'DeadCenter', body: 'You have a new notification' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: data.tag || 'deadcenter-notification',
        data: {
            url: data.url || '/',
        },
        vibrate: [100, 50, 100],
        actions: data.actions || [],
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'DeadCenter', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});

} // end ServiceWorkerGlobalScope guard

const CACHE_NAME = 'dahab-pwa-shell-v5';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            );
        })
    );

    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET' || request.mode === 'navigate') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.startsWith('/api/')
        || url.pathname.startsWith('/login')
        || url.pathname.startsWith('/logout')
    ) {
        return;
    }

    const cacheableDestination = [
        'style',
        'script',
        'image',
        'font',
        'manifest'
    ].includes(request.destination);

    const isPwaAsset = url.pathname.startsWith('/pwa/');

    if (! cacheableDestination && ! isPwaAsset) {
        return;
    }

    event.respondWith(
        (async () => {
            const cached = await caches.match(request);

            // Fetch current assets first; cached responses are the offline fallback.
            // Clone immediately (before the
            // response body can be read anywhere else), cache the clone,
            // and return the original. Any caching failure is swallowed
            // so it never breaks the actual page/image/font load.
            try {
                const response = await fetch(request, { cache: 'no-cache' });

                if (response && response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME)
                        .then(cache => cache.put(request, copy))
                        .catch(() => {});
                }

                return response;
            } catch (error) {
                return cached || Response.error();
            }
        })()
    );
});


/* Real background Web Push, separate from page polling. */
self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let data = {};
        try { data = event.data ? event.data.json() : {}; } catch (_) {}
        const target = new URL(String(data.url || '/menu/'), self.location.origin);
        if (target.origin !== self.location.origin || !target.pathname.startsWith('/menu/track/')) {
            target.pathname = '/menu/';
            target.search = '';
        }
        await self.registration.showNotification(String(data.title || 'TISH — تحديث الطلب').slice(0, 100), {
            body: String(data.body || 'تم تحديث حالة طلبك.').slice(0, 240),
            icon: '/assets/tish-paper/pwa/apple-touch-icon-180.png',
            badge: '/assets/tish-paper/pwa/apple-touch-icon-180.png',
            tag: String(data.tag || 'tish-status'),
            renotify: true,
            data: {url: target.href},
        });
    })());
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        const url = new URL(String(event.notification.data?.url || '/menu/'), self.location.origin);
        if (url.origin !== self.location.origin) return;
        const windows = await self.clients.matchAll({type: 'window', includeUncontrolled: true});
        for (const windowClient of windows) {
            if (windowClient.url === url.href && 'focus' in windowClient) {
                return windowClient.focus();
            }
        }
        return self.clients.openWindow(url.href);
    })());
});

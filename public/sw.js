const CACHE_NAME = 'dahab-pwa-shell-v5';
const MENU_IMAGE_CACHE = 'tish-menu-images-v1';
const MENU_IMAGE_TTL = 30 * 60 * 1000;
const MENU_IMAGE_LIMIT = 240;

function isMenuImage(request) {
    if (request.destination !== 'image' || !request.referrer) return false;
    const referrer = new URL(request.referrer);
    return referrer.origin === self.location.origin && referrer.pathname.startsWith('/menu/');
}

async function saveMenuImage(cache, request, response) {
    await cache.put(request, response);
    const entries = await cache.keys();
    if (entries.length > MENU_IMAGE_LIMIT) {
        await Promise.all(entries.slice(0, entries.length - MENU_IMAGE_LIMIT).map(entry => cache.delete(entry)));
    }
}

async function loadMenuImage(event) {
    const request = event.request;
    const cache = await caches.open(MENU_IMAGE_CACHE).catch(() => null);
    const cached = await cache?.match(request).catch(() => null);
    const age = Date.now() - Date.parse(cached?.headers.get('Date'));
    if (cached && age >= 0 && age < MENU_IMAGE_TTL && request.cache !== 'reload' && request.cache !== 'no-cache') {
        return cached;
    }

    try {
        const response = await fetch(request);
        const policy = response.headers.get('Cache-Control') || '';
        if (cache && response.ok && !response.redirected &&
            response.headers.get('Content-Type')?.startsWith('image/') &&
            !/\b(?:private|no-store|no-cache)\b/i.test(policy)) {
            event.waitUntil(saveMenuImage(cache, request, response.clone()).catch(() => {}));
        }
        return response;
    } catch {
        return cached || Response.error();
    }
}

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter(name =>
            (name.startsWith('dahab-pwa-shell-') && name !== CACHE_NAME) ||
            (name.startsWith('tish-menu-images-') && name !== MENU_IMAGE_CACHE)
        ).map(name => caches.delete(name)));
        await self.clients.claim();
    })());
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

    if (isMenuImage(request)) {
        event.respondWith(loadMenuImage(event));
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
            const cached = await (await caches.open(CACHE_NAME)).match(request);

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


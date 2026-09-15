const CACHE_NAME = 'cinema-ticket-v1';
self.addEventListener('install', (event) => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', (event) => event.waitUntil(
    caches.keys().then((keys) => Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
    )).then(() => self.clients.claim()),
));
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    const isPrivateRoute = /^\/(user|admin|login|register|forgot-password|reset-password|ticket-verify)/.test(url.pathname)
        || /\/checkout|\/payment|\/tickets\//.test(url.pathname);

    if (event.request.method === 'GET' && event.request.mode === 'navigate' && !isPrivateRoute) {
        event.respondWith(caches.open(CACHE_NAME).then(async (cache) => {
            try {
                const response = await fetch(event.request);
                if (response.ok && response.type === 'basic') {
                    await cache.put(event.request, response.clone());
                }

                return response;
            } catch {
                return cache.match(event.request);
            }
        }));
    }
});

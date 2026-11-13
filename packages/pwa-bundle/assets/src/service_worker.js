/*
 * The service worker of phil/pwa-bundle, a classic worker script imported by the one the bundle serves at /sw.js,
 * which first sets `self.philPwa`: the version and prefix of the caches, the paths to cache on install, the offline
 * page, the cache rules by path prefix and the prefixes to leave alone.
 *
 * - install: the precache list goes into the cache of this version, fetched past the HTTP cache;
 * - activate: the caches of other versions of this prefix are deleted, and the open pages are taken over;
 * - fetch: only GET requests of the site's own origin within the scope; a navigation is network-first, falling back on
 *   the cache, then on the offline page; another request follows the first rule whose prefix starts its path —
 *   cache-first, network-first or network-only —, and goes to the network without a rule;
 * - message `{type: 'skip-waiting'}`: the waiting worker takes over at once.
 *
 * A response is cached only when it is a 200-range answer of the site itself whose Cache-Control has neither no-store
 * nor private: a page meant for one person is never kept for the next one.
 */
function philPwaInstall(scope, settings) {
    const cacheName = `${settings.cache}-${settings.version}`;
    const origin = new URL(scope.registration.scope).origin;
    const scopePath = new URL(scope.registration.scope).pathname;

    const cacheable = (response) =>
        response instanceof Response &&
        response.ok &&
        response.type === 'basic' &&
        !/(?:^|[\s,])(?:no-store|private)(?:$|[\s,=])/i.test(response.headers.get('Cache-Control') ?? '');

    const store = async (request, response) => {
        if (cacheable(response)) {
            const cache = await scope.caches.open(cacheName);
            await cache.put(request, response.clone());
        }

        return response;
    };

    const fromCache = async (request) => (await scope.caches.open(cacheName)).match(request);

    const networkFirst = async (request, navigation) => {
        try {
            return await store(request, await scope.fetch(request));
        } catch (error) {
            const cached = await fromCache(request);

            if (cached) {
                return cached;
            }

            if (navigation && settings.offline) {
                return (await fromCache(new URL(settings.offline, origin).href)) ?? Response.error();
            }

            throw error;
        }
    };

    const cacheFirst = async (request) => (await fromCache(request)) ?? store(request, await scope.fetch(request));

    const strategy = (path) => settings.rules.find((rule) => path.startsWith(rule.prefix))?.strategy ?? 'network-only';

    const handlers = {
        install(event) {
            event.waitUntil(
                scope.caches
                    .open(cacheName)
                    .then((cache) =>
                        cache.addAll(
                            settings.precache.map((url) => new Request(new URL(url, origin), { cache: 'reload' })),
                        ),
                    ),
            );
        },

        activate(event) {
            event.waitUntil(
                scope.caches
                    .keys()
                    .then((names) =>
                        Promise.all(
                            names
                                .filter((name) => name.startsWith(`${settings.cache}-`) && name !== cacheName)
                                .map((name) => scope.caches.delete(name)),
                        ),
                    )
                    .then(() => scope.clients.claim()),
            );
        },

        fetch(event) {
            const request = event.request;
            const url = new URL(request.url);

            if (
                request.method !== 'GET' ||
                url.origin !== origin ||
                !url.pathname.startsWith(scopePath) ||
                settings.exclude.some((prefix) => url.pathname.startsWith(prefix))
            ) {
                return;
            }

            if (request.mode === 'navigate') {
                event.respondWith(networkFirst(request, true));

                return;
            }

            const chosen = strategy(url.pathname);

            if (chosen === 'cache-first') {
                event.respondWith(cacheFirst(request));
            } else if (chosen === 'network-first') {
                event.respondWith(networkFirst(request, false));
            }
        },

        message(event) {
            if (event.data?.type === 'skip-waiting') {
                scope.skipWaiting();
            }
        },
    };

    for (const [type, handler] of Object.entries(handlers)) {
        scope.addEventListener(type, handler);
    }

    return handlers;
}

if (typeof self !== 'undefined' && self.philPwa && self.registration) {
    philPwaInstall(self, self.philPwa);
}

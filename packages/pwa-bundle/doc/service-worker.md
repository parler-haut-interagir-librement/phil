# The service worker

## What is served

`/sw.js` answers:

```js
// The service worker of phil/pwa-bundle: its settings, then its code.
self.philPwa = {"version":"3f9a…","cache":"phil-pwa","scope":"/","precache":["/offline","/assets/styles/app-a1b2.css","/"],"offline":"/offline","rules":[{"prefix":"/assets/","strategy":"cache-first"}],"exclude":["/_profiler/","/_wdt/"],"script":"/assets/@phil/pwa-bundle/service_worker-c3d4.js"};
importScripts("/assets/@phil/pwa-bundle/service_worker-c3d4.js");
```

The code of the worker, `assets/src/service_worker.js`, is a classic worker script of the bundle, served by
AssetMapper like any asset — compiled to `public/assets/` in production, at an address that changes with its content.
The settings come from the configuration, the routes and the precache providers.

## Precache and versions

The precache list holds, each once and 200 at most:

1. the offline page, `/offline`, unless `offline` is false;
2. `service_worker.precache`, assets at their versioned addresses;
3. the paths of every `PrecacheProvider` ([Integrations](integrations.md)).

The `version` is a hash of every setting, these addresses included. A deployment that changes an asset, a path or a
rule changes the version: the browser sees a new `/sw.js`, installs the new worker, which caches the list in
`<cache>-<version>`, fetched past the HTTP cache. Once active, it deletes the caches `<cache>-…` of other versions and
takes over the open pages. A new worker waits while pages use the old one, until the page asks it to take over — the
`update` action of the controller.

## Answering requests

The worker answers only `GET` requests of the site's own origin, within its scope, outside `exclude`:

| Request | Strategy |
|---|---|
| A navigation — a page opened | Network-first: the network, else the page cached, else the offline page, else a network error. |
| A path whose prefix has a rule | The first rule whose prefix starts the path: `cache-first` — the cache, else the network, kept —, `network-first` — the network, kept, else the cache —, or `network-only`. |
| Any other | The network, as if there were no worker. |

A response is kept only when it is a success of the site itself whose `Cache-Control` has neither `no-store` nor
`private`. A page meant for one person — an account, a basket — sends `private`, as Symfony does for a page that reads
the session, and is never kept on a shared device. Answers are not refreshed in the background: a cached asset changes
with its versioned address.

## The offline page

`/offline` is a page of the bundle within `layout`, in English and French from the translation domain `phil_pwa`: a
heading, a sentence with the site's name, and a link to `start_url` to try again. It carries no script. Override
`@PhilPwa/offline.html.twig` to change it.

## Headers, scope and CSP

| Answer | Headers |
|---|---|
| `/sw.js` | `Content-Type: text/javascript`, `Cache-Control: no-cache` — the browser checks the worker on every visit —, `X-Content-Type-Options: nosniff`, and `Service-Worker-Allowed: <scope>` only when the scope is wider than the directory of `/sw.js`. |
| `/manifest.webmanifest`, `/offline` | `Cache-Control: public, max-age=<cache_max_age>`, `X-Content-Type-Options: nosniff`. |

No answer reads the session or sets a cookie. The scope is a directory of the site, checked like every path; the worker
never answers another origin.

With a Content Security Policy, the site's own origin must be allowed for the worker and the manifest: `worker-src
'self'` and `manifest-src 'self'`, which the default policy of `phil/browser-security-bundle` already gives through
`default-src 'self'`. The worker's `importScripts()` loads its code from the site; nothing is inline in a page, so no
nonce is needed. With a CDN in front of AssetMapper, add its origin to `worker-src`.

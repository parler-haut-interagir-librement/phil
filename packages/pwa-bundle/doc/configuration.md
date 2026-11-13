# Configuration

```yaml
phil_pwa:
    manifest:                         # required
        name: Example                 # required, 120 characters at most
        short_name: Example           # 30 characters at most
        description: ~
        id: /                         # the identity of the installed site
        start_url: /?source=pwa       # within the scope
        scope: /
        display: standalone           # fullscreen | standalone | minimal-ui | browser
        display_override: []          # modes asked for first: the four above, window-controls-overlay, tabbed
        orientation: ~                # any, natural, landscape, portrait, landscape-primary, …
        theme_color: '#1b1b1b'        # hexadecimal or a CSS keyword
        background_color: ~
        lang: ~                       # a language tag, such as fr-CA
        dir: ~                        # ltr | rtl | auto
        categories: []
        icons:
            - { src: images/icon-512.png, sizes: 512x512, type: image/png, purpose: [any, maskable] }
        shortcuts:
            - { name: Schedule, url: /schedule, short_name: ~, description: ~ }
    service_worker:
        cache: phil-pwa               # the prefix of its caches
        scope: /                      # the directory it controls
        script: '@phil/pwa-bundle/service_worker.js'   # the logical path of its code
        precache: [styles/app.css, /] # paths of the site, or logical paths of assets
        offline: true                 # cache the offline page and fall back on it
        cache_rules:                  # the first prefix that starts the path decides
            - { prefix: /assets/, strategy: cache-first }
        exclude: [/_profiler/, /_wdt/]
    layout: '@PhilPwa/layout.html.twig'   # the layout of the offline page
    cache_max_age: 600                # the seconds browsers keep the manifest and the offline page
```

| Option | Default | What it changes |
|---|---|---|
| `manifest` | — | The members of the manifest ([The manifest](manifest.md)). Checked when the container is built. |
| `service_worker.cache` | `phil-pwa` | The caches are named `<cache>-<version>`; those of other versions are deleted on activation, those of other prefixes never. |
| `service_worker.scope` | `/` | The scope the controller registers the worker with: a directory, ending with `/`. |
| `service_worker.script` | `@phil/pwa-bundle/service_worker.js` | The code of the worker, an asset: replace it with a script of the application's own. |
| `service_worker.precache` | `[]` | A value starting with `/` is a path of the site; any other is the logical path of an asset, given at its versioned address. |
| `service_worker.offline` | `true` | Precache `/offline` and show it when a navigation fails and the page is not cached. |
| `service_worker.cache_rules` | `/assets/` cache-first | `cache-first`, `network-first` or `network-only`, by the prefix of the path; a request without a rule goes to the network. |
| `service_worker.exclude` | `/_profiler/`, `/_wdt/` | Prefixes the worker never answers, navigations included. |
| `layout` | `@PhilPwa/layout.html.twig` | A template with the blocks `title` and `content`. |
| `cache_max_age` | `600` | `Cache-Control: public, max-age=…` of the manifest and the offline page; 0 sends `no-cache`. |

Paths are written from the root of the site and resolved as a browser does: `/app/./a/../` is `/app/`. A path that
leaves the site — `//host`, `/\host`, `/.//host` —, carries a fragment, spaces or control characters, or an asset path
with a `.` or `..` segment, is refused with the option named.

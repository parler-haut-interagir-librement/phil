<!-- phil-dev source-sha256=0d8cd137c69fd4610e9db7ce15d4a2bbd9ae660c2298f302a02dae66ddda71e7 -->
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

| Option | Valeur par défaut | Effet |
|---|---|---|
| `manifest` | — | Les membres du manifeste ([Le manifeste](manifest.md)). Vérifiés à la construction du conteneur. |
| `service_worker.cache` | `phil-pwa` | Les caches se nomment `<cache>-<version>` ; ceux des autres versions sont supprimés à l'activation, ceux des autres préfixes jamais. |
| `service_worker.scope` | `/` | La portée avec laquelle le contrôleur enregistre le worker : un répertoire, terminé par `/`. |
| `service_worker.script` | `@phil/pwa-bundle/service_worker.js` | Le code du worker, une ressource : remplacez-le par un script propre à l'application. |
| `service_worker.precache` | `[]` | Une valeur qui commence par `/` est un chemin du site ; toute autre est le chemin logique d'une ressource, donné à son adresse versionnée. |
| `service_worker.offline` | `true` | Précharge `/offline` et l'affiche quand une navigation échoue et que la page n'est pas en cache. |
| `service_worker.cache_rules` | `/assets/` cache d'abord | `cache-first`, `network-first` ou `network-only`, selon le préfixe du chemin ; une requête sans règle va au réseau. |
| `service_worker.exclude` | `/_profiler/`, `/_wdt/` | Les préfixes auxquels le worker ne répond jamais, navigations comprises. |
| `layout` | `@PhilPwa/layout.html.twig` | Un gabarit avec les blocs `title` et `content`. |
| `cache_max_age` | `600` | `Cache-Control: public, max-age=…` du manifeste et de la page hors ligne ; 0 envoie `no-cache`. |

Les chemins s'écrivent à partir de la racine du site et se résolvent comme le fait un navigateur : `/app/./a/../` donne
`/app/`. Un chemin qui sort du site — `//host`, `/\host`, `/.//host` —, qui porte un fragment, des espaces ou des
caractères de contrôle, ou un chemin de ressource avec un segment `.` ou `..`, est refusé en nommant l'option.

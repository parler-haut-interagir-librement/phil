<!-- phil-dev source-sha256=fa244f8d8e1d3a8a1ffb5048833ab9963ba4ca4f7d764b7dd595d67d221947a2 -->
# Configuration

Chaque option de `phil_browser_security`, avec sa valeur par défaut.

```yaml
phil_browser_security:
    # The named policy sent as Content-Security-Policy, or ~ to enforce none.
    enforce: default
    # The named policy sent as Content-Security-Policy-Report-Only, or ~.
    report_only: ~

    # The named policies; without any, "default" is the default policy.
    policies:
        default:
            extends: ~                # a policy whose directives this one takes
            directives:               # sources by directive; true for a directive without value
                default-src: ["'self'"]
            strict_dynamic: false     # 'strict-dynamic' next to the nonces and hashes of script-src
            report: true              # report-uri and report-to on this policy
            inline:                   # inline code allowed by the SHA-256 of its exact content
                script: []
                style: []
            trusted_types:
                enabled: false        # require-trusted-types-for 'script'
                policies: []          # the Trusted Types policies the page may create
                allow_duplicates: false

    # Prefixes of the paths whose responses get no header.
    exclude_paths: ['/_wdt', '/_profiler']

    # The script nonce on the tags of importmap().
    importmap: true

    reporting:
        enabled: true
        group: csp                    # the endpoint's name in Reporting-Endpoints and report-to
        uri: ~                        # an https address or a path, instead of the route phil_csp_report
        max_bytes: 65536              # 512 to 1,048,576
        max_reports: 20               # reports read from one body, 1 to 100
        rate_limit:
            enabled: true
            limit: 60                 # bodies per client
            interval: '1 minute'
            cache_pool: cache.app     # the storage of the limiter built by the bundle; a shared one in prod
            limiter: ~                # the service id of a rate limiter factory of the application
            single_server: false      # accepts a pool local to the server in prod
        deduplication:
            enabled: true
            window: 3600              # seconds during which a violation reported again dispatches no event
            cache_pool: cache.app
        client_address:
            enabled: true             # with phil/ip-anonymizer installed
            ipv4_prefix: 24
            ipv6_prefix: 48
        storage:
            enabled: false
            connection: default       # the DBAL connection of phil_csp_report
            capacity: 10000           # rows at most; a new violation is not kept beyond
            retention: 30             # days, the default of phil:csp:purge-reports

    headers:
        permissions_policy: {}        # origins by feature: camera: [] gives camera=()
        cross_origin_opener_policy: ~ # same-origin, same-origin-allow-popups, noopener-allow-popups, unsafe-none
        cross_origin_opener_policy_report_only: ~
        cross_origin_embedder_policy: ~ # require-corp, credentialless, unsafe-none
        cross_origin_embedder_policy_report_only: ~
        cross_origin_resource_policy: same-site # same-origin, cross-origin
        referrer_policy: strict-origin-when-cross-origin
        content_type_options: nosniff
        permitted_cross_domain_policies: none
        origin_agent_cluster: '?1'    # ?0 for one agent cluster per site
        frame_options:
            default: SAMEORIGIN       # DENY, or ~ to send nothing
            paths: {}                 # the value by prefix of a path; ~ sends nothing
        integrity_policy:
            enabled: false
            blocked_destinations: [script]
        integrity_policy_report_only:
            enabled: false
            blocked_destinations: [script]

    https:
        redirect:
            enabled: ~                # null follows kernel.debug: on when it is off
            status: 308               # 301, 302, 307 or 308
            port: 443
            exclude_paths: []         # prefixes of the paths served over both
        strict_transport_security:
            enabled: ~                # null follows kernel.debug: on when it is off
            max_age: 31536000
            include_subdomains: false
            preload: false            # needs include_subdomains and a year or more

    signed_cookies:
        names: []                     # the cookies signed on every page
        secret: '%kernel.secret%'
        previous_secrets: []          # no longer used to sign, still accepted

    external_redirects:
        enabled: true
        action: refuse                # refuse, fallback or log
        fallback: /                   # the path a refused redirect goes to with "fallback"
        allow: []                     # hosts, or domains written with a leading dot

    clear_site_data:
        on_logout: [cookies, storage] # what the browser forgets at logout, on every firewall; [] for nothing

    user_content:                     # the headers of a file someone uploaded, see User content
        directives:                   # its own policy; default-src 'none', base-uri, form-action, frame-ancestors 'none', sandbox
            default-src: ["'none'"]
            sandbox: true
        inline_types: [image/png, image/jpeg, text/plain] # the media types shown in the browser
        cross_origin_resource_policy: same-origin # same-site, cross-origin

    # The service id of the PSR-20 clock.
    clock: clock
```

## Ce qui est vérifié à la construction du conteneur

- chaque directive et chaque valeur, avec la grammaire de [Politiques](policies.md#valeurs) ;
- `extends`, qui nomme une politique déclarée, sans cycle ;
- `enforce` et `report_only`, qui nomment des politiques déclarées ;
- `reporting.uri`, une adresse `https` ou un chemin, sans espace, guillemet, virgule ni point-virgule ;
- `reporting.group`, 1 à 64 lettres, chiffres, `-` et `_` ;
- chaque en-tête voisin, d'après les valeurs que liste sa spécification ;
- les options de cadre par chemin, les noms des cookies signés, les hôtes permis, la destination de repli et les
  directives de `Clear-Site-Data` ;
- `preload`, qui demande `include_subdomains` et un an ou plus ;
- la politique du contenu d'usager, ses types affichés (jamais un type qui peut s'exécuter comme une page ou un script)
  et son `Cross-Origin-Resource-Policy` ;
- dans l'environnement `prod`, le `cache_pool` de la limitation de débit, que la garde de `phil/rate-limit` refuse quand
  il est propre à chaque serveur, sauf si `single_server` est défini.

Un échec lève `InvalidConfigurationException`, avec le chemin de l'option et la raison.

## Clés

Les noms des politiques et des directives gardent leurs traits d'union : `default-src`, pas `default_src`.

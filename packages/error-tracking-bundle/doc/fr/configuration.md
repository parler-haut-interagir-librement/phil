<!-- phil-dev source-sha256=a0519553b6eb957a280d55b96d00402bd626fe31babc1be142796d8e922249bc -->
# Configuration

Chaque clé de `phil_error_tracking`, avec sa valeur par défaut.

```yaml
phil_error_tracking:
    release: ~                         # the version or commit that runs; regressions are told by it
    environment: '%kernel.environment%'
    project_dir: '%kernel.project_dir%' # its frames outside vendor/ are the application's
    sample_rate: 1.0                   # the share of the events kept, 0.0 to 1.0
    max_frames: 50                     # frames per exception, 1 to 500
    max_chain: 10                      # exceptions per chain, 1 to 50
    scrub_fields: []                   # parts of a name that make a header or a tag sensitive
    token_path_prefixes: []            # /reset-password/reset/: the next segment is a token, filtered
    ignored_exceptions: []             # classes never captured, with their subclasses
    client_address:
        ipv4_prefix: 24
        ipv6_prefix: 48
    rate_limit:                        # events kept per group; false turns it off
        limit: 30
        interval: '1 minute'
        cache_pool: cache.app          # a shared pool in prod
        single_server: false           # accepts a pool local to the server in prod
    capture:
        http:                          # false turns it off
            client_errors: false       # the HTTP exceptions below 500 too
        messenger:                     # false turns it off
            retries: false             # each failed attempt, not only the last one
        console: true
        monolog_level: error           # the level from which the handler captures
    storage:
        connection: default
        events_per_group: 50           # the latest events kept per group, 1 to 10000
        retention: 30                  # days, for phil:error-tracking:purge
    alerts:                            # false turns them off
        transport: ~                   # the chatter transport; its default one when null
        async: true                    # the chat message through Messenger rather than in the request
        recipients: []                 # identifiers notified by phil/notifications-bundle
        audience: ~                    # or an audience of that bundle
    health_check:                      # with phil/monitoring-bundle; false turns it off
        new_groups: 20                 # new groups within the window that make it degraded; 0 ignores them; at most 200
        window: 900                    # seconds, up to a day
    admin:                             # off unless enabled
        enabled: false
        role: ROLE_ADMIN
        layout: '@PhilErrorTracking/layout.html.twig'
        page_size: 50
        events_shown: 10
    browser:                           # off unless enabled
        enabled: false
        max_bytes: 16384               # 512 to 262144
        max_frames: 50
        origins: []                    # https://example.com; the endpoint's own when empty
        token: ~                       # checked in X-Error-Tracking-Token when set
        rate_limit:                    # reports per client; false turns it off
            limit: 10
            interval: '1 minute'
            cache_pool: cache.app      # a shared pool in prod
            single_server: false       # accepts a pool local to the server in prod
    clock: clock                       # the service id of the PSR-20 clock
```

## Ce qui fait échouer la construction

| Valeur | Message |
|---|---|
| une origine qui n'est pas `scheme://host[:port]` | `phil_error_tracking.browser.origins: "…" is not an origin such as https://example.com.` |
| une classe ignorée qui n'est pas un throwable | `phil_error_tracking.ignored_exceptions: "…" is not a throwable class.` |
| un préfixe de chemin à jeton qui n'est pas un ou plusieurs segments de chemin entre barres obliques | `phil_error_tracking.token_path_prefixes: "…" is not a token path prefix: …` |
| une horloge ou un répertoire de projet vide ou qui n'est pas un texte | `phil_error_tracking: clock must be a string.` |
| un taux d'échantillonnage, une borne ou un préfixe hors de son intervalle | le message du composant Config |
| une limite de débit activée sans `symfony/rate-limiter` | `… needs symfony/rate-limiter: install it, or set "enabled" to false.` |
| dans l'environnement `prod`, un `cache_pool` sur l'adaptateur filesystem, APCu, array ou system, comme l'est `cache.app` par défaut, sauf si `single_server: true` indique que l'application tourne sur un seul serveur | `…: the store "…" is local to each server…` |
| à la fois `alerts.recipients` et `alerts.audience`, ou l'un des deux sans PhilNotificationsBundle, ou un destinataire qui n'est pas un identifiant | `phil_error_tracking.alerts: …` |

## Les services qui ont besoin de leur bundle

Les pages d'administration ont besoin de SecurityBundle, de sa protection CSRF et de TwigBundle. Une alerte par
clavardage a besoin d'un transport de *chatter* dans la configuration du notifier — sans lui, les alertes sont dans l'état
dégradé des [Alertes](alerts.md) et la construction se poursuit — et elle est envoyée dans la requête en l'absence de
bus Messenger. Les limites de débit comptent dans un pool partagé par les serveurs :

```yaml
framework:
    cache:
        pools:
            cache.rate_limit: { adapter: cache.adapter.redis }

phil_error_tracking:
    rate_limit: { cache_pool: cache.rate_limit }
    browser: { rate_limit: { cache_pool: cache.rate_limit } }
```

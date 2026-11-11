<!-- phil-dev source-sha256=c96cf329ea7f5741bad94549ef0522b473addb4299b008be2eaaf019a6348734 -->
# Configuration

Chaque clé de `phil_monitoring`, avec sa valeur par défaut :

```yaml
phil_monitoring:
    clock: clock                # service id of the PSR-20 clock
    cache_pool: cache.app       # service id of the PSR-6 pool of the heartbeats and of the "cache" maintenance store

    health:
        token: null             # bearer token of the detailed view; the view answers 404 while it is null
        database: []            # DBAL connections to check, by name: [default, audit]
        cache_pools: []         # service ids of PSR-6 pools to check: [cache.app, cache.redis]
        messenger: {}           # transports to count, each with its threshold or null: { async: 100, failed: 0 }
        filesystems: {}         # local directories, by name: { uploads: '%kernel.project_dir%/public/uploads' }
                                # or { uploads: { path: '…', min_free_megabytes: 512 } }
        flysystem: {}           # Flysystem storages, by name: { media: media.storage }

    maintenance:
        store: file             # "file" or "cache"
        file: '%kernel.project_dir%/var/maintenance.json'
        retry_after: 300        # seconds visitors are told to wait, unless the command gives its own
        allowed_ips: []         # addresses or CIDR networks: ['10.0.0.0/8', '2001:db8::/32']
        allowed_roles: []       # roles that see the site: [ROLE_ADMIN]
        allowed_routes: []      # route names served anyway, besides the health endpoints
        template: '@PhilMonitoring/maintenance.html.twig'

    heartbeats:
        store: null             # service id of a HeartbeatStore; null keeps the beats in cache_pool
        tasks: {}               # { backup: { every: 86400, grace: 3600 }, report: { every: 60 } }
        lock:
            factory: lock.factory # service id of the LockFactory of the dead-man switch, its store shared
            ttl: 30             # seconds a round holds the lock of a task at most, 1 to 3600
        alerts:
            channels: [chat]    # Notifier channels: chat, email, sms, chat/slack…
            importance: urgent  # urgent, high, medium or low

    queues:
        enabled: true           # false removes the queues and their page
        role: ROLE_ADMIN        # the role granted the page and its actions
        failed_limit: 50        # failed messages listed per failure transport, 1 to 1000
        layout: '@PhilMonitoring/layout.html.twig'
```

## Les noms

Chaque vérification porte le nom de sa configuration. Ce sont ces noms que montrent les moniteurs et la vue détaillée :

| Clé | Nom de la vérification |
|---|---|
| `health.database: [default]` | `database.default` |
| `health.cache_pools: [cache.app]` | `cache.app` |
| `health.messenger: { async: 100 }` | `messenger.async` |
| `health.filesystems: { uploads: … }` | `filesystem.uploads` |
| `health.flysystem: { media: … }` | `storage.media` |
| `heartbeats.tasks`, au moins une | `heartbeats` |

Un nom, de vérification ou de tâche, est un identifiant d'au plus 100 caractères : lettres, chiffres, points, tirets,
soulignés et deux-points. Il commence par une lettre ou un chiffre. Le conteneur refuse tout autre nom à sa
construction.

## Ce que le conteneur refuse

Un stockage de maintenance autre que `file` ou `cache`, un `retry_after` négatif, une tâche sans `every`, un `every`
inférieur à 1 ou un `grace` négatif, une importance hors des quatre permises, un seuil négatif de messages ou d'espace
libre, un répertoire sans chemin, un `failed_limit` hors de 1 à 1000, et un identifiant de service, un fichier, un
gabarit, un rôle ou une mise en page vide.

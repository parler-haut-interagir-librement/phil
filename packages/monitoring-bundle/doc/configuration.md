# Configuration

Every key of `phil_monitoring`, with its default:

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

## The names

Each check is named after its configuration, and the names are what monitors and the detailed view show:

| Key | Check name |
|---|---|
| `health.database: [default]` | `database.default` |
| `health.cache_pools: [cache.app]` | `cache.app` |
| `health.messenger: { async: 100 }` | `messenger.async` |
| `health.filesystems: { uploads: … }` | `filesystem.uploads` |
| `health.flysystem: { media: … }` | `storage.media` |
| `heartbeats.tasks`, at least one | `heartbeats` |

A name, of a check or a task, is an identifier of up to 100 letters, digits, dots, dashes, underscores and colons,
starting with a letter or a digit; the container refuses another when it is built.

## What the container refuses

A maintenance store other than `file` or `cache`, a negative `retry_after`, a task without `every`, an `every` under 1
or a negative `grace`, an importance outside the four, a negative threshold of messages or of free space, a directory
without a path, a `failed_limit` outside 1 to 1000, and an empty service id, file, template, role or layout.

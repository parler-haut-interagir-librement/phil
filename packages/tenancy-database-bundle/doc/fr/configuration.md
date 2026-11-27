<!-- phil-dev source-sha256=412eed1570fe89a613a1579ff21f437cffe1ebdedc8d237460898654bf1f170c -->
# Configuration

```yaml
# config/packages/phil_tenancy_database.yaml
phil_tenancy_database:
    connection: default                 # the connection that follows the tenant
    entity_manager: default             # the mapping a move copies
    placement: null                     # null for the tenants below, dbal for the table, or a TenantPlacement service
    dedicated:                          # the tenants with a database of their own
        wholesale: tenant_wholesale
        export: tenant_export
    credentials: null                   # a TenantCredentials service
    availability: dbal                  # dbal for the table, cache for the pool, or a TenantDatabaseAvailability service
    store_connection: null              # a connection on the shared database for the dbal stores
    retry_after: 120                    # the Retry-After of a 503, in seconds
    migrations:
        console: '%kernel.project_dir%/bin/console'
        command: ['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']
        concurrency: 4                  # 1 to 32
        timeout: 3600                   # seconds; 0 for no limit
        grace_period: 10                # seconds between SIGTERM and SIGKILL when the command is stopped
```

| Clé | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `connection` | `default` | La connexion Doctrine dont `TenantDatabaseMiddleware` enveloppe le pilote, et que `TenantConnectionSwitch` déplace. |
| `entity_manager` | `default` | Le gestionnaire d'entités dont `TenantMover` copie les entités limitées au locataire. |
| `placement` | `null` | Où les emplacements sont lus. `null` : `ConfiguredPlacements` lit `dedicated`, et rien ne peut être déplacé. `dbal` : `DbalPlacementStore` lit et écrit la table `phil_tenancy_placement` de la base partagée ([Emplacements et suspensions dans la base de données](stores.md)). Toute autre valeur est l'identifiant du service `TenantPlacement` de l'application, qui peut aussi implémenter `PlacedTenants`, pour les migrations, et `PlacementStore`, pour les déplacements. |
| `dedicated` | `{}` | Les locataires qui ont une base à eux, par référence, et le nom de cette base. Tout autre locataire est dans la base partagée. Lu seulement quand `placement` vaut `null` ; refusé avec `placement: dbal` ; vérifié pendant la compilation du conteneur. |
| `credentials` | `null` | L'identifiant d'un service `TenantCredentials`, pour une base dédiée atteinte avec un autre compte, un autre hôte ou un autre port. |
| `availability` | `dbal` | Où les suspensions sont gardées. `dbal` : `DbalTenantDatabaseAvailability`, dans la table `phil_tenancy_suspension` de la base partagée. `cache` : `CacheTenantDatabaseAvailability`, dans le pool `phil_tenancy_database.availability`, qu'une éviction ou un cache vidé efface. Toute autre valeur est l'identifiant d'un service `TenantDatabaseAvailability`. |
| `store_connection` | `null` | La connexion Doctrine sur la base partagée que les stockages `dbal` utilisent ; elle ne doit pas suivre le locataire, donc `connection` est refusée. Sans elle, les stockages partagent `phil_tenancy_database.store_connection`, une connexion à eux fixée sur la base partagée de `connection`. |
| `retry_after` | `120` | Les secondes de l'en-tête `Retry-After` du `503` que reçoit un locataire suspendu ; au moins 1. |
| `migrations.console` | `%kernel.project_dir%/bin/console` | La console que chaque migration exécute. |
| `migrations.command` | `doctrine:migrations:migrate --no-interaction --allow-no-migration` | La commande, et ses arguments, qui migre la base où se trouve la connexion ; `--tenant=` est ajouté pour une base dédiée. Toute commande convient : `doctrine:schema:update --force` pour un prototype. |
| `migrations.concurrency` | `4` | Les migrations qui s'exécutent à la fois, de 1 à 32 ; `--concurrency` la remplace pour une exécution. |
| `migrations.timeout` | `3600` | Les secondes que chaque migration peut durer avant d'être arrêtée et comptée comme échouée ; `0` pour aucune limite. |
| `migrations.grace_period` | `10` | Les secondes laissées à une migration en cours pour s'arrêter après SIGTERM, quand un signal arrête la commande, avant qu'elle reçoive SIGKILL ; `0` la tue aussitôt. Voir [Arrêter la commande](migrations.md#arrêter-la-commande). |

## Ce que le bundle enregistre

| Service | Enregistré |
|---|---|
| `TenantDatabaseRouter` | toujours ; réinitialisé avec le noyau |
| `TenantDatabaseMiddleware` | toujours, étiqueté `doctrine.middleware` pour `connection` |
| `TenantConnectionSwitch` | toujours, étiqueté `phil_tenancy.activation` et réinitialisé avec le noyau |
| `RollBackOpenTransactions` | toujours, à l'écoute de `kernel.terminate` ; de `console.terminate` avec le composant Console, et de `WorkerMessageHandledEvent` et `WorkerMessageFailedEvent` avec Messenger. Voir [Les *workers*](connection.md#les-workers) |
| `ConfiguredPlacements`, avec l'alias `TenantPlacement` | quand `placement` vaut `null` |
| `DbalPlacementStore`, avec l'alias `TenantPlacement` | avec `placement: dbal` ; pour un identifiant de service, `TenantPlacement` est un alias de ce service |
| `DbalTenantDatabaseAvailability`, avec l'alias `TenantDatabaseAvailability` | avec `availability: dbal`, la valeur par défaut |
| `CacheTenantDatabaseAvailability`, avec l'alias `TenantDatabaseAvailability` | avec `availability: cache` ; pour un identifiant de service, `TenantDatabaseAvailability` est un alias de ce service |
| `phil_tenancy_database.store_connection` | avec un stockage `dbal` et sans `store_connection` ; fermée à la réinitialisation du noyau |
| `StoreSchemaListener` | avec un stockage `dbal`, à l'écoute de `postGenerateSchema` pour la connexion dont le schéma contient les tables |
| `TenantAvailabilityGuard` | toujours |
| `RefuseUnavailableTenant` | toujours, à l'écoute de `kernel.request` deux fois : après `ResolveHostTenant` et avant le pare-feu, puis après `ResolveActiveTenant` |
| `RefuseUnavailableTenantMiddleware` | quand Messenger est installé ; autoconfiguré, sur chaque bus par son `#[AsMessageMiddleware]`, après le middleware du locataire |
| `TenantDatabaseCreator`, `TenantMover` | toujours |
| `MigrationRunner`, `MigrationTargets` et les trois commandes | quand les composants Console et Process sont installés |

Avec FrameworkBundle et `availability: cache`, le bundle déclare le pool de cache `phil_tenancy_database.availability`
sur `cache.app`, et le met parmi les pools globaux de `phil_tenancy.cache`, pour que chaque locataire lise les mêmes
suspensions.

## Messenger

`RefuseUnavailableTenantMiddleware` porte l'attribut de Messenger `#[AsMessageMiddleware(bus: '*')]`, et le bundle
l'enregistre autoconfiguré : il va sur chaque bus, après le middleware du locataire de `phil/tenancy-bundle`, qui entre
dans le locataire de chaque message reçu, et avant le middleware de Doctrine qui ouvre, sonde ou ferme une connexion.
Rien n'est à lister. Un bus dont la configuration liste les deux les garde là où la configuration les met :

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware
                    - Phil\TenancyDatabaseBundle\Infrastructure\Messenger\RefuseUnavailableTenantMiddleware
```

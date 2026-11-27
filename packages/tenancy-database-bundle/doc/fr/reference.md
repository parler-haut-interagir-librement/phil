<!-- phil-dev source-sha256=189c08b7f97cfc231291a6814b01d7e0c106ca95ac791c3c1a34243344fe3fed -->
# Référence

Chaque type public du paquet, par couche.

## `Phil\TenancyDatabaseBundle\Domain`

| Type | Sorte | Rôle |
|---|---|---|
| `ValueObject\DatabaseCredentials` | `final readonly class` | Un utilisateur, un mot de passe, un hôte et un port facultatifs ; vérifié, jamais exporté ni sérialisé |
| `Exception\TenantSwitchInTransaction` | `LogicException`, `DomainError` | Un locataire d'une autre base entré dans une transaction ouverte |
| `Exception\TenantDatabaseUnavailable` | `RuntimeException`, `DomainError` | Un locataire en cours de déplacement, ou dont la base a échoué à sa dernière migration |
| `Exception\TenantNotMoved` | `RuntimeException`, `DomainError` | Un déplacement qui n'a pas eu lieu et a été annulé, ou un emplacement qui ne peut pas changer |
| `Exception\TenantNotCopyable` | `RuntimeException`, `DomainError` | Un mappage qu'un déplacement ne peut pas copier table par table |

## `Phil\TenancyDatabaseBundle\Application`

| Type | Sorte | Rôle |
|---|---|---|
| `Contract\PlacedTenants` | port | Les locataires qui ont une base à eux, pour les migrations |
| `Contract\PlacementStore` | port, étend `TenantPlacement` | Un emplacement qu'un déplacement peut changer |
| `Contract\TenantCredentials` | port | Le compte, l'hôte et le port d'une base dédiée |
| `Contract\TenantDatabaseAvailability` | port | Les bases et les locataires suspendus |

## `Phil\TenancyDatabaseBundle\Infrastructure`

| Type | Sorte | Rôle |
|---|---|---|
| `Doctrine\TenantDatabaseRouter` | `final class`, `ResetInterface` | La base sur laquelle la connexion gérée s'ouvre ; `parameters()`, `pinned()`, `route()`, `current()` |
| `Doctrine\TenantDatabaseMiddleware`, `TenantDatabaseDriver` | middleware et pilote DBAL | Ouvrent chaque connexion avec les paramètres du routeur |
| `Doctrine\TenantConnectionSwitch` | `final class`, `TenantActivation`, `ResetInterface` | Déplace la connexion avec le locataire ; refuse dans une transaction ; vide les gestionnaires d'entités |
| `Doctrine\RollBackOpenTransactions` | `final readonly class`, écouteur d'événements | Annule les transactions restées ouvertes à la fin d'une requête, d'une commande ou d'un message, puis quitte le locataire |
| `Doctrine\PinnedConnection` | `final class`, statique | `open()` et `shared()` : une connexion à elle, avec les paramètres et la configuration de la connexion gérée, fixée sur une base |
| `Doctrine\StoreTables` | `final class`, statique | Les tables `phil_tenancy_placement` et `phil_tenancy_suspension` : `configure()`, et `upsert()` pour MySQL, MariaDB, PostgreSQL et SQLite |
| `Doctrine\StoreSchemaListener` | `final readonly class`, écouteur de `postGenerateSchema` | Ajoute les tables des stockages `dbal` au schéma de leur connexion |
| `Placement\ConfiguredPlacements` | `final readonly class`, `TenantPlacement`, `PlacedTenants` | Les locataires de `dedicated` |
| `Placement\DbalPlacementStore` | `final readonly class`, `PlacementStore`, `PlacedTenants` | Les emplacements dans `phil_tenancy_placement` |
| `Availability\DbalTenantDatabaseAvailability` | `final readonly class`, `TenantDatabaseAvailability` | Les suspensions dans `phil_tenancy_suspension` ; la valeur par défaut |
| `Availability\CacheTenantDatabaseAvailability` | `final readonly class`, `TenantDatabaseAvailability` | Les suspensions dans un pool PSR-6 ; non durable |
| `Availability\TenantAvailabilityGuard` | `final readonly class` | `check()` : lance `TenantDatabaseUnavailable` pour un locataire suspendu |
| `Http\RefuseUnavailableTenant` | `final readonly class`, écouteur de requête | Le `503` et son `Retry-After` |
| `Messenger\RefuseUnavailableTenantMiddleware` | `final readonly class`, middleware Messenger, `#[AsMessageMiddleware]` sur chaque bus | Laisse les messages d'un locataire suspendu à la stratégie de nouvelle tentative |
| `Migration\MigrationRunner` | `final readonly class` | La commande de migration une fois par base, à concurrence bornée, avec un délai |
| `Migration\MigrationTarget`, `MigrationResult` | `final readonly class` | Une base à migrer, et comment sa migration s'est terminée : `interrupted()` et `notStarted()` pour une exécution arrêtée |
| `Migration\MigrationStop` | `final class` | Une demande d'arrêt d'une exécution, depuis un gestionnaire de signaux ; une seconde la force |
| `Console\MigrationTargets` | `final readonly class` | La base partagée et chaque base dédiée une fois |
| `Console\MigrateTenantDatabasesCommand` | commande de console | `phil:tenancy:database:migrate` |
| `Console\CreateTenantDatabaseCommand` | commande de console | `phil:tenancy:database:create` |
| `Console\MoveTenantCommand` | commande de console | `phil:tenancy:database:move` |
| `Database\TenantDatabaseCreator` | `final readonly class` | Crée une base dédiée sur le serveur de la connexion partagée |
| `Move\CopyPlan`, `CopyTable` | `final class`, `final readonly class` | Les tables d'un locataire, dans l'ordre où leurs lignes peuvent être insérées |
| `Move\TenantMover` | `final readonly class` | Déplace un locataire, vérifié, ou annulé |
| `Move\MoveReport` | `final readonly class` | Les lignes copiées dans chaque table, et si la source a été vidée |
| `Credentials\CredentialSeal` | `final readonly class` | Scelle et ouvre les identifiants : `ptdb.v1.xchacha20poly1305.` |

## Le bundle

| Type | Rôle |
|---|---|
| `PhilTenancyDatabaseBundle` | La configuration `phil_tenancy_database`, les services ci-dessus, et, avec `availability: cache`, le pool `phil_tenancy_database.availability` ; les constantes `DBAL`, `CACHE`, `STORE_CONNECTION`, `AVAILABILITY_POOL` et `ROLLBACK_PRIORITY` |

| Identifiant de service | Classe |
|---|---|
| `Phil\TenancyBundle\Application\Contract\TenantPlacement` | alias de `ConfiguredPlacements`, de `DbalPlacementStore` avec `placement: dbal`, ou du service que nomme `placement` |
| `Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability` | alias de `DbalTenantDatabaseAvailability`, de `CacheTenantDatabaseAvailability` avec `availability: cache`, ou du service que nomme `availability` |
| `phil_tenancy_database.store_connection` | une `Doctrine\DBAL\Connection` fixée sur la base partagée, pour les stockages `dbal` sans `store_connection` |
| `phil_tenancy_database.availability` | le pool de cache des suspensions, sur `cache.app`, avec `availability: cache` |

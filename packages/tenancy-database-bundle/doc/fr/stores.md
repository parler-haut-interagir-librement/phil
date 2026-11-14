<!-- phil-dev source-sha256=48bc9cd186d506301362004ed41e155735f670a21052fb161f1eec81a5ac3b51 -->
# Emplacements et suspensions dans la base de données

Le bundle garde deux choses sur lesquelles chaque processus doit s'accorder : où vit chaque locataire, et quelles bases
et quels locataires sont suspendus. Les deux peuvent vivre dans une table de la base partagée, écrite par les
adaptateurs du bundle, de sorte qu'un déplacement n'exige aucun code et qu'une suspension survit à un cache vidé, à un
déploiement et à un redémarrage.

```yaml
# config/packages/phil_tenancy_database.yaml
phil_tenancy_database:
    placement: dbal        # the placements in phil_tenancy_placement
    availability: dbal     # the suspensions in phil_tenancy_suspension; the default
```

| Réglage | Adaptateur | Table |
|---|---|---|
| `placement: dbal` | `DbalPlacementStore` : `TenantPlacement`, `PlacedTenants` et `PlacementStore` | `phil_tenancy_placement` |
| `availability: dbal` (par défaut) | `DbalTenantDatabaseAvailability` | `phil_tenancy_suspension` |
| `availability: cache` | `CacheTenantDatabaseAvailability`, sur le pool `phil_tenancy_database.availability` | aucune ; non durable |

`placement: dbal` n'accepte aucune liste `dedicated` : les locataires qui ont une base à eux sont consignés dans la
table, par un déplacement ou par `PlacementStore::place()`.

## Les tables

`phil_tenancy_placement`, une ligne par locataire placé :

| Colonne | Type | Contient |
|---|---|---|
| `id` | `CHAR(64)`, clé primaire | Le SHA-256 de la référence du locataire, en hexadécimal |
| `tenant` | `VARCHAR(255)` | La référence du locataire, pour l'opérateur qui lit la table |
| `database_name` | `VARCHAR(63)`, nullable | La base dédiée, ou `NULL` pour la base partagée |
| `placed_at` | `DATETIME`, défaut `CURRENT_TIMESTAMP` | Le moment où l'emplacement a été consigné en dernier |

`phil_tenancy_suspension`, une ligne par suspension :

| Colonne | Type | Contient |
|---|---|---|
| `id` | `CHAR(64)`, clé primaire | Le SHA-256 de ce qui est suspendu |
| `kind` | `VARCHAR(8)` | `shared`, `database` ou `tenant` |
| `subject` | `VARCHAR(255)`, nullable | Le nom de la base ou la référence du locataire ; `NULL` pour la base partagée |
| `suspended_at` | `DATETIME`, défaut `CURRENT_TIMESTAMP` | Le moment où la suspension a été consignée |

Les lignes se trouvent par l'empreinte, jamais par la référence : une référence se compare octet par octet, quelle que
soit la collation du serveur, là où la collation par défaut de MySQL lirait `Rose` et `rose` comme un seul locataire.
Un locataire sans ligne est dans la base partagée ; une base ou un locataire sans ligne est servi.

Un nom de base relu dans la table est vérifié comme le vérifie `Placement::dedicated()` : une ligne modifiée à la main
vers un nom qui n'en est pas un est refusée par `InvalidValue`, et le locataire n'est pas servi, plutôt que de lire le
nom comme du SQL.

## Les créer

Les tables sont ajoutées au schéma que Doctrine compare à la base : `doctrine:migrations:diff` écrit leur migration, et
`doctrine:schema:update` les crée. `StoreSchemaListener` les ajoute au schéma de la connexion qui suit le locataire, ou
à celui de `store_connection` quand une est nommée. Le bundle ne livre aucune classe de migration : la migration des
tables est celle de l'application, écrite par `doctrine:migrations:diff` dans son propre espace de noms et exécutée dans
son propre ordre, comme pour les tables du transport Doctrine de Messenger, et un changement ultérieur des tables
parvient à l'application par le même diff.

Une migration peut aussi les créer elle-même :

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;

final class Version20261113000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Both tables, each unless it exists.
        foreach ([StoreTables::PLACEMENTS, StoreTables::SUSPENSIONS] as $name) {
            if ($schema->hasTable($name)) {
                continue;
            }

            foreach ($this->platform->getCreateTableSQL(StoreTables::configure($schema)->getTable($name)) as $sql) {
                $this->addSql($sql);
            }
        }
    }
}
```

Chaque base exécute les mêmes migrations : les tables atteignent donc aussi les bases dédiées, où elles restent vides :
les adaptateurs les lisent et les écrivent dans la base partagée seulement.

## La connexion du stockage

Les adaptateurs doivent atteindre la base partagée quel que soit le locataire entré : lue par la connexion gérée, la
table viendrait de la base du locataire entré. Par défaut, ils partagent une connexion à eux,
`phil_tenancy_database.store_connection`, ouverte avec les paramètres et la configuration de la connexion gérée, fixée
sur la base partagée — la primaire, pour une primaire avec des répliques. Elle s'ouvre à sa première requête, et se
ferme à la fin de chaque requête et de chaque message, avec la réinitialisation du noyau, de sorte qu'un *worker* de
longue durée ne lit jamais par une connexion que le serveur a coupée.

Une application qui a déjà une connexion sur la base partagée qui ne suit pas le locataire la nomme :

```yaml
phil_tenancy_database:
    store_connection: platform
```

Les tables rejoignent alors le schéma de cette connexion. La connexion qui suit le locataire y est refusée.

## Le coût de chaque requête

| Question | Requêtes |
|---|---|
| `placementOf()`, à chaque entrée dans un locataire | Une, sur la clé primaire |
| `TenantAvailabilityGuard::check()`, à chaque requête et chaque message reçu | Deux, sur la clé primaire |
| `place()`, `suspend…()` | Un upsert : `INSERT … ON DUPLICATE KEY UPDATE` sur MySQL et MariaDB, `INSERT … ON CONFLICT` sur PostgreSQL |
| `resume…()` | Un `DELETE` sur la clé primaire |

Rien n'est gardé entre deux questions : un locataire déplacé, ou une base suspendue, par un processus est vu par la
prochaine unité de travail de chaque autre.

## Le pool de cache

`availability: cache` garde les suspensions dans `phil_tenancy_database.availability`, un pool sur `cache.app` que tous
les locataires partagent. Une entrée n'a pas de durée de vie, mais un pool qui évince des entrées sous la pression de
la mémoire, ou un cache vidé au déploiement, oublie une suspension : les locataires d'une base dont la migration a
échoué sont alors servis de nouveau, sur un schéma peut-être migré à moitié. Gardez-le pour le développement, ou pour
un pool qui n'évince jamais et qu'on ne vide jamais.

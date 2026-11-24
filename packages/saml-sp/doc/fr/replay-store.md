<!-- phil-dev source-sha256=7476e076eb407cbe69cef69ebbf7fcbf26c0d06f0bb485bfe22dc1edff6588fb -->
# Le magasin de rejeu

Une assertion est acceptée une seule fois : `ResponseProcessor` demande à son `ReplayGuard` si c'est la première
utilisation de l'assertion de cet émetteur, et la refuse sinon avec `ReplayedAssertion`. La garde retient chaque
assertion jusqu'à la dernière de ses expirations plus le décalage d'horloge. Trois gardes sont fournies avec la
bibliothèque :

| Garde | Où elle retient | Utilisez-la pour |
|---|---|---|
| `DbalReplayGuard` | Une table d'une connexion Doctrine DBAL | Un nombre quelconque de *workers* et de serveurs : la première utilisation est atomique. |
| `CacheReplayGuard` | Une réserve de cache PSR-6 | Un seul serveur, ou une réserve sur un magasin partagé, où deux copies au même instant sont peu probables. |
| `InMemoryReplayGuard` | La mémoire du processus | Les tests, ou un seul processus de longue durée. |

## `DbalReplayGuard`

```sh
composer require doctrine/dbal
```

```php
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Store\DbalReplayGuard;

$guard = new DbalReplayGuard($connection, $clock);           // the table phil_saml_used_assertion
$guard = new DbalReplayGuard($connection, $clock, 'sso_used_assertion');

$processor = new ResponseProcessor($clock, $guard);
```

Elle fonctionne sur PostgreSQL, MySQL, MariaDB et SQLite. La table contient une ligne par assertion acceptée :

| Colonne | Type | Contenu |
|---|---|---|
| `assertion` | `CHAR(64)`, clé primaire | Le SHA-256, en hexadécimal, de la longueur de l'émetteur, d'un deux-points, de l'émetteur et de l'ID de l'assertion. |
| `expires_at` | `datetime_utc_immutable`, indexée | Le moment où l'assertion peut être oubliée, en UTC. |

La première utilisation est un seul `INSERT`, sans lecture avant lui. La clé primaire fait de la base de données le
juge : de deux serveurs qui reçoivent la même assertion au même moment, l'un insère la ligne et l'autre obtient une
violation d'unicité, que `firstUse()` traduit par `false`. Sur PostgreSQL, l'insertion est
`INSERT … ON CONFLICT (assertion) DO NOTHING`, de sorte qu'un refus laisse utilisable une transaction ouverte ; MySQL,
MariaDB et SQLite laissent utilisable une transaction après une violation d'unicité.

- La clé est hachée : sa longueur est fixe quels que soient l'émetteur et l'ID, et aucun couple émetteur et ID ne la
  partage avec un autre. Elle se compare octet par octet : la colonne prend `ascii` et `ascii_bin` sur MySQL et MariaDB,
  dont les collations par défaut ignorent la casse ; PostgreSQL et SQLite comparent déjà les octets.
- L'expiration est écrite en UTC quel que soit le fuseau horaire de l'instant donné, et relue en UTC.
- Avant l'insertion, la ligne de la même assertion est supprimée si elle a expiré : une assertion échue ne prend aucune
  place et peut être acceptée de nouveau, comme les autres gardes le permettent.
- Le nom de la table compte au plus 63 lettres minuscules, chiffres ou `_`, sans commencer par un chiffre ; tout autre
  nom est refusé avec une `InvalidArgumentException`.

### La migration

L'application crée la table. `configureSchema()` l'ajoute à un `Doctrine\DBAL\Schema\Schema` qui ne l'a pas et
retourne tel quel un schéma qui l'a, pour une migration :

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Phil\SamlSp\Store\DbalReplayGuard;
use Symfony\Component\Clock\NativeClock;

final class Version20261120000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $target = new DbalReplayGuard($this->connection, new NativeClock())->configureSchema($schema);

        foreach ($this->platform->getAlterSchemaSQL($this->connection->createSchemaManager()->createComparator()->compareSchemas($schema, $target)) as $sql) {
            $this->addSql($sql);
        }
    }
}
```

Ou depuis un écouteur de l'événement `postGenerateSchema` de l'ORM, pour que les outils de schéma et les migrations
générées voient la table ; ou, sans migrations :

```php
$manager = $connection->createSchemaManager();
$manager->migrateSchema($guard->configureSchema($manager->introspectSchema()));
```

### La purge

Les lignes expirées restent jusqu'à leur purge. `purge(DateTimeImmutable $now)` supprime les lignes expirées à cet
instant et retourne combien elle en a supprimé ; lancez-la depuis une tâche planifiée, aussi souvent que le volume de
connexions l'exige — une fois par heure suffit largement à la plupart des applications.

```php
$guard->purge($clock->now());
```

## `CacheReplayGuard`

`CacheReplayGuard` garde chaque assertion dans une réserve PSR-6 sous le même genre de clé, jusqu'à son expiration. Elle
lit, puis écrit : deux copies d'une même assertion postées au même instant à deux serveurs pourraient passer toutes les
deux. Sa réserve est partagée par chaque *worker* et chaque serveur, et ce ne doit pas être une réserve qu'un
déploiement vide, sans quoi les assertions encore valides seraient oubliées.

Une réserve locale à chaque serveur — APCu, un tableau, des fichiers — ne retient une assertion que sur le serveur qui
l'a acceptée : la même assertion postée à un autre serveur est acceptée de nouveau. Une telle réserve convient à un seul
serveur.

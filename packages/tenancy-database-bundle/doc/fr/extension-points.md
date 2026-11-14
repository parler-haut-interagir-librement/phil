<!-- phil-dev source-sha256=dbbc84e151eb56f92497fa20f92ede479f48a5c0ff87da49404e3b3baeebdaee -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Point d'extension | Sorte | Implémenté par | Branchement |
|---|---|---|---|
| `Phil\TenancyBundle\Application\Contract\TenantPlacement` | port | le bundle (`ConfiguredPlacements`, `DbalPlacementStore`), ou l'application | `placement`, ou `dedicated` |
| `Application\Contract\PlacedTenants` | port | le même service que `TenantPlacement` | aucun ; les migrations le cherchent |
| `Application\Contract\PlacementStore` | port, étend `TenantPlacement` | le bundle (`DbalPlacementStore`), ou l'application | `placement` ; les déplacements le cherchent |
| `Application\Contract\TenantCredentials` | port | l'application | `credentials` |
| `Application\Contract\TenantDatabaseAvailability` | port | le bundle (`DbalTenantDatabaseAvailability`, `CacheTenantDatabaseAvailability`), ou l'application | `availability` |
| `migrations.command` | configuration | la commande de migration de l'application | toute commande qui migre la base où se trouve la connexion |

## `TenantPlacement`, `PlacedTenants` et `PlacementStore`

`placement: dbal` donne les trois ports sur un seul service, `DbalPlacementStore`, au-dessus d'une table de la base
partagée ([Emplacements et suspensions dans la base de données](stores.md)). Une application qui garde ses emplacements
ailleurs — dans sa propre table de locataires, à côté de leur forfait — les implémente sur un seul service :

| Méthode | Contrat |
|---|---|
| `placementOf(TenantId $tenant): Placement` | `Placement::shared()` ou `Placement::dedicated($name)` ; un locataire inconnu est partagé |
| `placedTenants(): iterable<TenantId>` | Chaque locataire qui a une base à lui ; les locataires partagés peuvent être omis |
| `place(TenantId $tenant, Placement $placement): void` | Durable une fois retourné : le `placementOf()` suivant, dans n'importe quel processus, le lit |

Invariants :

- `placementOf()` est demandé à chaque entrée dans un locataire : une requête indexée, ou un cache que `place()`
  invalide.
- Jamais sur la connexion gérée : elle lirait la table depuis la base où se trouve la connexion.
- `place()` est appelé en dernier dans un déplacement, une fois la copie vérifiée, et de nouveau avec l'ancien
  emplacement si le déplacement est annulé : il doit être idempotent.

```php
final readonly class TenantPlacements implements PlacementStore, PlacedTenants
{
    public function __construct(
        private Connection $platform,
    ) {
    }

    public function placementOf(TenantId $tenant): Placement
    {
        $database = $this->platform->fetchOne('SELECT database_name FROM tenant_placement WHERE tenant = ?', [$tenant->value]);

        return \is_string($database) ? Placement::dedicated($database) : Placement::shared();
    }

    public function place(TenantId $tenant, Placement $placement): void
    {
        $this->platform->executeStatement('DELETE FROM tenant_placement WHERE tenant = ?', [$tenant->value]);

        if (null !== $placement->database) {
            $this->platform->insert('tenant_placement', ['tenant' => $tenant->value, 'database_name' => $placement->database]);
        }
    }

    public function placedTenants(): iterable
    {
        foreach ($this->platform->fetchFirstColumn('SELECT tenant FROM tenant_placement') as $tenant) {
            yield TenantId::fromString((string) $tenant);
        }
    }
}
```

## `TenantCredentials`

| Méthode | Contrat |
|---|---|
| `credentialsFor(string $database): ?DatabaseCredentials` | Le compte, et facultativement l'hôte et le port, d'une base dédiée ; `null` pour ceux de la connexion partagée |

Demandé chaque fois que la connexion s'ouvre sur cette base, jamais pour la base partagée. Stockez les identifiants
scellés : voir [Identifiants](credentials.md).

## `TenantDatabaseAvailability`

| Méthode | Contrat |
|---|---|
| `suspendDatabase(Placement)`, `resumeDatabase(Placement)`, `isDatabaseSuspended(Placement)` | Une base dont la dernière migration a échoué |
| `suspendTenant(TenantId)`, `resumeTenant(TenantId)`, `isTenantSuspended(TenantId)` | Un locataire en cours de déplacement |

Invariants : partagé par chaque processus qui sert l'application — serveurs web, *workers* et console — et par chaque
locataire ; `isDatabaseSuspended()` et `isTenantSuspended()` sont demandés à chaque requête et à chaque message reçu,
gardez-les donc peu coûteux. Une suspension oubliée sert un locataire dont la base est peut-être migrée à moitié :
l'adaptateur par défaut, `DbalTenantDatabaseAvailability`, les garde dans une table de la base partagée ;
`CacheTenantDatabaseAvailability`, sur un pool de cache, ne convient qu'à un pool qui n'évince jamais et qu'on ne vide
jamais.

## `migrations.command`

Toute commande de console qui migre la base où se trouve la connexion gérée quand elle s'exécute : Doctrine
Migrations, une mise à jour du schéma, celle de l'application. Elle se termine par `0` en cas de succès ; tout autre
code, ou un dépassement de délai, suspend la base. Elle reçoit `--tenant=` pour une base dédiée, que
`phil/tenancy-bundle` ajoute à chaque commande.

<!-- phil-dev source-sha256=a43789dcbdb06f6a7ead41543795444ebcb0bcbad66890bcf3a3e9cf0acec181 -->
# phil/tenancy-database-bundle

**Une base de données pour chaque locataire, ou la base partagée, choisie à chaque requête sur une seule connexion Doctrine.**

phil/tenancy-database-bundle déplace la connexion Doctrine d'une application Symfony multilocataire vers la base de
données du locataire actif. Un locataire vit dans la base partagée, à côté des autres locataires partagés et séparé
d'eux par le filtre Doctrine de `phil/tenancy-bundle`, ou dans une base à lui. La connexion le suit chaque fois qu'une
requête, un message ou une commande entre dans le locataire. Un seul service de connexion sert tous les locataires :
aucune connexion n'est déclarée par locataire, et un nouveau locataire n'exige aucun déploiement.

Les emplacements et les suspensions vivent dans deux petites tables de la base partagée, ou dans le stockage propre à
l'application. Le bundle refuse de changer de base dans une transaction ouverte. Il vide les gestionnaires d'entités de la connexion à
chaque changement de locataire, pour qu'une entité chargée pour un locataire ne soit jamais remise à un autre. Dans un
*worker* de longue durée, la fin de chaque unité de travail ramène la connexion à la base partagée. Le bundle lance les
migrations de chaque base en nombre borné à la fois : une base qui échoue est suspendue, ses locataires reçoivent un
`503` et leurs messages attendent, pendant que les autres locataires sont servis. Il crée la base d'un nouveau
locataire, et déplace un locataire d'une base à une autre : copié, vérifié, et annulé au moindre échec.

C'est un bundle Symfony sur Doctrine DBAL et ORM et sur `phil/tenancy-bundle`, dont il répond au port
`TenantPlacement` à partir de sa configuration, de sa table ou du stockage propre à l'application. Le composant Console,
le composant Process, Messenger et les pools de cache de FrameworkBundle sont facultatifs. Il fonctionne sur MySQL,
MariaDB et PostgreSQL.

## État

| Partie | État |
|---|---|
| `TenantDatabaseRouter`, `TenantDatabaseMiddleware`, `TenantDatabaseDriver` | Implémenté et testé sur MySQL et PostgreSQL |
| `TenantConnectionSwitch` | Implémenté et testé sur MySQL et PostgreSQL ; une activation de locataire, réinitialisée avec le noyau |
| `ConfiguredPlacements`, `PlacedTenants`, `PlacementStore` | Implémenté et testé |
| `DbalPlacementStore`, `DbalTenantDatabaseAvailability`, `StoreTables`, `StoreSchemaListener`, `PinnedConnection` | Implémenté et testé sur MySQL et PostgreSQL ; la disponibilité par défaut |
| `TenantDatabaseAvailability`, `CacheTenantDatabaseAvailability`, `TenantAvailabilityGuard` | Implémenté et testé ; le pool de cache avec `availability: cache`, non durable |
| `RefuseUnavailableTenant`, `RefuseUnavailableTenantMiddleware` | Implémenté et testé ; le `503` et la nouvelle tentative de Messenger |
| `MigrationRunner`, `phil:tenancy:database:migrate` | Implémenté et testé sur MySQL et PostgreSQL |
| `TenantDatabaseCreator`, `phil:tenancy:database:create` | Implémenté et testé sur MySQL et PostgreSQL |
| `TenantMover`, `CopyPlan`, `phil:tenancy:database:move` | Implémenté et testé sur MySQL et PostgreSQL |
| `TenantCredentials`, `DatabaseCredentials`, `CredentialSeal` | Implémenté et testé |
| `PhilTenancyDatabaseBundle` | Configuration `phil_tenancy_database` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la connexion et l'ordre des bundles. |
| [Configuration](doc/fr/configuration.md) | Chaque option, ce que chacune enregistre, avec un exemple. |
| [La connexion de chaque locataire](doc/fr/connection.md) | Comment la connexion suit le locataire, les transactions, la table d'identité, les *workers*, Messenger, le cache et la sécurité de ligne. |
| [Emplacements et suspensions dans la base de données](doc/fr/stores.md) | Les deux tables de la base partagée, leur migration, la connexion sur laquelle elles sont lues, et le pool de cache qui n'est pas durable. |
| [Migrations et nouveaux locataires](doc/fr/migrations.md) | La migration de chaque base à concurrence bornée, un échec isolé et répondu par un `503`, et la base d'un nouveau locataire. |
| [Déplacer un locataire](doc/fr/moving-tenants.md) | Le déplacement d'une base à une autre, étape par étape, sa vérification, son annulation et ses limites. |
| [Identifiants](doc/fr/credentials.md) | Les bases dédiées atteintes avec un autre compte, le format scellé, et ce qui n'est jamais journalisé. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports à implémenter, les services à remplacer, et leurs invariants. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et comment une application teste son isolement. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```bash
composer require phil/tenancy-database-bundle
```

```php
// config/bundles.php, after PhilTenancyBundle
Phil\TenancyDatabaseBundle\PhilTenancyDatabaseBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_tenancy_database.yaml
phil_tenancy_database:
    placement: dbal   # the placements in a table of the shared database, which a move updates
```

```bash
bin/console doctrine:migrations:diff                         # the tables of the placements and the suspensions
bin/console doctrine:migrations:migrate
bin/console phil:tenancy:database:migrate                    # every database, a bounded number at a time
bin/console phil:tenancy:database:move retail tenant_retail  # to a database created and migrated beforehand
```

## Prérequis

PHP 8.5 ou une version plus récente avec l'extension sodium, Symfony 8.2 ou une version plus récente, Doctrine DBAL 4
et ORM 3, et `phil/tenancy-bundle`. Les bases sont MySQL, MariaDB ou PostgreSQL, toutes sur la plateforme de la
connexion partagée.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

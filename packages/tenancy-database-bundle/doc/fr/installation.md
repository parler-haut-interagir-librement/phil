<!-- phil-dev source-sha256=8bb5fa11c54c8317f3d0cb38833f378fc9ba19968e8e33a0d1451c565fff49b9 -->
# Installation

```sh
composer require phil/tenancy-database-bundle
```

Enregistrez le bundle après `phil/tenancy-bundle`, dont il reprend le contexte de locataire et les activations :

```php
// config/bundles.php
Phil\TenancyBundle\PhilTenancyBundle::class => ['all' => true],
Phil\TenancyDatabaseBundle\PhilTenancyDatabaseBundle::class => ['all' => true],
```

## Prérequis

- PHP 8.5 ou une version plus récente, avec l'extension sodium, que `CredentialSeal` utilise.
- Symfony 8.2 ou une version plus récente, Doctrine DBAL 4, Doctrine ORM 3 et DoctrineBundle.
- `phil/tenancy-bundle`, configuré : son filtre Doctrine sépare les locataires partagés, et son contexte de locataire
  dit à ce bundle quel locataire est entré.
- MySQL, MariaDB ou PostgreSQL. Chaque base — la partagée et chaque base dédiée — est sur la plateforme de la
  connexion partagée, et contient le même schéma.

Facultatifs :

| Paquet | Pour |
|---|---|
| `symfony/console`, `symfony/process` | Les commandes `phil:tenancy:database:*` ; chaque migration s'exécute dans un processus à part |
| `symfony/framework-bundle` | Le pool `phil_tenancy_database.availability`, pour les suspensions gardées avec `availability: cache` |
| `symfony/messenger` | `RefuseUnavailableTenantMiddleware`, qui réessaie plus tard les messages d'un locataire suspendu |

## La connexion

Le bundle prend une connexion Doctrine, `default` sauf si `connection` en nomme une autre, et la déplace de base en
base. Son URL ou ses paramètres nomment la base **partagée** ; une base dédiée est atteinte sur le même serveur, avec le
même compte, sous son propre nom, à moins que `TenantCredentials` dise autre chose (voir
[Identifiants](credentials.md)).

Gardez à part, sur une connexion à elles qui ne suit pas le locataire :

- le transport Messenger de Doctrine, s'il stocke les messages dans la base : sa table vit dans la base partagée ;
- le stockage propre des emplacements de l'application, quand elle les lit dans une table — les stockages `dbal` du
  bundle ouvrent une connexion à eux fixée sur la base partagée, ou utilisent `store_connection` ;
- tout ce que tous les locataires partagent, comme une table de sessions.

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default: { url: '%env(DATABASE_URL)%' }       # follows the tenant
            platform: { url: '%env(DATABASE_URL)%' }      # always the shared database
```

## Le compte de la connexion partagée

Les migrations et la création d'une base s'exécutent avec le compte de la connexion partagée. Pour créer la base d'un
nouveau locataire, il lui faut le droit de créer des bases (`CREATE` sur MySQL, `CREATEDB` sur PostgreSQL) ; sans lui,
un opérateur crée la base et la commande ne fait que la migrer. Une requête n'a besoin que des droits sur les tables.

## Le schéma de chaque base

Chaque base contient tout le schéma, y compris la colonne de locataire de chaque entité `#[TenantScoped]` : une base
dédiée est migrée avec les mêmes migrations, une ligne copiée garde sa référence de locataire, et le filtre Doctrine
continue de fonctionner quelle que soit la base du locataire. Les données de référence que tous les locataires lisent,
comme les devises, sont semées dans chaque base par ses migrations.

<!-- phil-dev source-sha256=2f7f17c598bee3a32db2e18a1574c31c7891378def6449c1f427fc2aa5254081 -->
# Migrations et nouveaux locataires

## Migrer chaque base

```sh
bin/console phil:tenancy:database:migrate
bin/console phil:tenancy:database:migrate --only=shared --only=tenant_wholesale --concurrency=8
```

La commande migre la base partagée, puis chaque base dédiée une fois, dans l'ordre de leurs noms. Chacune exécute
`migrations.command` dans un processus à part : la base partagée dans aucun locataire, une base dédiée avec `--tenant=`
le premier de ses locataires, dans lequel `phil/tenancy-bundle` entre et que la connexion suit. Au plus
`migrations.concurrency` processus s'exécutent à la fois.

Les bases dédiées viennent de `PlacedTenants::placedTenants()`, que le service d'emplacement implémente ; plusieurs
locataires qui partagent une même base dédiée la migrent une seule fois.

| Résultat | Ce qui suit |
|---|---|
| Le processus se termine par `0` | La base est reprise, si elle était suspendue. |
| Il se termine par un autre code | La base est suspendue : ses locataires reçoivent un `503`, leurs messages attendent. Les autres continuent. |
| Il dépasse `migrations.timeout` | Il est arrêté, et la base est suspendue. |

La commande affiche chaque base à mesure qu'elle termine, et les derniers 4 Kio de ce qu'une migration échouée a écrit.
Elle se termine par `1` tant qu'une base a échoué, pour qu'un déploiement puisse s'arrêter. La relancer — ou la
relancer avec `--only` pour la base échouée — reprend chaque base qui migre alors.

Ce qu'une migration écrit est montré sur la console et jamais journalisé : une erreur de connexion peut nommer un hôte
ou un utilisateur.

## Arrêter la commande

La commande écoute SIGINT et SIGTERM — Ctrl+C, un déploiement annulé, un conteneur arrêté — pour qu'aucune migration ne
lui survive :

1. Elle ne lance aucune autre migration.
2. Elle envoie SIGTERM à chaque migration encore en cours, les attend jusqu'à `migrations.grace_period` secondes, puis
   envoie SIGKILL à celles qui tournent encore, et les attend toutes. Un second signal saute l'attente.
3. Chaque migration arrêtée a pu laisser sa base à moitié migrée : la base est suspendue, comme après un échec. Une
   migration qui se termine par `0` entre-temps compte comme faite. Chaque base pas encore commencée est laissée telle
   quelle.
4. Elle affiche ce qu'elle n'a pas migré, et se termine par 128 plus le numéro du signal : `143` pour SIGTERM, `130`
   pour SIGINT.

```
✓ shared
✗ tenant_retail was stopped before it finished; its tenants are suspended.
- tenant_wholesale was not started.
1 of 3 databases migrated.
Stopped by signal 15; not migrated: tenant_retail, tenant_wholesale.
```

Relancez-la, ou relancez-la avec `--only` pour les bases qu'elle liste, afin de les migrer. L'exécuteur arrête ses
migrations de la même façon quand une exception le traverse, et à l'arrêt du processus après une erreur fatale. Un
SIGKILL envoyé à la commande elle-même ne peut pas être intercepté : ses migrations continuent alors jusqu'à leur fin,
et leurs bases gardent l'état de leur dernière migration.

`MigrationRunner::run()` accepte le `MigrationStop` à demander depuis du code qui l'exécute ailleurs, comme le
gestionnaire de signaux d'un worker.

## Un locataire suspendu

`RefuseUnavailableTenant` répond à une requête dans un locataire suspendu — sa base a échoué à sa dernière migration,
ou le locataire est en cours de déplacement — par un `503 Service Unavailable` et un `Retry-After` de `retry_after`
secondes. Le visiteur lit seulement que le site est en maintenance ; la raison reste dans la chaîne d'exceptions, pour
les journaux. La vérification s'exécute dès que le locataire de l'hôte est entré, avant que le pare-feu lise un compte
dans la base, et de nouveau une fois le locataire du compte résolu.

Les commandes ne sont jamais refusées, pour qu'un opérateur puisse migrer de nouveau.

Les suspensions sont gardées par `TenantDatabaseAvailability`. Par défaut, il les garde dans la table
`phil_tenancy_suspension` de la base partagée, où elles survivent à un cache vidé, à un déploiement et à un redémarrage,
jusqu'à ce qu'une migration de la base réussisse. `availability: cache` les garde plutôt dans un pool de cache, qu'une
éviction ou un cache vidé efface (voir [Emplacements et suspensions dans la base de données](stores.md)).

## La base d'un nouveau locataire

```sh
bin/console phil:tenancy:database:create wholesale
bin/console phil:tenancy:database:create wholesale --no-migrate
```

L'emplacement du locataire doit nommer une base dédiée. La commande la crée sur le serveur de la connexion partagée,
si elle n'existe pas encore, avec le compte de la connexion partagée, puis la migre comme le fait la commande de
migration ; une migration échouée la suspend. Un locataire dans la base partagée n'a besoin de rien.

Le nom de la base vient de `Placement::dedicated()`, qui n'accepte que des lettres ASCII minuscules, des chiffres et
des tirets bas, et il est de plus entre guillemets comme un identifiant. Une base sur un autre serveur, atteinte par
`TenantCredentials`, est créée par l'opérateur de ce serveur ; la commande ne fait alors que la migrer.

La base d'un locataire est créée dans aucun locataire : `TenantDatabaseCreator` refuse tant que la connexion est dans
la base d'un locataire.

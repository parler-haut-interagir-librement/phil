<!-- phil-dev source-sha256=e7606b02daa6515367bdcc4659c71b6e42e5fa7951de3b6edfb72bdccdd6baca -->
# Configuration

Chaque clé de `phil_backup`, avec sa valeur par défaut :

```yaml
phil_backup:
    connection: database_connection             # a Doctrine DBAL connection service
    directory: '%kernel.project_dir%/var/backup'
    binaries: {}                                # { pg_dump: /usr/lib/postgresql/17/bin/pg_dump }
    timeout: null                               # seconds; null, no limit
    key: null                                   # '%env(PHIL_BACKUP_KEY)%'; required unless allow_unencrypted
    allow_unencrypted: false                    # true, backups without a key are written in clear; with a key, older ones in clear are read
    media: {}                                   # { uploads: '%kernel.project_dir%/public/uploads' }
    rotation: { last: 0, daily: 0, weekly: 0, monthly: 0 }
    restore:
        production_environments: [prod]
        keep_before_restore: 3
    verify:
        database: null                          # the scratch database of a server's restore test
```

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `connection` | chaîne non vide | `database_connection` | Identifiant du service de la connexion Doctrine DBAL sauvegardée et restaurée ; sa plateforme choisit le client, et ses paramètres donnent les identifiants de connexion. |
| `directory` | chaîne non vide | `%kernel.project_dir%/var/backup` | L'endroit où les jeux sont écrits et trouvés, créé en `0700`. |
| `binaries` | table d'un nom d'outil vers un chemin | `{}` | Les chemins des outils clients qui ne sont pas dans le `PATH`. |
| `timeout` | nombre à virgule ou null | `null` | Le nombre de secondes pendant lesquelles un outil client peut s'exécuter. |
| `key` | chaîne ou null | `null` | La clé de sauvegarde, 32 octets aléatoires en base64 ; lue lorsqu'une commande en a besoin pour la première fois, ce qui permet une variable d'environnement. Exigée sauf si `allow_unencrypted` est vrai. |
| `allow_unencrypted` | booléen | `false` | Si les sauvegardes peuvent être écrites en clair lorsque `key` est null. Une clé, lorsqu'elle est donnée, est toujours utilisée, et une sauvegarde en clair est alors refusée par `phil:backup:restore` et `phil:backup:verify` — quiconque peut écrire dans le stockage des sauvegardes aurait pu l'y déposer — sauf si cette option est vraie, pour les sauvegardes écrites avant la clé. |
| `media` | table d'un nom vers un répertoire | `{}` | Les répertoires de médias sauvegardés à côté de la base de données, chacun sous un nom formé de lettres, de chiffres, de `-` et de `_`. |
| `rotation.last`, `daily`, `weekly`, `monthly` | entiers, 0 ou plus | `0` | Le nombre de jeux que garde chaque règle après chaque `phil:backup:create` ; tous à zéro, chaque jeu est gardé. Voir la rotation de phil/backup (`doc/rotation.md` de ce paquet). |
| `restore.production_environments` | liste de chaînes | `[prod]` | Les environnements du noyau dans lesquels une restauration exige `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` ; voir [restore-protection.md](restore-protection.md). |
| `restore.keep_before_restore` | entier, 1 ou plus | `3` | Le nombre de jeux étiquetés `before-restore` gardés une fois une restauration réussie ; les plus anciens sont supprimés. |
| `verify.database` | chaîne ou null | `null` | La base de travail, sur le même serveur, dans laquelle `phil:backup:verify` restaure une sauvegarde MySQL, MariaDB ou PostgreSQL ; avec `null`, elle n'est pas restaurée. |

## Services

| Identifiant de service | Classe | Visibilité | Définition |
|---|---|---|---|
| `Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner` | — | privé | À partir de `binaries` et de `timeout`. |
| `phil_backup.database_client` | `DatabaseClient` | privé | Construit par `DatabaseClients::forConnection()` à partir de `connection` et de l'exécuteur (`PhilBackupBundle::DATABASE_CLIENT`). |
| `Phil\Backup\Storage\BackupDirectory`, `Phil\Backup\Media\MediaArchive` | — | privé | À partir de `directory` et de `media`. |
| `Phil\Backup\Encryption\BackupKey`, `BackupCipher` | — | privé | Seulement avec une `key` : `BackupKey::fromBase64()` de la clé, et le chiffreur qui l'utilise. |
| `Phil\Backup\Rotation\RotationPolicy` | — | privé | Seulement lorsqu'une règle est supérieure à zéro. |
| `Phil\Backup\Restore\RestoreGuard` | — | privé | À partir de `%kernel.environment%` et de `restore.production_environments`. |
| `Phil\Backup\Backups` | — | public | Au-dessus du client, du répertoire, du chiffreur ou de null, de l'archive des médias et de la rotation ou de null. |
| `CreateBackupCommand`, `RestoreBackupCommand`, `VerifyBackupCommand` | — | privé | `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify` ; la restauration avec `restore.keep_before_restore`, la dernière avec `verify.database`. |

Rien ne se connecte à la base de données pendant la construction du conteneur ou la création du client : la plateforme
vient des paramètres de la connexion, ou de la version du serveur qu'ils donnent.

## Validation à la construction

Le composant Config refuse un `connection` ou un `directory` vide, un répertoire de médias vide, un compte de rotation
négatif et un `keep_before_restore` inférieur à 1. Une sauvegarde contient les données de chaque personne : une
configuration sans `key` est donc refusée, sauf si elle indique le contraire :

```
Invalid configuration for path "phil_backup": Backups are encrypted: give phil_backup.key, or set phil_backup.allow_unencrypted to true to write them in clear.
```

L'extension construit l'archive des médias une fois au chargement : un mauvais nom de média fait donc échouer la
construction du conteneur plutôt que la première sauvegarde :

```
phil_backup.media: The media name "up loads" holds characters other than letters, digits, "-" and "_".
```

Une clé qui n'est pas formée de 32 octets en base64 est refusée lorsqu'une commande construit `Backups` pour la première
fois, avec `A backup key is 32 random bytes in base64, such as the output of base64_encode(random_bytes(32)).`

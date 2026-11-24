<!-- phil-dev source-sha256=8bcb40913419385a1abb4de7917ad3b5ac8fb568b7a91c41454d3d1db747dbbf -->
# Configuration

Chaque clé de `phil_backup`, avec sa valeur par défaut :

```yaml
phil_backup:
    connection: database_connection             # a Doctrine DBAL connection service
    directory: '%kernel.project_dir%/var/backup'
    binaries: {}                                # { pg_dump: /usr/lib/postgresql/17/bin/pg_dump }
    timeout: null                               # seconds; null or 0, no limit
    warn_after: 3600                            # seconds; null or 0, no warning
    grace_period: 10                            # seconds between SIGTERM and SIGKILL past the timeout
    key: null                                   # '%env(PHIL_BACKUP_KEY)%'; required unless allow_unencrypted
    allow_unencrypted: false                    # true, backups without a key are written in clear; with a key, older ones in clear are read
    media: {}                                   # { uploads: '%kernel.project_dir%/public/uploads' }
    rotation: { last: 0, daily: 0, weekly: 0, monthly: 0 }
    restore:
        production_environments: [prod]
        keep_before_restore: 3
    lock:
        enabled: true                           # false, the commands run without a lock
        factory: null                           # a Symfony Lock factory service, such as lock.factory
        ttl: 86400                              # seconds, for a store whose locks expire
    monitor:
        max_age_hours: 26                       # phil:backup:monitor fails past it
    verify:
        database: null                          # the scratch database of a server's restore test
```

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `connection` | chaîne non vide | `database_connection` | Identifiant du service de la connexion Doctrine DBAL sauvegardée et restaurée ; sa plateforme choisit le client, et ses paramètres donnent les identifiants de connexion. |
| `directory` | chaîne non vide | `%kernel.project_dir%/var/backup` | L'endroit où les jeux sont écrits et trouvés, créé en `0700`. |
| `binaries` | table d'un nom d'outil vers un chemin | `{}` | Les chemins des outils clients qui ne sont pas dans le `PATH`. |
| `timeout` | nombre à virgule, 0 ou plus, ou null | `null` | Le nombre de secondes pendant lesquelles un outil client peut s'exécuter ; au-delà, il reçoit SIGTERM, puis SIGKILL après `grace_period`, et la sauvegarde, la restauration ou le test de restauration échoue avec `<tool> was still running after <timeout> seconds, the limit of phil_backup.timeout, and was stopped.` Avec null ou `0`, aucune limite : une grosse base de données prend le temps qu'il faut. |
| `warn_after` | nombre à virgule, 0 ou plus, ou null | `3600` | Le nombre de secondes après lequel un outil client toujours en cours est consigné comme avertissement, une seule fois, et annoncé par un événement `BackupToolRunningLong` ; voir [Outils lents ou bloqués](#outils-lents-ou-bloqués). Avec null ou `0`, jamais. |
| `grace_period` | nombre à virgule, 0 ou plus | `10` | Le nombre de secondes laissées à un outil client qui a dépassé son `timeout` pour s'arrêter après SIGTERM, avant qu'il reçoive SIGKILL. |
| `key` | chaîne ou null | `null` | La clé de sauvegarde, 32 octets aléatoires en base64 ; lue lorsqu'une commande en a besoin pour la première fois, ce qui permet une variable d'environnement. Exigée sauf si `allow_unencrypted` est vrai. |
| `allow_unencrypted` | booléen | `false` | Si les sauvegardes peuvent être écrites en clair lorsque `key` est null. Une clé, lorsqu'elle est donnée, est toujours utilisée, et une sauvegarde en clair est alors refusée par `phil:backup:restore` et `phil:backup:verify` — quiconque peut écrire dans le stockage des sauvegardes aurait pu l'y déposer — sauf si cette option est vraie, pour les sauvegardes écrites avant la clé. |
| `media` | table d'un nom vers un répertoire | `{}` | Les répertoires de médias sauvegardés à côté de la base de données, chacun sous un nom formé de lettres, de chiffres, de `-` et de `_`. |
| `rotation.last`, `daily`, `weekly`, `monthly` | entiers, 0 ou plus | `0` | Le nombre de jeux que garde chaque règle après chaque `phil:backup:create` ; tous à zéro, chaque jeu est gardé. Voir la rotation de phil/backup (`doc/rotation.md` de ce paquet). |
| `restore.production_environments` | liste de chaînes | `[prod]` | Les environnements du noyau dans lesquels une restauration exige `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` ; voir [restore-protection.md](restore-protection.md). |
| `restore.keep_before_restore` | entier, 1 ou plus | `3` | Le nombre de jeux étiquetés `before-restore` gardés une fois une restauration réussie ; les plus anciens sont supprimés. |
| `lock.enabled` | booléen | `true` | Si `phil:backup:create`, `phil:backup:restore` et `phil:backup:verify` tiennent le verrou exclusif du répertoire ; voir [Le verrou](commands.md#le-verrou). `lock: false` le désactive. |
| `lock.factory` | chaîne ou null | `null` | L'identifiant d'un service `LockFactory` de Symfony Lock, tel que `lock.factory` avec un magasin que plusieurs machines partagent ; avec `null`, une fabrique au-dessus d'un magasin flock du répertoire temporaire du système, qui sert les commandes d'une seule machine. |
| `lock.ttl` | nombre à virgule, 1 ou plus | `86400` | Le nombre de secondes que le verrou vit au plus dans un magasin dont les verrous expirent, tel que Redis : plus long que la plus longue sauvegarde, restauration ou test, sinon le verrou expire pendant que la commande s'exécute. Le magasin flock n'expire jamais et l'ignore. |
| `monitor.max_age_hours` | entier, 1 ou plus | `26` | Le nombre d'heures que peut avoir au plus la dernière sauvegarde sans étiquette avant que `phil:backup:monitor` échoue : une sauvegarde de nuit, et deux heures pour qu'elle s'exécute en retard. |
| `verify.database` | chaîne ou null | `null` | La base de travail, sur le même serveur, dans laquelle `phil:backup:verify` restaure une sauvegarde MySQL, MariaDB ou PostgreSQL ; avec `null`, elle n'est pas restaurée. |

## Services

| Identifiant de service | Classe | Visibilité | Définition |
|---|---|---|---|
| `Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner` | — | privé | À partir de `binaries`, de `timeout`, de `warn_after` et de `grace_period`, avec le service `logger` sur le canal `backup` et le service `event_dispatcher` lorsqu'ils existent. |
| `phil_backup.database_client` | `DatabaseClient` | privé | Construit par `DatabaseClients::forConnection()` à partir de `connection` et de l'exécuteur (`PhilBackupBundle::DATABASE_CLIENT`). |
| `Phil\Backup\Storage\BackupDirectory`, `Phil\Backup\Media\MediaArchive` | — | privé | À partir de `directory` et de `media`. |
| `Phil\Backup\Encryption\BackupKey`, `BackupCipher` | — | privé | Seulement avec une `key` : `BackupKey::fromBase64()` de la clé, et le chiffreur qui l'utilise. |
| `Phil\Backup\Rotation\RotationPolicy` | — | privé | Seulement lorsqu'une règle est supérieure à zéro. |
| `Phil\Backup\Restore\RestoreGuard` | — | privé | À partir de `%kernel.environment%` et de `restore.production_environments`. |
| `Phil\Backup\Backups` | — | public | Au-dessus du client, du répertoire, du chiffreur ou de null, de l'archive des médias et de la rotation ou de null. |
| `phil_backup.lock_factory` | `Symfony\Component\Lock\LockFactory` | privé | Seulement avec le verrou activé et sans `lock.factory` : au-dessus d'un `FlockStore` du répertoire temporaire du système (`PhilBackupBundle::LOCK_FACTORY`). |
| `Phil\BackupBundle\Infrastructure\Lock\BackupLock` | — | privé | Seulement avec le verrou activé : au-dessus de la fabrique, de `directory` et de `lock.ttl`. |
| `CreateBackupCommand`, `RestoreBackupCommand`, `VerifyBackupCommand` | — | privé | `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify`, chacune avec le verrou ou null ; la restauration avec `restore.keep_before_restore`, le test avec `verify.database`. |
| `MonitorBackupCommand` | — | privé | `phil:backup:monitor`, avec `monitor.max_age_hours` et le service `clock` quand il existe. |

Rien ne se connecte à la base de données pendant la construction du conteneur ou la création du client : la plateforme
vient des paramètres de la connexion, ou de la version du serveur qu'ils donnent.

## Outils lents ou bloqués

Une sauvegarde n'a pas de durée maximale par défaut, pour qu'une grosse sauvegarde ne soit jamais interrompue. Pour
distinguer une sauvegarde lente d'une sauvegarde bloquée sans arrêter ni l'une ni l'autre, l'exécuteur surveille chaque
outil client pendant qu'il s'exécute : une fois qu'il a tourné `warn_after` secondes, il consigne, une seule fois, sur le
canal `backup`,

```
The backup tool {tool} is still running after {seconds} seconds.
```

avec le nom de l'outil, son identifiant de processus et le seuil dans le contexte, et distribue un événement
`Phil\BackupBundle\Application\Event\BackupToolRunningLong` qui porte les trois mêmes valeurs — des identifiants
seulement, jamais la ligne de commande. Un écouteur peut lever une alerte, puis vérifier plus tard si le processus
s'exécute toujours.

```php
use Phil\BackupBundle\Application\Event\BackupToolRunningLong;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class AlertOnSlowBackup
{
    public function __invoke(BackupToolRunningLong $event): void
    {
        // $event->tool, $event->pid, $event->seconds
    }
}
```

Donnez aussi un `timeout` pour arrêter un outil qui a duré trop longtemps : il reçoit SIGTERM, puis SIGKILL s'il ne
s'est pas arrêté après `grace_period` secondes, et la commande échoue avec une `BackupFailed`.

## Validation à la construction

Le composant Config refuse un `connection` ou un `directory` vide, un répertoire de médias vide, un compte de rotation
négatif, un `timeout`, un `warn_after` ou un `grace_period` négatif, et un `keep_before_restore` inférieur à 1. Une sauvegarde contient les données de chaque personne : une
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

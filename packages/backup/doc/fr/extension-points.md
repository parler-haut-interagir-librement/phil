<!-- phil-dev source-sha256=31eb44520f56565808ee98a01900da8222a36b49f92471fc88c3fe134fdd0d10 -->
# Points d'extension

## `CommandRunner`

La bibliothèque ne lance jamais elle-même un processus. C'est le `CommandRunner` donné à chaque client qui le fait :

| Méthode | Contrat |
|---|---|
| `find($candidates)` | Le chemin du premier outil installé de la liste ; `BackupFailed::missing($candidates, $remedy)` si aucun ne l'est. |
| `run($command, $environment = [], $input = null)` | Exécute la commande — une liste d'arguments, jamais par un shell — avec ces variables d'environnement ajoutées, et le fichier comme entrée standard s'il est donné ; retourne sa sortie standard ; `BackupFailed::failed($tool, $errorOutput)` si elle se termine en erreur. |

`phil/backup-bundle` en fournit un au-dessus de Symfony Process, sans limite de temps sauf si elle est configurée. Votre
propre exécuteur doit passer chaque argument tel quel, donner l'environnement à l'outil seul et ne garder aucune ligne
de commande dans un journal : il peut nommer un fichier d'options, jamais un mot de passe.

## `DatabaseClient`

Une autre base de données, ou une autre façon d'en copier une — l'API d'instantanés d'un service géré, par exemple —,
est un `DatabaseClient` :

| Méthode | Contrat |
|---|---|
| `identity()` | La plateforme, l'hôte et le nom ; les messages, les noms de fichiers et la garde de restauration les lisent. |
| `extension()` | L'extension d'un fichier de sauvegarde, sans son point. |
| `backup($file)` | Une sauvegarde complète et cohérente dans le fichier ; `BackupFailed` si elle ne peut pas être faite. |
| `restore($file)` | Le contenu de la base de données remplacé par la sauvegarde, en entier ou pas du tout là où la plateforme le permet ; `BackupFailed` si le fichier manque ou ne peut pas être restauré. |
| `withDatabase($name)` | Le même client pour une autre base de données du même serveur. |
| `check()` | Vérifie une copie restaurée au-delà de la réussite de l'outil, ou ne fait rien ; `BackupFailed` si la copie échoue. |

Un client doit garder les identifiants hors des lignes de commande, et doit refuser une restauration à partir d'un
fichier qui n'existe pas plutôt que de vider la base de données.

## Bâtir sur `Backups`

Chaque partie de `Backups` peut servir seule : `BackupCipher` pour tout fichier, `MediaArchive` pour tout ensemble de
répertoires, `RotationPolicy` sur toute liste de `BackupSet`, `RestoreGuard` devant toute restauration.

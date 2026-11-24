<!-- phil-dev source-sha256=a93e76add6f3f6dd1d501a17c08afc0966f9abdf695a5da82fcad4e054834ecb -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Ports écrits par l'application

Chaque port est une interface de `Phil\FollowBundle\Application\Contract`, nommée dans la configuration.

### `FollowAccess` (`access`)

| Méthode | Contrat |
|---|---|
| `filter(SubjectReference $subject, list<string> $members): list<string>` | Les membres autorisés à suivre le sujet et à apprendre son activité, dans l'ordre donné ; demandé une fois par page d'abonnés, donc une requête par page |

`OpenAccess` autorise tout le monde. Obligatoire avec Symfony Security.

### `FollowApproval` (`approval`)

| Méthode | Contrat |
|---|---|
| `approverOf(SubjectReference $subject, string $follower): ?string` | Le membre qui approuve un nouvel abonné, ou null pour un sujet ouvert |

`OpenFollowing`, la valeur par défaut, laisse chaque sujet ouvert.

### `SubjectParents` (`parents`)

| Méthode | Contrat |
|---|---|
| `parentsOf(SubjectReference $subject): iterable<SubjectReference>` | Les parents, du plus proche au plus lointain ; lus jusqu'à `inheritance.max_depth` |

`NoParents`, la valeur par défaut, n'en donne aucun.

### `CurrentRecipient` (`current_member`)

Le `CurrentRecipient` de phil/notifications-bundle nomme le membre courant des formulaires et des gabarits ; celui de ce
bundle quand `current_member` est nul.

## Services que l'application appelle

| Service | Ce qu'il fait |
|---|---|
| `Follows` | `follow()`, `unfollow()`, `state()`, `following()`, `seen()` |
| `FollowRequests` | `pendingFor()`, `sentBy()`, `accept()`, `reject()`, `withdraw()` |
| `Mutuals` | `request()`, `end()`, `areMutual()`, `pending()`, `of()`, `enabled()` |
| `Followers` | `followersOf()`, `count()`, `isHidden()`, `followersHidden()`, `hideFollowers()` |
| `AutoFollow` | `created()`, `replied()` |
| `Activities` | `publish()` |

## Messages

| Message | Handler | Porte |
|---|---|---|
| `FanOutActivity` | `FanOutActivityHandler` | L'identifiant de l'activité et le dernier membre de la page précédente |
| `PurgeFollowRecords` | `PurgeFollowRecordsHandler` | Rien |

## Intégrations

| Avec | Le bundle |
|---|---|
| phil/notifications-bundle | Envoie par `Notifications`, déclare ses types, les traduit |
| ORM de Doctrine | Ajoute ses tables au schéma généré |
| Symfony Messenger | Envoie les pages par des messages `FanOutActivity` |
| Symfony Scheduler | Purge chaque jour, avec un verrou et un état quand ils existent |
| phil/privacy-bundle | Exporte et efface les enregistrements d'un membre |
| Twig | Enregistre ses fonctions, ses gabarits et ses formulaires |

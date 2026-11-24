<!-- phil-dev source-sha256=45d91d1e67402f2f1c5f9e566cd659620916d7eb9c5075fd36e7b1ca952bb2ba -->
# Configuration

La configuration du bundle se trouve sous `phil_follow`. Chaque option a une valeur par défaut ; avec Symfony Security,
`access` est obligatoire.

```yaml
# config/packages/phil_follow.yaml
phil_follow:
    connection: default               # the Doctrine connection of the tables
    member_subject_type: member       # the subject type that names a member
    batch_size: 500                   # followers served by one message, 1 to 1000
    message_bus: null                 # the bus of FanOutActivity; messenger.default_bus when null
    current_member: null              # a CurrentRecipient service; phil_notifications' one when null
    access: App\Follow\SubjectAccess  # a FollowAccess service; required with Symfony Security
    approval: App\Follow\LockedProfiles   # a FollowApproval service; every subject open when null
    parents: App\Follow\ForumParents  # a SubjectParents service; no parents when null
    inheritance:
        max_depth: 5                  # parents read, nearest first, 0 to 16
    events: [reply.created, poll.opened]  # the kinds of event offered at the custom level
    auto_follow:
        created: false                # AutoFollow::created() follows what a member creates
        replied: false                # AutoFollow::replied() follows what a member replies to
        level: watching               # watching or mentions
    mutual:
        enabled: false                # mutual relationships between members
        follow_each_other: true       # an accepted relationship makes each follow the other
    followers:
        hidden_by_default: false      # whether a member who never chose hides their followers
    email:
        once_until_seen: true         # one notification on every channel until the member sees the subject
        resend_after: 604800          # seconds unseen after which it goes out on every channel again
    notifications:
        declare_types: true           # declare the notification types in phil_notifications.types
        activity: follow.activity
        quiet: follow.activity_quiet
        requested: follow.requested
        accepted: follow.accepted
        followed: follow.followed
    retention:
        activity_days: 30             # days an activity and the claims of its deliveries are kept
    schedule:
        enabled: true                 # the schedule phil_follow, with Symfony Scheduler
        at: '04:15'
        timezone: UTC
        lock_factory: lock.factory    # keeps the schedule to one worker; used when it exists
        state_pool: cache.app         # keeps the last run; used when it exists
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `connection` | `default` | La connexion Doctrine des tables. |
| `member_subject_type` | `member` | Le type de sujet dont l'identifiant est un membre : le suivre, c'est suivre une personne, qui en est informée, peut verrouiller son profil et cacher ses abonnés. |
| `batch_size` | `500` | Combien d'abonnés un message `FanOutActivity` dessert, de 1 à 1000, le maximum qu'une notification nomme. |
| `message_bus` | `null` | Le bus des messages `FanOutActivity` ; `messenger.default_bus` quand il existe, aucun sinon, les pages se suivant alors d'un seul coup. |
| `current_member` | `null` | Le service qui nomme le membre courant, un `CurrentRecipient` de phil/notifications-bundle ; celui de ce bundle quand la valeur est nulle. |
| `access` | `null` | Le service `FollowAccess`. Obligatoire avec Symfony Security ; `Phil\FollowBundle\Application\OpenAccess` ouvre chaque sujet à chaque membre. |
| `approval` | `null` | Le service `FollowApproval`, qui nomme l'approbateur d'un sujet verrouillé ; chaque sujet est ouvert quand la valeur est nulle. |
| `parents` | `null` | Le service `SubjectParents` ; aucun sujet n'a de parents quand la valeur est nulle. |
| `inheritance.max_depth` | `5` | Combien de parents d'un sujet sont lus, de 0 à 16. |
| `events` | `[]` | Les sortes d'événement pour lesquelles les gabarits offrent un niveau au niveau personnalisé. |
| `auto_follow.created`, `auto_follow.replied` | `false` | Si `AutoFollow` suit ce qu'un membre crée, ou ce à quoi il répond. |
| `auto_follow.level` | `watching` | Le niveau d'un suivi automatique, `watching` ou `mentions`. |
| `mutual.enabled` | `false` | Si les membres peuvent former des relations mutuelles. |
| `mutual.follow_each_other` | `true` | Si une relation acceptée fait suivre chaque membre par l'autre, sauf s'il a déjà choisi un niveau. |
| `followers.hidden_by_default` | `false` | Si un membre qui n'a jamais choisi cache ses abonnés. |
| `email.once_until_seen` | `true` | Si, après une notification envoyée sur tous les canaux, les activités suivantes du sujet restent dans le centre jusqu'à ce que le membre voie le sujet. |
| `email.resend_after` | `604800` | Après combien de secondes sans consultation une notification repart sur tous les canaux. |
| `notifications.declare_types` | `true` | Si le bundle déclare ses cinq types sous leurs clés par défaut dans `phil_notifications.types`. |
| `notifications.*` | `follow.*` | Les clés des types de notification que le bundle envoie. Un type renommé est déclaré par l'application. |
| `retention.activity_days` | `30` | Combien de jours une activité et les revendications de ses livraisons sont conservées. |
| `schedule.enabled` | `true` | Si le calendrier `phil_follow` purge chaque jour, quand Symfony Scheduler est installé. |
| `schedule.at`, `schedule.timezone` | `04:15`, `UTC` | Quand la purge s'exécute. |
| `schedule.lock_factory` | `lock.factory` | La fabrique de verrous qui limite le calendrier à un worker, utilisée quand le service existe ; nulle pour aucune. |
| `schedule.state_pool` | `cache.app` | Le groupe de cache qui garde la dernière exécution du calendrier, utilisé quand il existe ; nul pour aucun. |

## Les types de notification

Avec `notifications.declare_types`, le bundle déclare dans `phil_notifications.types` :

| Type | Canaux | Envoyé quand |
|---|---|---|
| `follow.activity` | `site`, `email`, regroupé | Une activité atteint un abonné |
| `follow.activity_quiet` | `site`, regroupé, sans notification instantanée | Une activité atteint un abonné qui n'a pas vu le sujet depuis le dernier `follow.activity` |
| `follow.requested` | `site`, `email` | Une demande attend son approbateur |
| `follow.accepted` | `site` | Une demande est acceptée |
| `follow.followed` | `site` | Un membre gagne un abonné |

Les entrées `phil_notifications.types` de l'application, sous les mêmes clés, les modifient : leurs canaux, leur
importance, si le courriel attend le résumé. Les titres et les corps sont traduits dans le domaine
`phil_notifications`, en anglais et en français par le bundle.

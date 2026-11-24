<!-- phil-dev source-sha256=384d555b6d8fe224074cb0dbb1e04f7680083fbd7267dd98e97f383a61c2eb50 -->
# Livraison

## Publier une activité

L'application publie ce qui se passe sur un sujet :

```php
use Phil\FollowBundle\Application\Activities;

$activities->publish(
    new SubjectReference('thread', $thread->id),
    'reply.created',          // the kind of event, a key
    $author->id,              // the actor, who never hears of their own activity
    $mentionedIds,            // up to 200 members, who hear of it at the mentions level
    'reply:'.$reply->id,      // the application's identifier; a UUID v7 otherwise
);
```

L'activité est conservée dans `phil_follow_activities`, puis envoyée par un message `FanOutActivity` qui ne porte que
son identifiant. Publiée de nouveau sous le même identifiant — une requête répétée, un second écouteur —, elle est
conservée une fois et envoyée une fois à chaque abonné.

Le bundle n'envoie rien à un membre mentionné qui ne suit pas le sujet : l'application notifie elle-même ses mentions,
avec son propre type de notification.

## Les pages

Le handler de `FanOutActivity` relit l'activité à partir de son identifiant — une activité purgée entre-temps n'est
envoyée à personne — et la chaîne de son sujet et de ses parents. Il dessert ensuite les abonnés une page de
`batch_size` membres à la fois :

1. deux requêtes lisent les membres de la page, dans l'ordre des octets après le dernier membre de la page précédente,
   et chaque suivi qu'ils détiennent sur la chaîne — une seule quand le sujet n'a pas de parents ;
2. le suivi le plus proche de chaque membre décide, selon son niveau, si l'activité l'atteint ;
3. `FollowAccess` écarte les membres qui ne peuvent plus voir le sujet ;
4. la livraison est revendiquée pour chaque membre : un `INSERT` qui saute les membres déjà revendiqués, puis une
   lecture des lignes qui portent le jeton de cette tentative ;
5. les membres revendiqués reçoivent la notification par `Notifications::send()` de phil/notifications-bundle, un seul
   appel pour la page, qui la divise en un message `SendNotification` par destinataire sur le bus.

Avec un bus, une page pleine se termine par le message `FanOutActivity` de la suivante, après son dernier membre, de
sorte que chaque message fait une quantité bornée de travail ; sans bus, les pages se suivent d'un seul coup. Une page
coûte sept requêtes, quelle que soit sa taille.

## Aucune livraison en double

Une revendication est conservée par activité et par membre. Un message répété après un échec, une page lue deux fois ou
deux workers sur le même message envoient à chaque membre une seule fois : les membres revendiqués par une tentative
antérieure sont laissés de côté. Quand l'appel aux notifications échoue, les revendications de ses membres sont
rendues et l'exception remonte, pour que Messenger réessaie le message. Chaque notification porte aussi la clé de
doublons `follow:<activity>`, que lit la fenêtre de doublons de phil/notifications-bundle quand elle est activée.

## Une notification sur tous les canaux jusqu'à la consultation

Avec `email.once_until_seen`, un membre qui a reçu un `follow.activity` à propos d'un sujet — tous les canaux du type,
courriel compris — reçoit les activités suivantes de ce sujet sous la forme `follow.activity_quiet`, gardées dans le
centre, jusqu'à ce qu'il voie le sujet ou que `email.resend_after` secondes soient écoulées. L'application dit quand un
membre voit un sujet :

```php
$follows->seen($member, new SubjectReference('thread', $thread->id));
```

Les deux types sont regroupés : le centre montre une notification par sujet et par type, avec le nombre d'activités
qu'elle représente.

## Le résumé

Le résumé est celui de phil/notifications-bundle : un membre qui a choisi un résumé quotidien ou hebdomadaire reçoit le
courriel de `follow.activity`, qui le permet, dans son résumé plutôt que sur-le-champ, regroupé par sujet. Le bundle n'a
besoin d'aucun calendrier propre pour cela.

## Les données d'une notification

| Champ | Valeur |
|---|---|
| `type` | `follow.activity` ou `follow.activity_quiet` |
| `subject` | Le sujet de l'activité |
| `actor` | L'acteur, ou aucun |
| `data` | `event`, la sorte d'événement, et `activity`, son identifiant |
| `dedupeKey` | `follow:<activity>` |

Les traductions des types du bundle reçoivent `%subject%`, `%actor%`, `%count%`, `%event%` et `%activity%`.

## La purge

`PurgeFollowRecords`, que le calendrier `phil_follow` envoie chaque jour à `schedule.at`, supprime mille lignes à la
fois, cent fois au plus par exécution : les activités plus anciennes que `retention.activity_days`, les revendications
de leurs livraisons, et les marques de notifications plus anciennes que `email.resend_after`. Avec une fabrique de
verrous, le calendrier s'exécute sur un seul worker, peu importe combien le consomment ; avec un groupe de cache, il
garde sa dernière exécution, de sorte qu'un worker qui redémarre ne la répète ni ne la saute.

## Les workers

Chaque service du bundle ne garde aucun état entre les messages ou les requêtes, comme un worker de longue durée l'exige.

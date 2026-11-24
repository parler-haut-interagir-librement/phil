<!-- phil-dev source-sha256=e6cbceb51d4f5f74ff1af3a9fa04049fcf3d1763ee8a4c8f18ac2a72cf775616 -->
# Suivre

## Les sujets

Un sujet est tout enregistrement de l'application, nommé par un `SubjectReference` de phil/notifications-bundle : un
type — une clé comme `thread` ou `member` — et un identifiant. Le type `member_subject_type`, `member` par défaut, nomme
un membre : le suivre, c'est suivre une personne.

```php
use Phil\FollowBundle\Application\Follows;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

$follows->follow($member, new SubjectReference('thread', $thread->id));
```

Les membres, les acteurs et les activités sont des identifiants — lettres, chiffres, `_`, `.`, `:` et `-`, 190
caractères au plus —, jamais un nom ni une adresse : une `InvalidFollow` le dit, sans répéter la valeur.

## Les niveaux

| Niveau | Ce qui atteint le membre |
|---|---|
| `watching` | Chaque activité |
| `mentions` | Les activités qui le mentionnent |
| `ignoring` | Rien, pas même ce qu'un parent suivi apporterait |
| `custom` | Pour chaque sorte d'événement, le niveau que le membre a choisi — `watching`, `mentions` ou `ignoring` — ; `mentions` pour un événement qu'il n'a pas nommé |

```php
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;

$follows->follow($member, $forum, FollowLevel::Mentions);
$follows->follow($member, $thread, FollowLevel::Custom, new EventPreferences([
    'reply.created' => FollowLevel::Watching,
    'edit.made' => FollowLevel::Ignoring,
]));
```

Appeler `follow()` de nouveau change le niveau ; un niveau inchangé n'est pas écrit. Les préférences par événement, 64
au plus, ne sont conservées qu'au niveau personnalisé. Ignorer un sujet n'est pas le suivre : un membre qui ignore n'est
pas parmi les abonnés.

`unfollow()` supprime le suivi du membre, et retire sa demande de suivre le sujet quand une demande attend. Un membre ne
se suit jamais lui-même : `FollowRefused`.

## L'héritage

L'application nomme les parents d'un sujet, du plus proche au plus lointain, avec un service `SubjectParents` — le fil
d'une réponse, le forum d'un fil, la catégorie d'un forum :

```php
final readonly class ForumParents implements SubjectParents
{
    public function parentsOf(SubjectReference $subject): iterable
    {
        if ('thread' === $subject->type) {
            yield new SubjectReference('forum', $this->threads->forumOf($subject->id));
        }
    }
}
```

Pour chaque membre, le suivi du sujet le plus proche de la chaîne décide : ignorer un fil l'emporte sur surveiller son
forum, et surveiller un fil l'emporte sur ignorer son forum. La chaîne s'arrête à `inheritance.max_depth` parents et au
premier parent vu deux fois. `Follows::state()` donne le suivi propre du membre, sinon le suivi du parent le plus proche
dont il hérite, et si une demande de sa part attend.

## L'accès

Un service `FollowAccess` dit qui peut suivre un sujet et apprendre son activité. Il est interrogé quand un membre suit
un sujet à un niveau autre qu'ignorer, et de nouveau pour chaque page d'abonnés à qui une activité est envoyée, de sorte
qu'un membre qui a perdu l'accès à un sujet privé n'en apprend plus rien :

```php
final readonly class SubjectAccess implements FollowAccess
{
    public function filter(SubjectReference $subject, array $members): array
    {
        return $this->groups->membersAmong($subject, $members); // one query for the page
    }
}
```

Avec Symfony Security, l'application le nomme dans `access`. `OpenAccess` laisse chaque membre suivre chaque sujet.

## Les demandes

Un service `FollowApproval` nomme l'approbateur d'un sujet — le membre d'un profil verrouillé, le propriétaire d'un
groupe privé —, ou null pour un sujet ouvert. Suivre un sujet qui a un approbateur crée une demande à la place, au
niveau demandé, et en informe l'approbateur avec `follow.requested` ; demander de nouveau attend sur la même demande.
Ignorer n'exige aucune approbation ; un membre qui a ignoré un sujet verrouillé doit quand même demander à le suivre.

```php
$outcome = $follows->follow($member, new SubjectReference('member', $locked->id)); // FollowOutcome::Requested

$requests->accept($approver, $requestId);   // the follow is made, the requester told with follow.accepted
$requests->reject($approver, $requestId);   // the request is deleted, without a word
$requests->withdraw($member, $requestId);   // the requester withdraws it
```

`FollowRequests::pendingFor()` liste les demandes qu'un membre doit trancher, de la plus ancienne à la plus récente ;
`sentBy()` celles qu'il a envoyées. Une demande que personne n'attend, ou qu'un autre membre approuve, est aussi une
`UnknownFollowRequest`, pour que personne n'apprenne les demandes d'un autre membre. De deux réponses simultanées, la
seconde trouve la demande disparue.

## Les relations mutuelles

Avec `mutual.enabled`, deux membres peuvent former une relation mutuelle à côté du suivi à sens unique : l'un demande,
l'autre accepte par les mêmes demandes ; deux membres qui se demandent l'un l'autre sont unis aussitôt.

```php
$mutuals->request($member, $other);   // FollowOutcome::Requested, or Done when $other had asked
$mutuals->areMutual($member, $other);
$mutuals->of($member);                // the other members, in byte order
$mutuals->end($member, $other);       // ends it, and withdraws the requests between them
```

Une relation acceptée fait suivre chaque membre par l'autre au niveau de surveillance, sauf s'il a déjà choisi un
niveau — un membre qui ignore le reste ainsi — ou si `mutual.follow_each_other` est faux. Cesser de suivre l'autre, ou
l'ignorer, laisse la relation telle quelle : seul `end()` y met fin.

## Les suivis automatiques

Avec `auto_follow.created` ou `auto_follow.replied`, l'application appelle `AutoFollow` là où un membre crée ou répond :

```php
$autoFollow->created($author, new SubjectReference('thread', $thread->id));
$autoFollow->replied($author, new SubjectReference('thread', $thread->id));
```

Le membre suit alors le sujet à `auto_follow.level`, sauf s'il a déjà choisi un niveau — ignorer compris —, si le sujet
a un approbateur, ou s'il ne peut pas le suivre.

## Un membre suivi

Suivre un sujet de type membre en informe ce membre avec `follow.followed`. `Followers::followersOf()` liste les abonnés
d'un sujet une page à la fois, avec leur nombre ; un membre qui cache ses abonnés ne les montre qu'à lui-même. Voir
[Vie privée](privacy.md).

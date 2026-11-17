<!-- phil-dev source-sha256=70525d6f7cf6314236b2ccf7d7add3c043f475eaaa34246229f343fd3572543c -->
# Sondages et bulletins

## Créer un sondage

`Polls::create()` accepte un `PollDraft` et l'acteur qui le crée, consulte le `mayCreate()` de la politique, et
conserve le sondage :

| `PollDraft` | Valeur par défaut | Ce qu'il règle |
|---|---|---|
| `question` | — | Un texte UTF-8 de 1 à 500 caractères. |
| `options` | — | De 2 à 100 `PollOption` : un identifiant de 1 à 64 lettres ASCII, chiffres, `_` ou `-`, et un libellé de 1 à 200 caractères ; les notes d'une échelle, écrites par `PollDraft::marks()`. |
| `kind` | `BallotKind::Single` | Une option, plusieurs, des options classées, ou une note sur une échelle. |
| `minChoices`, `maxChoices` | 1, toutes les options | Les bornes d'un bulletin à choix multiple ou classé ; un bulletin à choix unique et une échelle les ignorent. |
| `abstention` | `false` | Indique si un bulletin blanc est une abstention ou s'il est refusé. |
| `counting` | celle du type | Le nom de la méthode de dépouillement : `choices` pour les bulletins à choix unique et multiple, `instant_runoff` pour les bulletins classés, `scale` pour une échelle, par défaut ; `nps` pour un Net Promoter Score. |
| `anonymous` | `false` | Indique si les bulletins sont gardés à l'écart de leurs votants : voir [Anonymat](anonymity.md). |
| `results` | `ResultsVisibility::Always` | Qui voit les résultats : voir [Résultats](results.md). |
| `subject` | `null` | La ressource de l'application à laquelle appartient le sondage. |
| `opensAt`, `closesAt` | `null` | Le moment où le sondage ouvre et ferme ; tout de suite et jamais, par défaut. |
| `seats` | 1 | Les sièges que pourvoit le dépouillement, de 1 à un de moins que les options ; plus de 1 pour une méthode qui en pourvoit plusieurs, comme `meek_stv` : voir [Vote unique transférable](single-transferable-vote.md). |

La méthode de dépouillement doit savoir compter le type de bulletin du sondage. Les options et les règles ne changent
jamais une fois le sondage créé, pour que chaque bulletin déposé reste valide ; pour les changer, créez un autre
sondage. L'ordre des options est conservé : il ordonne les options à égalité dans les résultats, et départage les
dernières égalités d'un vote alternatif. Une application qui veut un tirage au sort les déclare dans un ordre
aléatoire.

`get()` trouve un sondage par son identifiant, `ofSubject()` les sondages d'une ressource, du plus ancien au plus
récent.

## Les notes d'une échelle

Une question sans pointage demande une note : comment était la soirée, de 1 à 5 ; quelle est la probabilité que vous
nous recommandiez, de 0 à 10. Ses options sont ses notes, qu'écrit `PollDraft::marks($lowest, $highest, $labels)`,
chacune étiquetée par `$labels` sous sa note ou par la note elle-même ; une échelle a de 2 à 100 notes, négatives
comprises. Un bulletin contient une note, ou aucune quand le sondage accepte un bulletin blanc.

```php
use Phil\Poll\BallotKind;

$evening = $polls->create(new PollDraft(
    'How was the evening?',
    PollDraft::marks(1, 5, [1 => 'Poor', 5 => 'Great']),
    BallotKind::Scale,
), $member);

$recommend = $polls->create(new PollDraft(
    'How likely are you to recommend us to a friend?',
    PollDraft::marks(0, 10),
    BallotKind::Scale,
    counting: 'nps',
), $member);

$polls->cast($recommend->id, $member, ['9']);
```

`scale` compte la distribution des notes et leur moyenne ; `nps`, sur une échelle de 0 à 10 seulement, ajoute les
promoteurs, les passifs et les détracteurs et le score : voir [Résultats](results.md).

## Ouvert et fermé

Un sondage est ouvert à partir de son ouverture — de sa création s'il n'en a pas — jusqu'à sa fermeture, ou jusqu'à ce
qu'un gestionnaire le ferme à la main. Les instants sont comparés à la seconde près, en UTC : un sondage qui ferme à
18:00:00 accepte un bulletin à 17:59:59 et en refuse un à 18:00:00.

## Déposer, modifier, retirer

| Méthode | Effet |
|---|---|
| `cast($poll, $voter, $choices)` | Vérifie les choix selon les règles et conserve le bulletin. Renvoie un `CastVote` : l'identifiant du bulletin et, pour un sondage anonyme, son reçu. |
| `change($poll, $voter, $choices)` | Remplace le bulletin du votant dans un sondage qui n'est pas anonyme. |
| `changeWithReceipt($poll, $voter, $receipt, $choices)` | Remplace le bulletin anonyme de ce reçu. |
| `withdraw($poll, $voter)` | Retire le bulletin du votant d'un sondage qui n'est pas anonyme ; il peut en déposer un autre. |
| `hasVoted($poll, $voter)` | Indique si le votant a déposé un bulletin, anonyme ou non. |
| `ballotOf($poll, $voter)` | Le bulletin du votant dans un sondage qui n'est pas anonyme. |
| `ballotOfReceipt($poll, $receipt)` | Le bulletin anonyme de ce reçu. |

Chacune des quatre premières refuse avec `VoteRefused` quand le sondage n'est pas ouvert ou que le `mayVote()` de la
politique refuse le votant, et avec `InvalidBallot`, de `phil/poll`, quand les choix enfreignent les règles : un choix
qui n'est pas une option, un choix donné deux fois, trop peu ou trop de choix. Les choix sont donnés comme les donne un
formulaire ou une requête, un tableau de n'importe quoi ; les vérifications sont celles de la bibliothèque.

Un votant a un bulletin par sondage : un deuxième `cast()` lève `AlreadyVoted`. Les contraintes uniques des tables
arrêtent deux requêtes qui se disputent le dépôt du bulletin d'un même votant.

## Un bulletin et une fermeture en même temps

Un bulletin n'est écrit que tant que le sondage est ouvert, et le stockage le vérifie dans la même écriture. `Polls`
lit le sondage et refuse un bulletin quand il n'est pas ouvert ; puis `PollRepository::raiseRevisionWhileOpen()` garde
le bulletin dans une transaction qui augmente d'abord la révision du sondage par un `UPDATE` dont le `WHERE` contient
l'ouverture, la fermeture et la fermeture à la main, et n'écrit le bulletin que si cette instruction a changé la ligne.
Un bulletin déposé, modifié, modifié par reçu ou retiré pendant qu'un gestionnaire ferme le sondage est donc l'une de
deux choses :

- gardé avant la fermeture : l'`UPDATE` verrouille la ligne du sondage jusqu'à la validation du bulletin, et la
  fermeture l'attend ;
- refusé avec `VoteRefused` (`This poll is not open.`), quand la fermeture a validé la première : rien du bulletin n'est
  écrit, sa participation comprise, et un bulletin modifié garde ses choix précédents.

Il en va de même d'un nouvel horaire et d'une suppression validés entre-temps. Un refus est l'erreur du domaine, jamais
une exception de la base de données. L'instant comparé est celui de l'horloge donnée au bundle, en UTC à la seconde,
comme le sondage garde ses dates.

## Gérer un sondage

`close()`, `reschedule()` et `delete()` consultent le `mayManage()` de la politique. Fermer un sondage déjà terminé ne
change rien ; changer les dates règle les deux dates, `null` pour tout de suite et pour jamais, et refuse une fermeture
au moment de l'ouverture ou avant ; supprimer retire le sondage avec ses bulletins et ses participations.

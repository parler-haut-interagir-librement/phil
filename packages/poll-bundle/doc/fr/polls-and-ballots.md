<!-- phil-dev source-sha256=5c7bc85122f64fa5c1e7cd317314c38152ea04dffe1c4ae85958520915bbc9c6 -->
# Sondages et bulletins

## Créer un sondage

`Polls::create()` accepte un `PollDraft` et l'acteur qui le crée, consulte le `mayCreate()` de la politique, et
conserve le sondage :

| `PollDraft` | Valeur par défaut | Ce qu'il règle |
|---|---|---|
| `question` | — | Un texte UTF-8 de 1 à 500 caractères. |
| `options` | — | De 2 à 100 `PollOption` : un identifiant de 1 à 64 lettres ASCII, chiffres, `_` ou `-`, et un libellé de 1 à 200 caractères. |
| `kind` | `BallotKind::Single` | Une option, plusieurs, ou des options classées. |
| `minChoices`, `maxChoices` | 1, toutes les options | Les bornes d'un bulletin à choix multiple ou classé ; un bulletin à choix unique les ignore. |
| `abstention` | `false` | Indique si un bulletin blanc est une abstention ou s'il est refusé. |
| `counting` | celle du type | Le nom de la méthode de dépouillement : `choices` pour les bulletins à choix unique et multiple, `instant_runoff` pour les bulletins classés, par défaut. |
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

## Gérer un sondage

`close()`, `reschedule()` et `delete()` consultent le `mayManage()` de la politique. Fermer un sondage déjà terminé ne
change rien ; changer les dates règle les deux dates, `null` pour tout de suite et pour jamais, et refuse une fermeture
au moment de l'ouverture ou avant ; supprimer retire le sondage avec ses bulletins et ses participations.

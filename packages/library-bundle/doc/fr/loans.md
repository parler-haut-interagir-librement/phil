<!-- phil-dev source-sha256=00c63babaa0a527afa796281810466b7e9d1a810f2439bbd1bdb05e8a259d3ec -->
# Prêts

`Loans` prête les exemplaires sur `phil/reservation` : chaque exemplaire est un `Bookable` d'une unité, toujours ouvert,
et chaque prêt qui le retient — réservé ou actif — une réservation de celui-ci. Un prêt demandé sur une période qu'un
autre retient est refusé, tout comme un prêt plus long que la politique le permet ou une réservation plus éloignée
qu'elle ne le permet.

## Prêter

```php
$loan = $loans->lend('G-0042', 'card-0007');                 // due at the end of its last day
$loan = $loans->lend('G-0042', 'card-0007', $dueAt);         // due when given
```

Un prêt commence maintenant. Son échéance tombe `loans.days` jours plus tard, à 23:59:59 sur l'horloge de la
bibliothèque, à moins qu'une échéance soit donnée.

## Réserver et enregistrer la sortie

```php
$reserved = $loans->reserve($copyId, 'card-0007', $start, $due);
$loans->checkOut($reserved->id);    // at the desk: active from now, its due date kept
$loans->cancel($reserved->id);
```

Une réservation vise une période à venir, qui commence `loans.reservation_days` jours à l'avance au plus. Sa sortie est
enregistrée au comptoir — dès maintenant si c'est tôt, en gardant son début si c'est tard — une fois son exemplaire
revenu, et avant son échéance ; une réservation passée son échéance est annulée et l'exemplaire est prêté à la place.

## Renouveler

```php
$loans->renew($loan->id);
```

Un renouvellement repousse l'échéance de la durée d'un prêt, comptée à partir de l'échéance, `loans.max_renewals` fois
au plus. Il est refusé quand un autre prêt a réservé l'exemplaire après lui. Ses rappels et ses avis recommencent.

## Retourner et perdre

```php
$loans->giveBack('G-0042');                                          // the condition kept
$loans->giveBack('G-0042', CopyCondition::Incomplete, 'Two tiles missing.');
$loans->lose($loan->id, 'Never came back.');                          // the copy withdrawn
```

L'exemplaire qui est sorti revient par son code à barres, avec l'état où il se trouve et une note quand ils ont changé.
Un exemplaire perdu clôt son prêt et est retiré.

## Le membre

Un membre est nommé par l'identifiant stable de l'application : de 1 à 128 lettres, chiffres, points, tirets bas,
deux-points et traits d'union. Une adresse courriel est refusée, dans le formulaire du comptoir et dans le domaine : une
adresse change et identifie une personne, et la bibliothèque conserve plutôt l'identifiant.

Un membre détient `loans.max_open` prêts au plus, réservés ou sortis. Avec `loans.block_when_overdue`, un membre qui a
un exemplaire passé son échéance n'emprunte plus rien jusqu'à son retour.

## Les refus

Un prêt, une réservation, une sortie ou un renouvellement qui ne peut pas se faire lève `LoanRefused`, dont `reason` est
un code stable et `parameters` les nombres de son message — traduit dans le domaine `phil_library` sous
`refusal.<reason>` :

| Raison | Quand | Paramètres |
|---|---|---|
| `withdrawn` | L'exemplaire est retiré | |
| `taken` | Un autre prêt retient l'exemplaire sur la période | |
| `too_far_ahead` | La réservation commence plus loin dans l'avenir que permis | `minutes` |
| `too_long` | Le prêt dure plus longtemps que permis | `minutes` |
| `overdue` | Le membre a un exemplaire en retard | |
| `limit` | Le membre détient autant de prêts que permis | `max` |
| `renewals` | Le prêt a été renouvelé autant de fois que permis | `max` |

Un exemplaire, un prêt ou un titre qui n'existe pas, ou un exemplaire qui n'est pas sorti, lève `NotFound` ; une valeur
hors de ses bornes ou un changement que l'état du prêt interdit lève `InvalidLibraryData`.

## Deux prêts à la fois

Chaque prêt réclame son exemplaire : la réclamation augmente la version de l'exemplaire, écrite dans le même `flush`
que le prêt. Deux prêts d'un même exemplaire faits en même temps à partir de la même lecture ne peuvent pas être tous
deux conservés : le second `flush` lève l'`OptimisticLockException` de Doctrine et n'écrit rien.

## Les événements

`CopyLent` — un exemplaire prêté ou une réservation dont la sortie est enregistrée — et `CopyReturned` — avec l'état où
l'exemplaire est revenu — sont émis par le répartiteur PSR-14 quand l'application en a un. Ils ne portent que des
identifiants.

## Le lien avec stockroom

Un exemplaire peut être un article de `phil/stockroom` : `Copy::stockroomItem()` le nomme. Avec `phil/stockroom-bundle`
installé, ou l'option `stockroom` réglée sur le service `Stockroom` de l'application, ses prêts déplacent l'article :

| Prêt | Mouvement de stock |
|---|---|
| Prêté, ou sortie enregistrée | `Out`, des contenants de l'article vers le détenteur dont l'identifiant est celui du prêt |
| Retourné | `Return`, de ce détenteur vers le lieu d'origine de l'article |
| Perdu | `Loss`, depuis ce détenteur |

Chaque mouvement renvoie au prêt ; le membre n'est jamais nommé dans le journal de la réserve. Le stock est déplacé
avant que le prêt soit conservé, de sorte qu'une réserve qui refuse — l'article retiré, pas en stock — arrête le prêt.
Sans réserve, un exemplaire lié ne déplace rien.

### Avec phil/stockroom-bundle

Quand `phil/stockroom-bundle` est installé et que `stockroom` ne nomme aucun autre service, le lien utilise le
`Stockroom` du bundle, sur ses tables, et chaque mouvement s'exécute dans une transaction du bundle, la ligne de son
article verrouillée, de sorte que deux prêts d'exemplaires d'un même article n'écrasent jamais les soldes l'un de
l'autre.

| Où sont les tables de la réserve | Ce que fait un prêt |
|---|---|
| Sur la connexion de la bibliothèque, `doctrine.dbal.default_connection` — la valeur par défaut du bundle | Prêter, enregistrer la sortie, retourner et perdre s'exécutent chacun dans une seule transaction de cette connexion : le mouvement, le prêt et l'exemplaire sont conservés ensemble ou pas du tout. |
| Sur une autre connexion | Le mouvement est validé d'abord dans sa propre transaction, puis le prêt est conservé, comme sans le bundle : un prêt qui échoue ensuite laisse le mouvement écrit, et le comptoir remet l'article en place par un retour. |
| En mémoire (`store: memory`) | Le mouvement est fait en mémoire, puis le prêt est conservé. |

Réserver, renouveler et annuler ne déplacent aucun stock et n'exécutent aucune transaction de ce genre.

### Sans le bundle

Avec `stockroom` nommant un `Stockroom` de l'application, le mouvement et le prêt sont deux écritures, le stock en
premier. Une application dont les stockages partagent la base de données de la bibliothèque enveloppe les appels de
`Loans` dans sa propre transaction, ou implémente `Application\Contract\LoanTransaction` et en crée un alias : `Loans`
exécute alors dans celle-ci chaque prêt qui déplace du stock.

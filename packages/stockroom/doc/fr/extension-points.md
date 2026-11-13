<!-- phil-dev source-sha256=1e4441afa9e141d2102f8ca3ec15a4a7a405b97192ec93570497f8bc968229d9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Les magasins

Une application qui conserve son stock implémente les ports de `Phil\Stockroom\Contract` sur sa base de données :

| Port | Contient | Remarques |
|---|---|---|
| `Containers` | Les contenants | `children()` dans l'ordre de leurs identifiants, borné par sa limite |
| `Items` | Les articles | `byCode()` trouve l'article qu'une étiquette nomme ; les codes sont uniques |
| `Balances` | Les pièces de chaque article dans chaque lieu | Une quantité mise à 0 supprime la ligne ; `of()` dans l'ordre des clés des lieux, `at()` dans l'ordre des articles |
| `Journal` | Les mouvements, écrits une seule fois | `ofItem()` et `ofReference()` du plus récent au plus ancien, bornés par leur limite |
| `LoadLists` | Une liste de chargement par événement | `save()` remplace la liste de son événement |

`Place::key()` donne un lieu sous la forme d'une seule chaîne — `container/shelf-b` — qu'un magasin conserve dans une
colonne, et `Place::fromKey()` la relit. La réserve n'exécute aucune transaction elle-même : une application exécute
chaque appel dans une transaction, de sorte qu'un mouvement et ses soldes soient écrits ensemble, et sérialise les
appels qui déplacent le même article — un verrou de ligne sur l'article, par exemple.

Les adaptateurs en mémoire, sous `Phil\Stockroom\Memory`, illustrent chaque contrat ; ils implémentent
`ResetInterface`.

## Lire le stock depuis un autre paquet

`Contract\StockReader` est ce qu'un autre paquet lit sans rien déplacer : `item()`, `available()` — les pièces dans des
contenants, aucune pour un article qui ne peut pas sortir — et `holdings()`. `Stockroom` l'implémente ; un paquet qui ne
fait que lire dépend du port.

## L'horloge

N'importe quelle horloge PSR-20 : `Symfony\Component\Clock\NativeClock` en production, `MockClock` dans les tests. Les
moments sont conservés en UTC, quel que soit son fuseau horaire.

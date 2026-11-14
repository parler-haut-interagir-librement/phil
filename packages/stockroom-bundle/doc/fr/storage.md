<!-- phil-dev source-sha256=2c95ca8343cb921ca708bfa3845afe93cd9bc3d9e659296f8c898f0ede49e239 -->
# Stockage

Les cinq ports de `phil/stockroom` sont conservés dans six tables d'une même connexion DBAL, nommées par le préfixe
(`phil_stockroom_` par défaut). Aucune entité de l'ORM ne les associe : le bundle les lit et les écrit avec DBAL, et son
écouteur de schéma ne fait que les ajouter au schéma que génère l'ORM.

## Les tables

| Table | Port | Une ligne par | Clés et index |
|---|---|---|---|
| `<prefix>container` | `Containers` | contenant : identifiant, nom, parent, code | clé primaire `id` ; index `(parent, id)` pour les enfants d'un contenant |
| `<prefix>item` | `Items` | article : identifiant, code, nom, suivi, origine, état | clé primaire `id` ; `code` unique |
| `<prefix>balance` | `Balances` | article et lieu qui en détient des pièces, avec la quantité | clé primaire `(item, place)` ; index `(place, item)` pour ce qu'un lieu détient |
| `<prefix>movement` | `Journal` | mouvement : position, identifiant, genre, article, quantité, origine, destination, moment, référence, note | clé primaire `position`, auto-incrémentée ; `id` unique ; index `(item, position)` et `(reference, position)` |
| `<prefix>load_list` | `LoadLists` | événement qui a une liste de chargement, même vide | clé primaire `event` |
| `<prefix>load_line` | `LoadLists` | ligne d'une liste de chargement, dans l'ordre de la liste | clé primaire `(event, line)` |

Un lieu — un contenant, un événement ou un détenteur — est conservé sous sa clé, `container/shelf-b`, que
`Place::fromKey()` relit. Un détenteur est l'identifiant que donne l'application, jamais un nom ni une adresse
courriel : la bibliothèque refuse un `@` dans tout identifiant.

Les identifiants, les codes et les clés de lieu sont des colonnes de 140 caractères. Les noms ont 200 caractères au plus,
les notes 1 000 au plus, comme la bibliothèque les borne.

## Octet par octet

Les identifiants se comparent octet par octet, comme la bibliothèque les trie : les colonnes prennent la collation
`ascii_bin` de MySQL et de MariaDB et la collation `C` de PostgreSQL ; SQLite compare déjà les octets. `crate-A` et
`crate-a` sont deux contenants, et une liste vient dans le même ordre sur toutes les bases de données et en mémoire : les
majuscules avant les minuscules, `Shelf-C` avant `shelf-a`.

## Le journal, écrit une fois

L'adaptateur du journal insère, et n'a aucune instruction qui change ou supprime un mouvement. Chaque ligne prend la
valeur suivante de sa position auto-incrémentée, qui ordonne le journal comme il a été écrit : les mouvements d'un
article ou d'une référence sont lus du plus récent au plus ancien par un index qui se termine par la position, bornés par
la limite demandée. Le moment d'un mouvement est conservé en microsecondes depuis l'époque Unix, en UTC, et relu en UTC
à la microseconde près.

## La relecture

Chaque ligne lue est revérifiée par les objets valeur de la bibliothèque : une ligne qui n'est pas un contenant, un
article, un mouvement ou une ligne que garde la bibliothèque lève `InvalidStock`, et une colonne du mauvais type lève
`UnexpectedValueException`, plutôt que d'être devinée. Les adaptateurs ne gardent aucun état entre les appels : ils sont
sûrs dans un worker.

## Transactions et verrous

`phil/stockroom` n'exécute lui-même aucune transaction : un mouvement lit les soldes de deux lieux, puis les écrit avec
sa ligne du journal dans des instructions séparées. `StockroomTransactions::run()` les met dans une seule transaction :

```php
$transactions->run(fn () => $stockroom->transfer('sword', 'shelf-a', 'crate-3', 12), 'sword');
$transactions->run(fn () => $deployments->deploy('larp-7'), ...array_keys($list->totals()));
```

Les identifiants donnés après le travail sont verrouillés d'abord, par `SELECT … FOR UPDATE` sur les lignes des
articles, dans l'ordre de leurs identifiants pour que deux transactions n'attendent jamais les verrous l'une de
l'autre ; 500 au plus par transaction. Un second mouvement du même article attend que le premier soit validé, puis lit
les soldes qu'il a laissés. SQLite, qui n'admet qu'un écrivain à la fois, ne prend aucun verrou de ligne. Ce que le
travail renvoie est renvoyé une fois validé ; ce qu'il lève annule tout et est levé de nouveau.

Quand une transaction est déjà ouverte sur la même connexion, `run()` la rejoint : DBAL l'imbrique dans un point de
sauvegarde, les verrous sont tenus jusqu'à la fin de la transaction extérieure, et le mouvement est conservé avec les
écritures extérieures, ou pas du tout.

Un appel qui écrit hors de `run()` — enregistrer un contenant ou un article, planifier une liste de chargement — est sûr
à lui seul : chacun est une seule ligne, ou une transaction à part pour une liste de chargement.

## Les migrations

Avec l'ORM, `StockroomSchemaListener` ajoute les tables au schéma de la connexion du stockage seulement ; un autre
gestionnaire d'entités sur une autre connexion ne les voit jamais. `doctrine:migrations:diff` écrit la migration qui les
crée, et une version ultérieure du bundle qui change une table est suivie d'un nouveau diff. Sans l'ORM,
`phil:stockroom:schema` montre ou crée les tables manquantes.

<!-- phil-dev source-sha256=52b062920d540d023d935c8c08c1a3740a591aee67ae3190447f115901d4c238 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

La bibliothèque ne déclare aucun service. Ce qu'une application écrit, c'est une source de produits ; ce qu'elle peut
ajouter, c'est le sérialiseur d'un autre format.

| Pour | Faites |
|---|---|
| Publier un catalogue | Implémentez `Contract\ProductSource` et passez-la à `FeedPublisher::publish()` |
| Écrire un autre format | Implémentez `Contract\FeedSerializer` et passez-le à la place de `FeedFormat::serializer()` |
| Borner la longueur d'un flux | Donnez à `FeedPublisher` son maximum d'articles, un million par défaut |

## `ProductSource`

`Phil\ProductFeeds\Contract\ProductSource` — les produits qu'un flux publie.

| Méthode | Contrat |
|---|---|
| `items(): iterable<FeedItem>` | Un article par produit ou variante vendue, chaque identifiant une seule fois |

Une source qui lit son catalogue par pages et produit chaque article l'un après l'autre garde plate la mémoire du
publicateur : un générateur sur un jeu de pages à clé est la forme à écrire. Une source décide de ce qu'elle publie — la
sélection d'un canal, les produits affichés, les variantes plutôt que leur famille — et construit les adresses de
chaque article avec `phil/url`. Un article qu'elle ne peut pas faire, son `InvalidFeedItem` attrapée, vaut mieux omis
et journalisé que de laisser arrêter tout le flux.

## `FeedSerializer`

`Phil\ProductFeeds\Contract\FeedSerializer` — écrit un flux sous forme de texte, en trois parties, pour qu'un flux de
n'importe quelle longueur s'écrive un article à la fois.

| Méthode | Contrat |
|---|---|
| `header(FeedChannel $channel): string` | Ce qui précède le premier article ; peut être vide |
| `item(FeedItem $item): string` | Un article, qui se termine là où le suivant commence |
| `footer(): string` | Ce qui suit le dernier article ; peut être vide |

Un sérialiseur ne garde aucun état entre les appels : `FeedPublisher` lui demande l'en-tête, chaque article, puis le
pied, et écrit chaque partie à mesure qu'elle arrive. `RssSerializer` et `TsvSerializer` sont les deux du paquet.

<!-- phil-dev source-sha256=90bbc6f2500da44b45088c566290f22ffc881090b4627cbb125ca250060e95bb -->
# Référence

Chaque type public du paquet, avec son rôle. Les noms sont relatifs à `Phil\RelatedContent`.

## Les voisins

| Type | Sorte | Rôle |
|---|---|---|
| `RelatedContent` | `final readonly class` | Calcule à la publication et conserve : `related(string $id): ?RelatedSet` lit, `compute(string $id)`, `published(string $id): Recomputation`, `retired(string $id): Recomputation`. |
| `Neighbourhood` | `final readonly class` | Calcule les voisins d'un élément : `compute(Item $item): RelatedSet`, à partir de la source, des règles, et des épingles, relations typées, similarité et comptes de paires facultatifs ; `rules`. |
| `RelevanceRules` | `final readonly class` | Les indices, le seuil, la limite et les bornes ; `forContent()`. |
| `Item` | `final readonly class` | Un identifiant de 1 à 255 octets, des mots-clés et des termes plus larges par indice, une date, une langue ; `withBroader()`, `keywordSet()`, `expandedSet()`, `sharesLocaleWith()`, `assertIdentifier()`. |
| `RelatedSet` | `final readonly class`, `Countable`, `IteratorAggregate` | Les voisins conservés d'un élément et le moment de leur calcul : `byReason()`, `ids()`, `contains()`, `top()`, `toArray()`, `fromArray()`. |
| `Recommendation` | `final readonly class` | Un voisin : `id`, `score` (nul quand une personne l'a choisi), `reasons` de la plus forte à la plus faible ; `hasReason()`, `toArray()`, `fromArray()`. |
| `Reason` | `enum` | `Pinned`, `Accessory`, `Complement`, `Upgrade`, `Indices`, `Similar`, `BoughtTogether`, `ViewedNext` ; `isManual()`. |
| `Recomputation` | `final readonly class` | Les identifiants recalculés, et ceux `deferred` que l'application doit mettre en file d'attente. |
| `Similarity` | `final readonly class` | Un identifiant d'élément et sa similarité, de 0 à 1. |
| `TaxonomyExpander` | `final readonly class` | `expand(Item $item): Item`, avec les termes plus larges des indices nommés. |

## Les indices

| Type | Sorte | Rôle |
|---|---|---|
| `Index\KeywordIndex` | `final readonly class` | Les mots-clés partagés d'un indice, et les termes plus larges partagés à `broaderFactor`. |
| `Index\DateProximity` | `final readonly class` | Les dates rapprochées, à moins de `windowDays`. |

## Produits

| Type | Sorte | Rôle |
|---|---|---|
| `Relation\TypedRelation` | `final readonly class` | `from`, `to` et `type` d'une relation fixée par un marchand. |
| `Relation\RelationType` | `enum` | `Accessory`, `Complement`, `Upgrade` ; `reason()`. |
| `Pairs\PairRecorder` | `final readonly class` | `order(list<string> $productIds): int`, `viewedNext(string $from, string $to)`. |
| `Pairs\Signal` | `enum` | `BoughtTogether`, `ViewedNext` ; `isSymmetric()`, `reason()`. |
| `Pairs\PairCount` | `final readonly class` | L'autre élément d'une paire et son compte. |
| `Pairs\InMemoryPairCounts` | `final class`, `ResetInterface` | Les comptes de paires en mémoire. |

## Stockage

| Type | Sorte | Rôle |
|---|---|---|
| `Store\InMemoryRelatedStore` | `final class`, `ResetInterface` | Les ensembles conservés en mémoire, avec leur index inverse. |

## Les ports

| Type | Sorte | Rôle |
|---|---|---|
| `Contract\ItemSource` | interface | `item(string $id): ?Item`, `candidates(Item $item): iterable<Item>`. |
| `Contract\Index` | interface | `score(Item $item, Item $candidate): float`, le même dans les deux sens. |
| `Contract\Taxonomy` | interface | `broader(string $index, list<string> $terms): list<string>`. |
| `Contract\Pins` | interface | `pinned(string $id): list<string>`. |
| `Contract\TypedRelations` | interface | `from(string $id)`, `to(string $id)` : `list<TypedRelation>`. |
| `Contract\SimilarItems` | interface | `similar(Item $item, int $limit): list<Similarity>`. |
| `Contract\PairCounts` | interface | `add()`, `top()`, `forget()`. |
| `Contract\RelatedStore` | interface | `save()`, `find()`, `remove()`, `pointingAt()`. |

## Pont

| Type | Sorte | Rôle |
|---|---|---|
| `Bridge\Publishing\PublishingListener` | `final readonly class` | `onPagePublished(PagePublished)` et `onPageRetired(PageRetired)` de phil/publishing. |

## Exceptions

| Type | Sorte | Rôle |
|---|---|---|
| `Exception\RelatedContentException` | interface | Toutes les exceptions de la bibliothèque. |
| `Exception\InvalidItem` | `final class` | Un identifiant mal formé, ou trop d'indices ou de mots-clés. |
| `Exception\InvalidRule` | `final class` | Un poids, un seuil, une limite ou une fenêtre hors de ses bornes. |

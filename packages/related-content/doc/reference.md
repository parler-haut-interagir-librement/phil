# Reference

Every public type of the package, with its role. Names are relative to `Phil\RelatedContent`.

## The neighbours

| Type | Kind | Role |
|---|---|---|
| `RelatedContent` | `final readonly class` | Computes at publication and keeps: `related(string $id): ?RelatedSet` reads, `compute(string $id)`, `published(string $id): Recomputation`, `retired(string $id): Recomputation`. |
| `Neighbourhood` | `final readonly class` | Computes the neighbours of one item: `compute(Item $item): RelatedSet`, from the source, the rules, and the optional pins, typed relations, similarity and pair counts; `rules`. |
| `RelevanceRules` | `final readonly class` | The indices, the threshold, the limit and the bounds; `forContent()`. |
| `Item` | `final readonly class` | An identifier of 1 to 255 bytes, keywords and broader terms by index, a date, a language; `withBroader()`, `keywordSet()`, `expandedSet()`, `sharesLocaleWith()`, `assertIdentifier()`. |
| `RelatedSet` | `final readonly class`, `Countable`, `IteratorAggregate` | The kept neighbours of an item and when they were computed: `byReason()`, `ids()`, `contains()`, `top()`, `toArray()`, `fromArray()`. |
| `Recommendation` | `final readonly class` | One neighbour: `id`, `score` (null when a person chose it), `reasons` strongest first; `hasReason()`, `toArray()`, `fromArray()`. |
| `Reason` | `enum` | `Pinned`, `Accessory`, `Complement`, `Upgrade`, `Indices`, `Similar`, `BoughtTogether`, `ViewedNext`; `isManual()`. |
| `Recomputation` | `final readonly class` | The identifiers computed again, and those `deferred` for the application to queue. |
| `Similarity` | `final readonly class` | An item identifier and its similarity, from 0 to 1. |
| `TaxonomyExpander` | `final readonly class` | `expand(Item $item): Item`, with the broader terms of the named indices. |

## The indices

| Type | Kind | Role |
|---|---|---|
| `Index\KeywordIndex` | `final readonly class` | Shared keywords of one index, and shared broader terms at `broaderFactor`. |
| `Index\DateProximity` | `final readonly class` | Close dates, within `windowDays`. |

## Products

| Type | Kind | Role |
|---|---|---|
| `Relation\TypedRelation` | `final readonly class` | `from`, `to` and `type` of a relation a merchant set. |
| `Relation\RelationType` | `enum` | `Accessory`, `Complement`, `Upgrade`; `reason()`. |
| `Pairs\PairRecorder` | `final readonly class` | `order(list<string> $productIds): int`, `viewedNext(string $from, string $to)`. |
| `Pairs\Signal` | `enum` | `BoughtTogether`, `ViewedNext`; `isSymmetric()`, `reason()`. |
| `Pairs\PairCount` | `final readonly class` | The other item of a pair and its count. |
| `Pairs\InMemoryPairCounts` | `final class`, `ResetInterface` | Pair counts in memory. |

## Storage

| Type | Kind | Role |
|---|---|---|
| `Store\InMemoryRelatedStore` | `final class`, `ResetInterface` | The kept sets in memory, with their reverse index. |

## The ports

| Type | Kind | Role |
|---|---|---|
| `Contract\ItemSource` | interface | `item(string $id): ?Item`, `candidates(Item $item): iterable<Item>`. |
| `Contract\Index` | interface | `score(Item $item, Item $candidate): float`, the same both ways. |
| `Contract\Taxonomy` | interface | `broader(string $index, list<string> $terms): list<string>`. |
| `Contract\Pins` | interface | `pinned(string $id): list<string>`. |
| `Contract\TypedRelations` | interface | `from(string $id)`, `to(string $id)`: `list<TypedRelation>`. |
| `Contract\SimilarItems` | interface | `similar(Item $item, int $limit): list<Similarity>`. |
| `Contract\PairCounts` | interface | `add()`, `top()`, `forget()`. |
| `Contract\RelatedStore` | interface | `save()`, `find()`, `remove()`, `pointingAt()`. |

## Bridge

| Type | Kind | Role |
|---|---|---|
| `Bridge\Publishing\PublishingListener` | `final readonly class` | `onPagePublished(PagePublished)` and `onPageRetired(PageRetired)` of phil/publishing. |

## Exceptions

| Type | Kind | Role |
|---|---|---|
| `Exception\RelatedContentException` | interface | Every exception of the library. |
| `Exception\InvalidItem` | `final class` | A malformed identifier, or too many indices or keywords. |
| `Exception\InvalidRule` | `final class` | A weight, a threshold, a limit or a window out of its bounds. |

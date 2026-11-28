# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Give the engine the published items | Implement `Contract\ItemSource` |
| Count sibling terms of a vocabulary | Implement `Contract\Taxonomy`, and pass the items through `TaxonomyExpander` |
| Let editors pin neighbours | Implement `Contract\Pins` |
| Let merchants relate products | Implement `Contract\TypedRelations` |
| Add the similarity of a search engine | Implement `Contract\SimilarItems` |
| Keep the pair counts in a database | Implement `Contract\PairCounts` |
| Keep the neighbours in a database or a cache | Implement `Contract\RelatedStore` |
| Score another clue | Implement `Contract\Index`, and add it to `RelevanceRules::$indices` |
| Change a weight, the threshold or a bound | Pass it to `RelevanceRules` |

Every implementation keeps nothing from one request to the next, so that one instance serves every request of a
worker; the in-memory implementations of the package implement `ResetInterface` for that reason.

## The ports

| Port | Contract |
|---|---|
| `ItemSource::item(string $id): ?Item` | The item as readers may see it now; null when it is not published, retired or unknown. The engine checks that the item returned has the identifier asked. |
| `ItemSource::candidates(Item $item): iterable<Item>` | The published items worth comparing, usually those sharing a keyword, the most recent first; read lazily, at most `maxCandidates`. |
| `Taxonomy::broader(string $index, list<string> $terms): list<string>` | The broader terms of the terms, without the terms themselves. |
| `Pins::pinned(string $id): list<string>` | The identifiers an editor chose, in order. |
| `TypedRelations::from(string $id)`, `to(string $id)` | The relations that start, and end, at the item. |
| `SimilarItems::similar(Item $item, int $limit): list<Similarity>` | Up to `$limit` items with a similarity from 0 to 1. |
| `PairCounts::add()`, `top()`, `forget()` | Anonymous counts by pair; `top()` highest first. |
| `RelatedStore::save()`, `find()`, `remove()`, `pointingAt()` | The kept sets, and the items whose set holds a given item. |

## Writing an index

An index returns a weighted score, 0 when its clue says nothing, and the same score both ways: `score($a, $b)` equals
`score($b, $a)`. `RelatedContent::published()` relies on it to find the items whose lists change.

```php
use Phil\RelatedContent\Contract\Index;
use Phil\RelatedContent\Item;

final readonly class SameVenue implements Index
{
    public function __construct(private float $weight)
    {
    }

    public function score(Item $item, Item $candidate): float
    {
        $venues = array_intersect_key($item->keywordSet('venue'), $candidate->keywordSet('venue'));

        return [] === $venues ? 0.0 : $this->weight;
    }
}
```

An index runs once per candidate, up to `maxCandidates` times per computation: it reads the items it is given and
calls no service.

## Search similarity

phil/search-bundle, when its engine finds similar documents, is adapted behind `SimilarItems`: the adapter asks the
engine for the documents most like the item and returns their identifiers with a score from 0 to 1. Without such an
engine, the port stays unset and the indices alone score.

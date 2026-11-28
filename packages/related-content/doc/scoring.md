# Scoring

`Neighbourhood::compute()` builds the list of an item in three parts, in this order:

1. the **pins** of `Contract\Pins`, in the order the editor set;
2. the **typed relations** of `Contract\TypedRelations`: accessories and upgrades that start at the item, then the
   complements that start or end at it — see [Products](products.md);
3. the **scored candidates**, highest score first, and by identifier on a tie.

A neighbour appears once, under its first part. A pinned or related item that `ItemSource::item()` does not return —
unpublished, retired — or that is in another language is left out. `RelevanceRules::$limit` bounds the whole list.

## The score of a candidate

The score adds:

- the score of each index of `RelevanceRules::$indices`;
- `similarWeight` × the similarity of `Contract\SimilarItems`, capped at 1;
- for each pair count at least `minimumPairCount`, the weight of its signal × its count divided by the highest count
  of the item — the strongest pair scores the full weight.

A candidate whose total is under `threshold` is dropped. Each neighbour keeps its `reasons`, the strongest contribution
first, so that a template can say why it is shown. Scores are rounded to six decimals.

## The indices

| Index | Score |
|---|---|
| `KeywordIndex($name, $weight, $broaderFactor = 0.0)` | `weight` per keyword both items hold in the index `$name`, plus `broaderFactor × weight` per broader term they share besides |
| `DateProximity($weight, $windowDays = 365)` | `weight` for the same instant, falling in a straight line to 0 at `windowDays` apart; 0 when either item has no date |

Every index scores the same both ways: the neighbours of a new item are exactly the items whose lists may now hold it,
which is what `RelatedContent::published()` computes again. A custom index keeps that property — see
[Extension points](extension-points.md).

Keywords are compared trimmed, in lowercase, each once. An item holds at most 32 indices and 200 keywords per index.

### The rules for content

`RelevanceRules::forContent()` reads the keywords `topics`, `authors` and `series`:

| Index | Weight |
|---|---|
| `topics`, broader terms at 0.25 | 100 |
| `authors` | 40 |
| `series` | 200 |
| the date, within 365 days | 20 |

with a threshold of 80: one shared topic is enough, an author alone is not, a series always is.

### Broader terms

`TaxonomyExpander` asks `Contract\Taxonomy` for the broader terms of the keywords of the indices it is given and adds
them to the item. Two items filed under « epic fantasy » and « dark fantasy », whose broader term is « fantasy », then
share that term at `broaderFactor`, without sharing a topic:

```php
use Phil\RelatedContent\TaxonomyExpander;

$item = new TaxonomyExpander($taxonomy, ['topics'])->expand($item);
```

The expander keeps the first 200 broader terms of an index.

## Newer candidates

With `includeNewer: false`, a candidate dated after the item is left out, so that an archive page points back in time
only. A candidate or an item without a date is never left out for it.

## The bounds

| Rule | Default | Bounds |
|---|---|---|
| `threshold` | 80 | 0 or more |
| `limit` | 10 | 1 to 100 |
| `maxCandidates`: the candidates read from `ItemSource::candidates()` | 500 | 1 to 10,000 |
| `similarWeight` | 100 | 0 or more |
| `boughtTogetherWeight` | 100 | 0 or more |
| `viewedNextWeight` | 50 | 0 or more |
| `minimumPairCount` | 3 | 1 to 1,000,000 |
| `maxRecomputes`: the items computed again at once | 100 | 1 to 10,000 |

A computation reads at most `maxCandidates` candidates, `limit` similar items and `limit` pairs of each signal: its cost
does not grow with the size of the site, and the benchmark of [Testing](testing.md) measures it.

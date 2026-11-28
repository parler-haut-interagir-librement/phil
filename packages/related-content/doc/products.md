# Products

A product is an item like any other: its keywords are its categories, its brand, its collection. A shop adds three
kinds of neighbours.

## Typed relations

The merchant sets them, through `Contract\TypedRelations`:

| Type | Shown on | Example |
|---|---|---|
| `RelationType::Accessory` | the source only | sleeves for a card game |
| `RelationType::Complement` | both products | a rulebook and its game master screen |
| `RelationType::Upgrade` | the source only | the deluxe edition of a game |

`TypedRelations::from($id)` returns the relations that start at the product, `to($id)` those that end at it; the engine
reads every type from the first and only the complements from the second. They come after the pins and before the
scored neighbours, with the reason of their type: a template shows `byReason(Reason::Accessory)` as its own block.

## Bought together and viewed next

`PairRecorder` turns what happens into anonymous counts by pair, at the moment it happens:

```php
use Phil\RelatedContent\Pairs\PairRecorder;

$recorder = new PairRecorder($pairCounts);

// When an order is paid: each pair of distinct products counts once, whatever the quantities.
$recorder->order(['product:game', 'product:sleeves', 'product:dice']);

// When a product page follows another in the same visit.
$recorder->viewedNext('product:game', 'product:expansion');
```

A pair bought together is the same pair both ways; a view counts from the first product to the second only. An order
pairs at most `maxItemsPerOrder` distinct products, 50 by default and 200 at most: an order of n products makes
n × (n − 1) / 2 pairs.

The engine reads the strongest pairs of the item, ignores those under `RelevanceRules::$minimumPairCount`, and scales
the others to the strongest one.

### What is never stored

A pair count holds two product identifiers and a number. The library stores, and asks a `Contract\PairCounts` to store,
nothing else: no customer, no order number, no session, no date of purchase, no sequence of views. The pair of the
previous and the current product of a visit is held by the application for the length of a request, and counted.

`minimumPairCount`, 3 by default, keeps a pair out of every list until several orders or visits made it: a pair seen
once would tell a reader what one other person bought.

When a product is retired, `RelatedContent::retired()` calls `PairCounts::forget()`: its pairs leave the counts.

`Pairs\InMemoryPairCounts` holds the counts in memory, for tests and for a process that rebuilds them at start. An
application keeps them in a table of three columns and a count — the two identifiers, the signal — through its own
`PairCounts`, adding to the count in one statement so that two orders at once do not lose one.

# phil/related-content

**Show related pages and products without profiling readers: weighted shared terms, manual pins and accessories,
bought-together counts by pair, never by person.**

phil/related-content is a plain PHP library. It finds the neighbours of a content or a product when it is published,
keeps them, and lets a page read them without computing anything:

- **weighted clues** — the terms of a vocabulary, with their broader terms at a lower weight, the authors, the series,
  close dates — add up to a score; below the threshold nothing is proposed, so an item without a real neighbour shows
  no block rather than a weak one;
- **pins**: the neighbours an editor chose come first, in the order they set;
- **typed relations** for products: accessory, complement (shown on both products), upgrade;
- **bought together** and **viewed next**: anonymous counts by pair of products, ignored under a minimum count, never
  a record of who bought or viewed what;
- **similar text**, from a search engine that knows how, through a port.

It builds no profile of a reader and does no collaborative filtering: what a page shows depends on the item, never on
who reads it.

The neighbours are computed when an item is published, changed or retired. The items that showed a changed item are
computed again, so are its new neighbours, and a retired item leaves every list; past a bound, the rest is returned for
the application to queue, one identifier per message.

## Status

| Part | State |
|---|---|
| `Neighbourhood`, `RelevanceRules`, `KeywordIndex`, `DateProximity`, `TaxonomyExpander` | Implemented and tested |
| `RelatedContent`: compute at publication, keep, recompute the dependants, retire | Implemented and tested |
| Pins, typed relations, pair counts and `PairRecorder` | Implemented and tested |
| `InMemoryRelatedStore` and `InMemoryPairCounts` | Implemented and tested; a database store belongs to the application or a bundle |
| The listener of phil/publishing, in `Bridge\Publishing` | Implemented and tested |
| Adapters over a content, catalogue, taxonomy or search package | Not shipped: the application implements the ports |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and computing the neighbours as pages are published. |
| [Scoring](doc/scoring.md) | The indices, the threshold, the order of the neighbours, and the bounds. |
| [Products](doc/products.md) | Typed relations, bought together and viewed next, and what is never stored. |
| [Extension points](doc/extension-points.md) | The ports, a custom index, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests and its benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/related-content
```

```php
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\RelatedContent;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Store\InMemoryRelatedStore;

// $source implements Contract\ItemSource over the application's published contents.
$related = new RelatedContent($source, new Neighbourhood($source, RelevanceRules::forContent()), new InMemoryRelatedStore());

$related->published('article:42');               // at publication: computed and kept

foreach ($related->related('article:42')?->top(4) ?? [] as $neighbour) {
    echo $neighbour->id, ' ', $neighbour->score, "\n"; // on the page: read, never computed
}
```

```text
article:17 300
article:8 140
```

## Requirements

PHP 8.5 or later, with the extension `mbstring`; symfony/clock 8.2 and symfony/service-contracts 3.6. phil/publishing
is optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network. The ports are implemented in memory under `tests/Support`:

- `MemorySource` holds the published items, gives every one as a candidate, and remembers which items were asked;
- `MemoryPins`, `MemoryRelations`, `MemorySimilar` and `MemoryTaxonomy` answer from what they are given;

and the clock is Symfony's `MockClock`. `RelatedContentTest` runs the whole chain: publication, a change of topic that
moves an item from one list to another, the items deferred past the bound, and a retirement that also forgets the pair
counts. In the monorepo, Infection holds the package at 100 % of covered mutants.

## The benchmark

`tests/Benchmark/NeighbourhoodBench` computes the neighbours of an article against 500 candidates — topics with broader
terms, authors, series, dates — and the pair counts of a busy product, with `RelevanceRules::forContent()`:

```sh
composer bench -- packages/related-content/tests/Benchmark
```

## Testing an application's source

An application tests its own `ItemSource` by computing a set with the in-memory store:

```php
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\RelatedContent;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Store\InMemoryRelatedStore;

$store = new InMemoryRelatedStore();
$related = new RelatedContent($source, new Neighbourhood($source, RelevanceRules::forContent()), $store);
$related->published('article:42');

self::assertSame(['article:17', 'article:8'], $store->find('article:42')?->ids());
```

# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network; the tests of `FeedPublisher` write
in a directory of their own under the system's temporary directory and remove it. They check each field's rule at its
limit and one past it, the exact RSS and TSV written, shipping costs included, a whole RSS file parsed back by DOM, and
that a failing source, a failed rename or a feed too long leaves the previous file and no temporary one. In the
monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/feed-item.php` makes an item of text nobody wrote — its identifier, title, description, brand, colour,
shipping service and custom label cut from the input at each `|` — and checks that it is refused, or written as one RSS
item that parses and gives its title and its shipping service back, and as one TSV row with a cell per field. Its seeds
are in `tests/Fuzz/corpus/feed-item`. In the monorepo, `composer fuzz feed-item` runs it.

## The benchmark

`tests/Benchmark/FeedPublisherBench.php` publishes a thousand and ten thousand items in each format. The time grows
with the items, the memory does not: about 5 MB for both lengths. In the monorepo, `composer bench` runs it.

# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

`tests/Support` holds the doubles every test shares: products in rows with an identity map, as an ORM keeps one —
a commit flushes the products and raises the version of those that changed, a rollback forgets them —, two actions,
an authorizer with rules, recording dispatchers and audit trail, and a run store that fails the saves it is told to.

## The functional flow

`tests/Functional/BulkEditFlowTest` takes the whole path on 1,200 products: a selection by filter with an unticked row,
the token sent back as a string, a preview of a form with one box ticked, the confirmation, the messages consumed by a
worker loop until the run is over, the token refused a second time, then a retry that applies only the failed item.

## The benchmark

`tests/Benchmark/ExecutorBench` measures a run of 5,000 items by filter in chunks of 100, and the store's round trip of
a run of 10,000 items:

```sh
composer bench -- packages/bulk-actions/tests/Benchmark
```

## Testing an application's actions

An application tests its actions with the memory stores and its own doubles of the ports:

```php
$runs = new InMemoryRunStore();
$confirmer = new BulkConfirmer($targets, $authorizer, new InMemorySelectionStore(), $runs, $dispatcher);
$executor = new BulkExecutor($targets, $authorizer, $runs, $transactions);

$run = $confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p1']), ChangeSet::of(['price' => null]), new DateTimeImmutable());

while ($executor->execute($run->id, new DateTimeImmutable())) {
}

self::assertSame(1, $runs->get($run->id)?->report()->count(ItemOutcome::Succeeded));
```

# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network:

- each formula — the sell-through and its rounding, a stock of zero, the dead stock threshold to the second — on its
  own;
- the sales totals with several currencies, returns larger than the sales of a period, and lines without a value;
- the three reports on the memory adapters;
- `PosSoldLines` on the entities of the point of sale and the commerce core, behind memory repositories;
- `LogisticsStockFigures` on the levels and the journal of the logistics module, behind a memory repository;
- the configuration, the tags and the aliases the bundle registers.

The functional suite, under `tests/Functional`, boots a kernel with FrameworkBundle, the reporting bundle and this
bundle, and exports each report as CSV through the reporting bundle's `Reports`.

No adapter of the bundle writes a query: they read the modules through their repositories, so the suites need no
database server. In the monorepo, Infection holds the package at 100 % of covered mutants.

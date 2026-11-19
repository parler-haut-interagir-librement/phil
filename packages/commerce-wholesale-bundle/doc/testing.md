# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `CustomerGroup`, `Membership` | Every rule, the group's price and its rounding, moving between groups |
| `GroupPrices`, `CustomerGroups` | Products priced for the group, other things sold and other groups left alone, a price list coming first through a real `OrderModifier`, joining, moving, leaving, minimum orders |
| `CreditAccount`, `Receivable`, `CreditSales`, `AccountGateway` | Limits, terms, suspension, purchases on account, the buyer's payments, overdue receivables, refunds crediting the receivable |
| `QuantityRule`, `QuantityRules` | Every bound, each breach and the closest quantity allowed, a group's own rule before the rule for every group, one lookup per SKU |
| `AllowedQuantity`, `AllowedQuantityValidator` | Each violation with its code and parameters through Symfony's validator, custom messages, other constraints and values refused |
| `QuantityGuard` | Through a real `OrderModifier`: allowed quantities priced by the resolvers after it, the line's whole quantity checked, changes of quantity and of group refused, the order left as it was |
| Mapping | Every column of the five tables |
| `PhilCommerceWholesaleBundle` | The services with DoctrineBundle, nothing without, the mapping, the guard's place among the price resolvers, the translations, the refusal without the core |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and
the module: `WholesaleFlowTest` stores a group and a membership and reads them back, and stores quantity rules whose
messages it reads in French and in English. Installed on its own, where
FrameworkBundle is a development requirement, the suite is skipped.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-wholesale-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `GroupPrices::priceFor()` over every line of carts of 5, 20
and 100 lines, on SQLite in memory, with the group already loaded and in a fresh unit of work, and
`QuantityGuard::priceFor()` over the same carts, each line's rules read through the validator — about 50 µs a line on
a laptop. From the monorepo root:

```bash
composer bench -- packages/commerce-wholesale-bundle/tests/Benchmark
```

# Testing

| Area | What is covered |
|---|---|
| The values | Every identifier, text, quantity, place, container, item, movement, load line and load list, and each refusal with its message |
| `Stockroom` | Containers nested, moved, bounded in depth, loops caught; items and their codes; each movement, its journal line and its balances; the home taken first; tracked units; conditions; listings and their bounds |
| `Deployments` | Plans, picking, deployments all or nothing and partial, finished later, the containers lines name, the check at the return, write-offs, items without a home, identifiers of digits only |
| `LoadListReader` | Every shape of line, the round trip, each refusal with its line number, the bounds of the text |
| The stores in memory | Each port's order, limits and reset |

```sh
vendor/bin/phpunit packages/stockroom/tests
```

The unit suite runs without a database or a network. In the monorepo, Infection holds the package at 100 % of covered
mutants.

## Fuzzing

The reader of load lists is a fuzz target, `load-list`, seeded from `tests/Fuzz/corpus/load-list`: whatever text it
gets, it returns lines that write back to a text read again as the same lines, or throws `InvalidStock`, and nothing
else.

```sh
composer fuzz -- load-list
```

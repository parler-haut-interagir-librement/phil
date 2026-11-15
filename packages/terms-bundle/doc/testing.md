# Testing

| Area | What is covered |
|---|---|
| The domain | Every identifier and its bounds, the versions and their rules, the acceptances, the policies and their renewal |
| The catalog and the checker | The versions in force, required and latest material at a moment; one read per request; renewal |
| `Terms` | Numbering, material first versions, moments past, formats, events and notices; accepting upcoming and replaced versions |
| `Outstanding` and the reminders | Batches of 500, the latest material version, the members who will not be in order |
| The stores | SQLite in memory: the columns and indexes, the order, the refusal of a taken number, the pages of members, the erasure, the rows that no longer read, the moments written and read back in UTC on a server of another time zone |
| The gate | Pages, APIs, exempt routes and paths, visitors and sub-requests, the problem in two languages |
| The pages | The acceptance page and its form, the targets, the public pages, the changes, the cache headers |
| The administration | The list and its moments in the time zone of the administration, the form, every refusal, the role and the token |
| The kits | Every public page and every page of the administration rendered with the PHIL UX kit, in a subdirectory and at the root, and with the Shadcn kit of the Symfony UX Toolkit: the same headings, labels and header cells, no prop of one kit written as an attribute by the other, no attribute twice; no template writes a word of its own |
| The form, the command, the adapters | The signup checkbox and its checks; `phil:terms:outstanding`, `phil:terms:check-legal-links`; notifications, privacy, network, security, scheduler, EasyAdmin |
| The bundle | The wiring and every bound of the configuration; a kernel on SQLite |

Infection holds the bundle at 100 % of covered mutants killed; the equivalent mutants are named in `phil-dev.php` with
their reasons.

```sh
vendor/bin/phpunit packages/terms-bundle/tests
```

The unit tests render the templates with the Twig of `Tests\Unit\Fixture\TemplatesKernel`, a kernel with the recipes of
the PHIL UX kit, booted once per locale; `KitRecipes` copies the recipes of either kit as `ux:install` would. The
Shadcn kit is read from `symfony/ux-toolkit`, a development requirement; its tests are skipped without it.

## Benchmark

`tests/Benchmark/AcceptanceGateBench.php` runs the gate for a member in order with three required policies of five
versions each, on SQLite in memory with 10,000 acceptances, and for a visitor. On a recent laptop:

| Subject | Time |
|---|---|
| A member in order: the versions read, then one question per policy | about 350 µs |
| A visitor | about 13 µs |

```sh
composer bench -- packages/terms-bundle/tests/Benchmark
```

## Testing an application

The stores are ports: an application's tests may give `Terms` and `AcceptanceChecker` stores in memory and a
`MockClock`, publish versions, and move the clock to test renewal and versions taking effect.

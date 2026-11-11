# Testing

| Area | What is covered |
|---|---|
| `RuleDescription` | Every part in English and French: plurals, places, month days from the end, months, positions, ends |
| `RecurrenceRuleType`, `RecurrenceRuleTransformer` | Each frequency submitted, impossible rules, showing, the rows' targets, names in the language |
| `RecurrenceRuleColumnType`, `RecurrenceExtension`, `PhilRecurrenceBundle` | Storage, the Twig functions, the services |
| The Stimulus controller | Rows shown and fields disabled, under Node's test runner |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle:
`RecurrenceBundleTest` stores a rule, renders the form in `fr_CA` and writes a rule in words. Installed on its own,
where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/recurrence-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/recurrence-bundle
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/recurrence-bundle/tests/Benchmark
```

# Testing

| Area | What is covered |
|---|---|
| `Email`, `ContactCard` | Normalising, refusals, display names, empty cards, phone roles |
| `PersonName`, `NameConventions` | Real names of each culture written and sorted — French, Dutch of the Netherlands and of Belgium, German, Spanish, Portuguese, Italian, English, Chinese, Japanese, Korean, Hungarian, Vietnamese, Icelandic —, orders given, refusals, bounds |
| `VCard`, `SchemaOrg` | Every property, escaping, folding between characters, persons and organisations |
| `ContactCardArray` | The round trip, missing keys, structured names, and each way a stored card is refused |
| `JSContactWriter`, `JSContactReader` | Every property written, the round trip, each notice, each refusal, the bounds |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/contact/tests
```

## Fuzzing

The fuzz target `jscontact-reader` gives the reader texts nobody wrote, from the seeds of
`tests/Fuzz/corpus/jscontact-reader`: whatever it reads, the card it gives writes a JSContact that reads back to the
same JSContact, or the text is refused with one of the library's exceptions. From the monorepo root:

```sh
composer fuzz -- jscontact-reader
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure the hot paths at several sizes: a vCard written for one card
and for a directory, a JSContact card read and written. From the monorepo root:

```sh
composer bench -- packages/contact/tests/Benchmark
```

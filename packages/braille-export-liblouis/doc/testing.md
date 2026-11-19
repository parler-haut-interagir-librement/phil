# Testing

| Area | What is covered |
|---|---|
| `LiblouisTranslator` | The arguments, the input one text per line, the answer as Unicode braille, empty texts left out, control characters, long texts in pieces, the table path, the display table, a missing program and path, a missing table, an exit code and its error output, the bound of the error output, the timeout, the input and output limits, an answer of the wrong count or not braille, the version |
| `LiblouisTables` | The defaults, a region falling back to its language, a copy with one table, every name and tag refused |

The unit tests, under `tests/Unit`, run a shell script that stands in for `lou_translate` and keeps its arguments,
its input and its table path. They need no liblouis and a POSIX shell:

```sh
vendor/bin/phpunit packages/braille-export-liblouis/tests/Unit
```

The suite under `tests/Integration`, in the `liblouis` group, runs the real program found on the `PATH`, and is skipped
without it. It reads the version, translates with each default table, checks that contracted English is shorter than
uncontracted, keeps the order of a batch with a long text, lays out a French document and writes it as BRF, and
reports a missing table:

```sh
vendor/bin/phpunit packages/braille-export-liblouis/tests/Integration
```

In the monorepo, Infection holds the package at 100 % of covered mutants.

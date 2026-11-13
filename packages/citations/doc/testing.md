# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. It checks the model and its
refusals, CSL-JSON read and written back, each LaTeX accent and command, BibTeX names, types, fields, macros,
`crossref` and syntax errors with their lines, RIS records, types, tags and made ids, the limits of each reader, the
duplicates, and the three styles in English and French on one set of references of every shape, as text and as HTML.
In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

`tests/Fuzz/bibtex.php`, `tests/Fuzz/ris.php` and `tests/Fuzz/csl-json.php` give the three readers inputs nobody wrote,
from the seeds of `tests/Fuzz/corpus/<target>`: whatever they read, they return references or throw `InvalidImport` —
`InvalidReference` for CSL-JSON —, nothing else.

```sh
composer fuzz -- bibtex ris csl-json --budget=60     # from the monorepo
```

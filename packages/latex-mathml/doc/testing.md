# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, converts each construct to its expected MathML — macros, colours, links, numbers,
rules and chemistry in `ExtensionsTest` —, checks every refusal with its offset, and the limits of length, depth,
cells and expansion. In the monorepo, Infection holds the package at 100 % of covered
mutants.

## Fuzzing

`tests/Fuzz/latex-mathml.php` gives the converter inputs nobody wrote, from the seeds of
`tests/Fuzz/corpus/latex-mathml`, macros, links and chemistry among them: whatever it reads, it returns MathML or throws `InvalidLatex`, nothing else.

```sh
composer fuzz -- latex-mathml --budget=60     # from the monorepo
```

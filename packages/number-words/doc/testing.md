# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. It spells cardinals, ordinals and
amounts in each of the six languages and in a language without rules, checks each French correction in both
spellings, and each refusal: a locale, a gender, a plural, a verbose form, a currency, an amount and a number out of
range. The words expected are those ICU 76 and later give alike. In the monorepo, Infection holds the package at 100 %
of covered mutants.

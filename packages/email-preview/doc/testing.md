# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: one test class per rule — an email
that follows it, emails that break it, and its limits —, a check of a whole newsletter that passes every rule and of one
that breaks each, and the check of the previews of phil/email-templates-bundle through a `PreviewRenderer` of the test.
In the monorepo, Infection holds the package at 100 % of covered mutants.

## The benchmark

`tests/Benchmark/EmailCheckerBench.php` checks a newsletter of 200 articles, some 300 KB of HTML, with the default
rules: the time must stay in proportion to the size of the email. In the monorepo:

```sh
composer bench -- --filter=EmailCheckerBench
```

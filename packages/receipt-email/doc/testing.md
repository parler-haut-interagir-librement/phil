# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. It covers the value objects and
their refusals, the amounts and taxes read from receipts of `phil/commerce-bundle` — credit notes and taxes included in
the prices too —, the variables of each message, the default templates rendered by `phil/email-templates-bundle` in
English and in French and checked as a site's save checks them, the transactional checks, the PDFs, and the Messenger
messages routed to an asynchronous transport, serialized, and handled by a worker that sends through a Mailer
transport. In the monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/transactional.php` gives `TransactionalCheck::check()` HTML nobody wrote, from the seeds of
`tests/Fuzz/corpus/transactional-html`: it returns the receipt or throws `NotTransactional`, and nothing else escapes.

```sh
composer fuzz transactional-html
```

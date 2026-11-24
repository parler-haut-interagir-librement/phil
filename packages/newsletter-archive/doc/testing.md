# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel or a network, on SQLite in memory: the archive's tables
beside those of `phil/newsletter-bundle`, with its own repositories, a clock that stands still, and a template of the
site that writes the subscriber's addresses. The functional suite, under `tests/Functional`, boots a small application
— FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, SecurityBundle, the newsletter and the archive — and reads
the pages through its browser.

The security of the archive has tests of its own: a revoked link answers 404; no page holds a subscriber's signed
token, address, external identifier, unsubscription or preference link, or tracking pixel, even from a template that
writes them; an unpublished issue leaves the list and its page; erasing a subscriber leaves the web version byte for
byte as it was. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

`tests/Fuzz/link-token.php` gives the reader of links texts nobody wrote: it never throws, never warns, and never opens
an issue with a token it did not issue. `composer fuzz` runs it.

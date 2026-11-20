# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

The unit suite, under `tests/Unit`, runs without a kernel or a network: the DBAL repository and preferences on SQLite
in memory, the others on memory and recording fixtures. The Web Push sender encrypts and signs real messages for a
browser key made by OpenSSL, posted to a mock push service that accepts them, forgets them or refuses them. The functional suite, under `tests/Functional`, boots an
application with FrameworkBundle, DoctrineBundle on SQLite, Twig and its components, sends a notification, renders the
bell and the list, and marks it read through a form checked by the real stateless CSRF protection. With Symfony UX
Turbo, it renders the bell as a permanent element, submits a form as Turbo does and reads the unread count. A second
kernel posts a broadcast to Slack and Discord through Symfony Notifier's own bridges, against a recording HTTP client
that answers as their APIs answer. `FallbackTest` runs the `first_success` mode against fake transports that accept,
refuse or throw on command, with a mock clock, a window of duplicates in an in-memory cache and an in-memory limiter:
the first channel, the next and the last, a channel without contact skipped without being called, a circuit that opens
and lets its probe through, a duplicate within the window, a total failure then its retry with the centre kept once,
the rate limit and the `urgent` type that passes it, a refusal of a transport and another exception that goes up. The Web Push button is rendered with its configuration, and a browser's subscription
kept and forgotten through its endpoints. The Node tests cover the controllers' modules — the updates read from
Mercure and the toasts built from them, what the Web Push controller reads of the browser and sends — and the service
worker, run with a fake `self`: what it shows and what a click opens.

`tests/Integration` runs the contract of the push subscriptions' stores on the DBAL store against MySQL 8 and
PostgreSQL 17; it is skipped unless `PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` is set, as
`phil-dev services test` sets them. `tests/Fuzz/push-subscription.php` reads subscriptions nobody wrote: each is read or
refused with `InvalidPushSubscription`, never another error (`composer fuzz -- push-subscription`).

In the monorepo, the tests run on PHP 8.5 and 8.6, and Infection holds the package at 100 % of covered mutants.

## The benchmarks

`tests/Benchmark/NotificationsBench.php` measures the unread count on tables of ten and a hundred thousand
notifications — it uses the index on the recipient and the state, and stays flat as the table grows —, and the delivery
of one notification to the centre, new or collapsed.

```sh
composer bench -- --filter=NotificationsBench
```

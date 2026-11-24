# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test 'tests/js/**/*.test.mjs'
```

The unit suite, under `tests/Unit`, runs without a kernel, a network or a DNS: the resolver answers from a list, the
clock is a `MockClock`, and the bounce history lives in memory. It covers the rules — the threshold, the window, a
complaint with and without an authenticator, the same report received twice —, the policies, the normalized form, the
field, the commands and the Maildir adapter. The raw messages of `tests/Fixtures` are delivery status notifications,
an abuse report and an automatic reply.

The functional suite, under `tests/Functional`, boots a small application: the field in a form, the route of the known
domains, a webhook event consumed, the command on a delivery status notification and an abuse report, and Messenger
on an in-memory transport that serializes, to check that no address reaches the queue.

The JavaScript tests run the suggestion, the role warning and the controller under Node's test runner, Stimulus
resolved to a stub by `tests/js/register.mjs`.

In the monorepo, Infection holds the package at 100 % of covered mutants.

## Testing an application

`InMemoryBounceHistoryRepository` keeps the history in memory and forgets it on `reset()`; alias
`BounceHistoryRepository` to it in the test environment. Give `dns.resolver` a `StaticResolver` of `phil/email-address`
so that no test asks the DNS.

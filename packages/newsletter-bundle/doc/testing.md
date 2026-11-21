# Testing

## The bundle's tests

The suite `tests/Unit` tests the domain's rules — subscribers' states, campaigns, deliveries, send windows across time
zones —; the subscriptions, the signed links, the sending — queueing once, the checks at sending, windows, pace,
transport failures, confirmations —, the approval of campaigns and its four-eyes rule, the send runs, their counts,
their circuit breaker on each of its thresholds and a worker that loses a race for a run's state, the sandboxed
templates against functions, methods, properties, tags, filters, the range operator and large outputs, the link
rewriting, the click tracker, the bounces from real delivery status notifications, the IMAP client over a socket pair,
the throttle on Symfony's rate limiter, the provider's events, the automations' triggers and actions, the privacy module
and the audience webhooks, all on SQLite in memory, a frozen clock and a mailer that keeps its messages; the bundle's
container under several configurations; and the Flex recipe, whose configuration the bundle accepts.

The suite `tests/Functional` boots an application with FrameworkBundle, TwigBundle, SecurityBundle and DoctrineBundle on
SQLite, and goes through the pages: the subscription form and its CSRF token, the double opt-in through Messenger and
the mailer, the confirmation, a campaign approved by a signed-in user under the four-eyes rule and its completed send
run, its tracked link, the one-click unsubscription, the preference center, and the preview behind its role. It also
runs `lint:container` on that application, without debug so that the command compiles the container itself: every
argument of the bundle's services, the inline ones included, matches the type its constructor declares.

From the monorepo root:

```sh
vendor/bin/phpunit packages/newsletter-bundle/tests
```

The suite `tests/Integration` runs the repositories' contract on a real MySQL 8.4 and PostgreSQL 17, with clicks counted
from two connections and the migration from the tables of 0.2 holding rows; each server's test reads its URL from
`PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/newsletter-bundle/tests/Integration
```

`tests/Fuzz/signed-link.php` feeds the signed links tokens nobody wrote: the verifier refuses each with `LinkRefused`,
never accepts one it did not sign, and never throws anything else. In the monorepo, Infection holds the bundle at 100 %
of covered mutants.

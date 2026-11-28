# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Runs |
|---|---|
| `tests/Unit` | The configuration and the services registered, the repositories on SQLite in memory, `Rosters`, `SwapBoard`, the Messenger handler, the form, the components, the command, the voter, the CSRF guard and the privacy requests. |
| `tests/Functional` | The bundle in a small application — FrameworkBundle with Forms and Messenger, DoctrineBundle with the ORM on SQLite, SecurityBundle, Twig and its components — and its pages posted as a browser without JavaScript posts them. |

`tests/Fixtures/Stage` builds the services on SQLite in memory with a clock the tests move and a dispatcher that keeps
the events; `RacingRosterRepository` and `RacingSwapRepository` lose their first writes, as if another request wrote
between each read and write. In the monorepo, Infection holds the bundle at 100 % of covered mutants.

The functional application keeps its cache and its SQLite file under the system's temporary directory, one per
checkout.

## Testing an application's shifts

`Rosters` and `SwapBoard` are services of the container; a test moves the clock and reads the store:

```php
$rosters = self::getContainer()->get(Rosters::class);
$rosters->plan($shift);
$rosters->signUp($shift->id, 'ada', []);

self::assertSame(PlaceStatus::Confirmed, $rosters->find($shift->id)?->statusOf('ada'));
```

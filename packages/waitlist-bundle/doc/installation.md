# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| Symfony | 8.2 or later | The dependency injection, the console and the identifiers |
| Doctrine ORM | 3.8 or later | The lists and their entries are entities, mapped by attributes |
| `phil/notifications-bundle` | 0.4, optional | To tell participants of their offers; see [Notifications](notifications.md) |

```sh
composer require phil/waitlist-bundle
```

With Symfony Flex, the recipe registers the bundle; its defaults need no configuration file. Without Flex, register
the bundle in `config/bundles.php`:

```php
// config/bundles.php
return [
    // …
    Phil\WaitlistBundle\PhilWaitlistBundle::class => ['all' => true],
];
```

The bundle maps its entities in DoctrineBundle on its own. Create the tables `phil_waitlist` and `phil_waitlist_entry`
with the application's migrations:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The services read the time from the `clock` service, which FrameworkBundle registers with `symfony/clock`, and send
their events through the `event_dispatcher` service when there is one.

## Lapsing the offers on a schedule

An offer lapses when `phil:waitlist:lapse` runs after its deadline. Run it every minute, from Symfony Scheduler:

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;

$schedule->add(RecurringMessage::every('1 minute', new RunCommandMessage('phil:waitlist:lapse')));
```

or from cron:

```text
* * * * * php /path/to/bin/console phil:waitlist:lapse --quiet
```

Each run lapses at most `batch` offers on each of at most `batch` lists; the next run takes the rest.

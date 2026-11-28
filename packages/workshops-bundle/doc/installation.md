# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| Symfony | 8.2 or later | The dependency injection and the translation contracts |
| Doctrine ORM | 3.8 or later | The workshops, sessions and registrations are entities, sorted by PHP's `SortDirection` |
| Doctrine DBAL | 4.6 or later | The moments in its `datetime_utc_immutable` type |
| `phil/reservation` | 0.1 | The span of a session, its `Interval` |
| `symfony/polyfill-php86` | 1.43 or later | PHP's `SortDirection` on PHP 8.5 |
| `phil/waitlist-bundle` | 0.1, optional | A waiting list per session that offers the freed seats in turn |

```sh
composer require phil/workshops-bundle
```

With Symfony Flex, the recipe registers the bundle; its defaults need no configuration file. Without Flex, register
the bundle in `config/bundles.php`:

```php
// config/bundles.php
return [
    // …
    Phil\WorkshopsBundle\PhilWorkshopsBundle::class => ['all' => true],
];
```

The bundle maps its entities in DoctrineBundle on its own. Create the tables `phil_workshops_workshop`,
`phil_workshops_session` and `phil_workshops_registration` with the application's migrations:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

For the waiting lists, install and register phil/waitlist-bundle too; its tables,
`phil_waitlist` and `phil_waitlist_entry`, come with the same migrations:

```sh
composer require phil/waitlist-bundle
```

The services read the time from the `clock` service, which FrameworkBundle registers with `symfony/clock`, and send
their events through the `event_dispatcher` service when there is one.

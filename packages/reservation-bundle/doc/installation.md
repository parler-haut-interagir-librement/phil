# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| Symfony | 8.2 or later | The dependency injection, forms and translation contracts |
| Doctrine ORM | 3.7 or later | The resources and reservations are entities, sorted by PHP's `SortDirection` |
| Doctrine DBAL | 4.5 or later | The moments in its `datetime_utc_immutable` type |
| `symfony/polyfill-php86` | 1.37 or later | PHP's `SortDirection` on PHP 8.5 |
| `symfony/console` | optional | The two commands |
| `symfony/messenger` | optional | The reminders sent as messages |
| `phil/calendar-bundle` | optional | The confirmed reservations in the calendar's views |

```sh
composer require phil/reservation-bundle
```

With Symfony Flex, the recipe registers the bundle; its defaults need no configuration file. Without Flex, register
the bundle in `config/bundles.php`:

```php
// config/bundles.php
return [
    // …
    Phil\ReservationBundle\PhilReservationBundle::class => ['all' => true],
];
```

The bundle maps its entities in DoctrineBundle on its own. Create the tables `phil_reservation_resource` and
`phil_reservation` with the application's migrations:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The services read the time from the `clock` service, which FrameworkBundle registers with `symfony/clock`.

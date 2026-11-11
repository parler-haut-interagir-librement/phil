# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-intl` | any | Days, dates and times written by ICU |
| `ext-mbstring` | any | Day names with a capital in any script |
| `phil/opening-hours` | ^0.1@dev | The hours |
| `psr/clock` | ^1.0 | Now, for Twig; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | The form |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `doctrine/dbal` | optional | The column type `phil_opening_hours` |
| `twig/twig` | optional | The functions and the table template |
| `symfony/clock` | required with Twig | The `clock` service the functions read now from |

```sh
composer require phil/opening-hours-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\OpeningHoursBundle\PhilOpeningHoursBundle::class => ['all' => true],
];
```

## Configuration

```yaml
phil_opening_hours:
    # The time zone new hours are kept in, an IANA identifier; UTC by default.
    default_timezone: America/Toronto
```

Twig's functions read now from the service `clock` — `symfony/clock`'s, with FrameworkBundle. With Twig installed,
that service is required: the bundle never falls back to the system's time, and a container without it does not
compile, naming the missing `clock` service.

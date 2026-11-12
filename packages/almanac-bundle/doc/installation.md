# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | Names in messages cut in characters |
| `phil/almanac` | ^0.1@dev | The dates and their rules |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel` | ^8.2 | The bundle class and its configuration |
| `symfony/form`, `symfony/options-resolver`, `symfony/validator` | ^8.2 | The form of a stored date and its validation |
| `doctrine/orm`, `symfony/uid` | optional | Stored dates, with `database: true` |
| `twig/twig` | optional | `almanac_dates`, `sun_times`, `almanac_eclipses` |

```sh
composer require phil/almanac-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\AlmanacBundle\PhilAlmanacBundle::class => ['all' => true],
];
```

With `database: true` — or any value but `false`, such as an environment variable — the bundle maps its entity for
Doctrine, and the application's migrations create the table ([stored-dates.md](stored-dates.md)); without it, the
entity is not mapped and no migration makes the table.

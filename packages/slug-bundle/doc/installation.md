# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/slug` | ^0.1 | The slugs and the sluggers |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |
| `twig/twig` | ^3.21, optional | The `phil_slug` filter, registered only when Twig is installed |
| `doctrine/dbal` | ^4.5, optional | The [history of former slugs](history.md), registered only when enabled |
| `doctrine/orm` | ^3.7, optional | The history table added to the schema the ORM generates |
| `psr/clock` | ^1.0, optional | The clock of the history; `symfony/clock` provides the `clock` service |

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\SlugBundle\PhilSlugBundle::class => ['all' => true],
];
```

Without any configuration, the bundle registers one slugger named `default`: the transliterating slugger of
`phil/slug`, in no particular locale, at most 255 characters, dropping no word.

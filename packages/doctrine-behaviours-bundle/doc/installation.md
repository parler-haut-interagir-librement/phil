# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/doctrine-behaviours` | ^0.1 | The behaviours |
| `doctrine/doctrine-bundle` | ^3.3 | The entity manager, the event listener tag, the filters, the mapping of the versions |
| `symfony/clock` | ^8.2 | The `clock` service of the instants |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |
| `symfony/security-core` | ^8.2, optional | The blame and owner of the authenticated user, the right to go back to a past version |
| `symfony/workflow` | ^8.2, optional | The publication state, under `publication` |
| `phil/slug-bundle` | ^0.1, optional | The sluggers, under `slug` |
| `symfony/uid` | ^8.2, optional | The UUID v7, under `dated_identifier` |
| `phil/privacy-bundle` | ^0.1, optional | The blame and the owner in access and erasure requests, under `privacy` |

`phil/doctrine-behaviours` brings `phil/locale-dimensions` ^0.1, the locales of the translations, and `doctrine/orm`.

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle::class => ['all' => true],
];
```

The columns of the traits come with the entities' mapping; generate the migration as for any other column.

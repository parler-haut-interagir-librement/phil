# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-intl` | any | Days, months and dates written by ICU; ICU messages |
| `phil/recurrence` | ^0.1@dev | The rules |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | The form |
| `symfony/dependency-injection` | ^8.2 | The bundle class |
| `symfony/translation-contracts` | ^3.6 | Rules in words, through the application's translator |
| `doctrine/dbal`, `twig/twig` | optional | The column type, the Twig functions |

```sh
composer require phil/recurrence-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\RecurrenceBundle\PhilRecurrenceBundle::class => ['all' => true],
];
```

The bundle has no configuration key. With Symfony UX, the Stimulus controller is in `assets/`, declared in its
`package.json`.

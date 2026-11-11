# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-intl` | any | The current locale labels are read in |
| `phil/content-warnings` | ^0.1@dev | The catalogue, warnings, preferences and decisions |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the bundle class |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | The fields |
| `symfony/service-contracts` | ^3.6 | Resetting the in-memory store between requests |
| `doctrine/dbal` | optional | The column type `phil_content_warnings` |
| `symfony/http-foundation` | optional | `SessionWarningPreferences`, the default store |
| `twig/twig` | optional | The functions, the folded display and the form theme |

```sh
composer require phil/content-warnings-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
];
```

Without configuration, the catalogue is the library's base list and warned content is folded. With Symfony UX, the
Stimulus controller and its stylesheet are in `assets/`, declared in its `package.json`.

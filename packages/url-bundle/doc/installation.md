# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/url`, `phil/enums` | ^0.1@dev, ^0.3@dev | The value types and the enum sets |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `TypedUrlType` |
| `symfony/validator` | ^8.2 | `UrlOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class |
| `doctrine/dbal` | optional | The column type `phil_url` |
| `twig/twig` | optional | The Twig filters and function |

```sh
composer require phil/url-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\UrlBundle\PhilUrlBundle::class => ['all' => true],
];
```

The bundle has no configuration key. With DoctrineBundle, it registers the column type `phil_url`; with Twig, its
extension; the messages come in English and French.

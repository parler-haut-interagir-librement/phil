# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/instant-messenger`, `phil/enums` | ^0.1@dev, ^0.3@dev | The value types and the enum sets |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `HandleType` |
| `symfony/validator` | ^8.2 | `HandleOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class |
| `twig/twig` | optional | The Twig filters |

```sh
composer require phil/instant-messenger-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\InstantMessengerBundle\PhilInstantMessengerBundle::class => ['all' => true],
];
```

The bundle has no configuration key.

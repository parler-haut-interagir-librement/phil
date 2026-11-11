# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/phone` | ^0.1@dev | The value types and the numbering plans |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `PhoneType` |
| `symfony/validator` | ^8.2 | `ValidPhoneNumber` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `doctrine/dbal` | optional | The column type `phil_phone` |
| `twig/twig` | optional | The filters |

```sh
composer require phil/phone-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\PhoneBundle\PhilPhoneBundle::class => ['all' => true],
];
```

## Configuration

```yaml
phil_phone:
    # The country a number typed without its country code is read in, by its ISO 3166-1 alpha-2 code in
    # capitals; null, the default, asks for the country code.
    default_region: CA
```

The field, the constraint and the filters read it; each field and constraint may name its own.

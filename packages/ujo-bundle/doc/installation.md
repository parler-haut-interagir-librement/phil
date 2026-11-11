# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| `phil/ujo` | ^0.1 | The model, the checks and the rendering |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The bundle class and its configuration |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `UjoType` |
| `symfony/validator` | ^8.2 | `ValidUjo` |
| `twig/twig` | ^3.21, optional | The `ujo_html` and `ujo_text` filters and functions |

## Installing

```sh
composer require phil/ujo-bundle
```

Without Symfony Flex, enable the bundle in `config/bundles.php`:

```php
return [
    // …
    Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
];
```

The bundle works without configuration; see [configuration.md](configuration.md) for its options. The translations
of its messages, in English and French, are in its `translations/` directory, which the framework finds on its own.

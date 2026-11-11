<!-- phil-dev source-sha256=61661d03f2beba0789e0323c0c918fde8439416a1722403fe77e4105d9279984 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/phone` | ^0.1@dev | Les types valeur et les plans de numérotation |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `PhoneType` |
| `symfony/validator` | ^8.2 | `ValidPhoneNumber` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `doctrine/dbal` | facultatif | Le type de colonne `phil_phone` |
| `twig/twig` | facultatif | Les filtres |

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

Le champ, la contrainte et les filtres la lisent ; chaque champ et chaque contrainte peut nommer la sienne.

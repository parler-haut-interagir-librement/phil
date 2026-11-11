<!-- phil-dev source-sha256=6a0f1ee43c879b30eab3b95f720d344b2ebf5aa760106238a1c7862249bf99cd -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-intl` | toute version | La langue de la requête, `Locale` |
| `phil/address` | ^0.1@dev | Les adresses, les formats et les subdivisions |
| `symfony/form`, `symfony/options-resolver`, `symfony/intl` | ^8.2 | Le formulaire et son champ de pays |
| `symfony/validator` | ^8.2 | `ValidAddress` |
| `symfony/http-foundation` | ^8.2 | Le point de terminaison des formats |
| `symfony/translation-contracts` | ^3.6 | Libellés et messages traduits, lorsqu'un traducteur est présent |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `doctrine/dbal`, `symfony/routing`, `twig/twig` | facultatif | Le type de colonne, la route du point de terminaison, les filtres |

```sh
composer require phil/address-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\AddressBundle\PhilAddressBundle::class => ['all' => true],
];
```

## Configuration

```yaml
phil_address:
    default_country: CA                         # the country of a new address; null asks for it
    format_url: /address-format/__COUNTRY__     # the endpoint the Stimulus controller reads; null leaves it out
```

```yaml
# config/routes/phil_address.yaml
phil_address:
    resource: '@PhilAddressBundle/config/routes.php'
    prefix: /address-format
```

Avec Symfony UX, le contrôleur Stimulus se trouve dans `assets/`, déclaré dans son `package.json`.

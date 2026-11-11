<!-- phil-dev source-sha256=b9f8192fe3eab869e540d8142febd5f14b06cc2bf36ef7e4121d0e2042e9068c -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/url`, `phil/enums` | ^0.1@dev, ^0.3@dev | Les types valeurs et les ensembles d'enums |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `TypedUrlType` |
| `symfony/validator` | ^8.2 | `UrlOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle |
| `doctrine/dbal` | facultatif | Le type de colonne `phil_url` |
| `twig/twig` | facultatif | Les filtres et la fonction Twig |

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

Le bundle n'a aucune clé de configuration. Avec DoctrineBundle, il enregistre le type de colonne `phil_url` ; avec
Twig, son extension ; les messages sont fournis en anglais et en français.

<!-- phil-dev source-sha256=cde4a62e258c29c86b454cd7cc769c4bb6426a1b76764332524f41ce880ee3cd -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/instant-messenger`, `phil/enums` | ^0.1@dev, ^0.3@dev | Les types valeur et les ensembles d'enums |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `HandleType` |
| `symfony/validator` | ^8.2 | `HandleOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle |
| `twig/twig` | facultatif | Les filtres Twig |

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

Le bundle n'a aucune clé de configuration.

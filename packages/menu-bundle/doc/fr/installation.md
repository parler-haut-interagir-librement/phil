<!-- phil-dev source-sha256=e077b46346cb586cef7b73583aa1f18b6b500ce86f62efe550a8bb5ae3e3c846 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | toutes | Les messages coupés en caractères |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle et la configuration automatique |
| `symfony/http-foundation` | ^8.2 | La requête dans laquelle est lu l'élément courant |
| `twig/twig` | facultatif | Les fonctions et les gabarits |

```sh
composer require phil/menu-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\MenuBundle\PhilMenuBundle::class => ['all' => true],
];
```

Le bundle n'a aucune clé de configuration. Les services qui implémentent `MenuBuilder` ou `MenuDecorator` sont trouvés
quand les services de l'application sont configurés automatiquement, comme ceux de Symfony le sont par défaut.

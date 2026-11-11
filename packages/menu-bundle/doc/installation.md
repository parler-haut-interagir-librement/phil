# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | Messages cut in characters |
| `symfony/dependency-injection` | ^8.2 | The bundle class and autoconfiguration |
| `symfony/http-foundation` | ^8.2 | The request the current item is read from |
| `twig/twig` | optional | The functions and the templates |

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

The bundle has no configuration key. Services implementing `MenuBuilder` or `MenuDecorator` are found when the
application's services are autoconfigured, as Symfony's are by default.

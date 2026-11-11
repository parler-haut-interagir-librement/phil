# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | Messages cut in characters |
| `phil/url` | ^0.1.3 | Addresses of the web read as a browser reads them |
| `symfony/config` | ^8.2 | The configuration key |
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

Services implementing `MenuBuilder` or `MenuDecorator` are found when the application's services are autoconfigured,
as Symfony's are by default.

## Configuration

One key, `allow_localhost`, `false` by default, which is also the container parameter `phil_menu.allow_localhost`.
`MenuItem::withUrl()` refuses an address on `localhost` or under `.localhost` unless it is given
`allowLocalhost: true`; a builder that links to a service of the development machine passes it the parameter:

```yaml
when@dev:
    phil_menu:
        allow_localhost: true

when@test:
    phil_menu:
        allow_localhost: true
```

```php
public function __construct(
    #[Autowire(param: 'phil_menu.allow_localhost')] private bool $allowLocalhost,
) {
}

// in build():
MenuItem::of('mail', 'Mail')->withUrl('http://localhost:8025/', $this->allowLocalhost);
```

An IP address, `http://127.0.0.1:8025/`, stays refused.

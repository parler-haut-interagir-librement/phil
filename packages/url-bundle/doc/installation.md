# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/url`, `phil/enums` | ^0.1@dev, ^0.3@dev | The value types and the enum sets |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `TypedUrlType` |
| `symfony/validator` | ^8.2 | `UrlOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class |
| `doctrine/dbal` | optional | The column type `phil_url` |
| `twig/twig` | optional | The Twig filters and function |

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

With DoctrineBundle, the bundle registers the column type `phil_url`; with Twig, its extension; the messages come in
English and French.

## Configuration

One key: `allow_localhost`, `false` by default. `localhost` and the names under `.localhost` name the loopback
address, so the form type, the constraint `UrlOfType`, the column type and the Twig extension refuse them, as
`phil/url` does. An application allows them in development and in its tests only:

```yaml
# config/packages/phil_url.yaml
when@dev:
    phil_url:
        allow_localhost: true

when@test:
    phil_url:
        allow_localhost: true
```

Allowed, `http://localhost:8000/` and `https://app.localhost/` are addresses; `http://127.0.0.1/`, any other single
name and a user name stay refused. The value is also the container parameter `phil_url.allow_localhost`.

# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/url`, `phil/enums` | ^0.1@dev, ^0.3@dev | The value types and the enum sets |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `TypedUrlType` |
| `symfony/validator` | ^8.2 | `UrlOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class |
| `doctrine/dbal`, `doctrine/doctrine-bundle` | optional | The column type `phil_url`, nothing else |
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

The form type, the constraint, the Twig extension and the messages need no database: an application without Doctrine
installs the bundle alone. With `doctrine/dbal` and DoctrineBundle, the bundle also registers the column type
`phil_url`; with Twig, its extension; the messages come in English and French.

## Configuration

| Key | Default | Needs |
|---|---|---|
| `allow_localhost` | `false` | Nothing |
| `column_type` | `null` | `doctrine/dbal` and DoctrineBundle when `true` |

### `allow_localhost`

`allow_localhost`, `false` by default. `localhost` and the names under `.localhost` name the loopback
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

### `column_type`

The only feature that needs `doctrine/dbal` is the column type `phil_url`. The key `column_type` says whether the
bundle registers it with DoctrineBundle:

| Value | The column type |
|---|---|
| `null` (default) | Registered when `doctrine/dbal` and DoctrineBundle are installed, absent otherwise |
| `true` | Required: without `doctrine/dbal`, or without DoctrineBundle, the container does not build and the message names the package to install |
| `false` | Never registered, for an application that maps addresses its own way |

```yaml
# config/packages/phil_url.yaml
phil_url:
    column_type: true # the entities map phil_url columns: fail at build rather than at the first query
```

The key is read before the extensions load, since DoctrineBundle learns of the column type by a prepended
configuration: it takes `true`, `false` or `null`, not an environment variable.

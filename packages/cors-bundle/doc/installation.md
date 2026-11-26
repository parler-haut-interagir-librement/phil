# Installation

```sh
composer require phil/cors-bundle
```

With Symfony Flex, the recipe registers the bundle and writes `config/packages/phil_cors.yaml`, a rule for `^/api/`
whose origins match the regular expression of the environment variable `CORS_ALLOW_ORIGIN`, added to `.env` with
the local origins `http://localhost` and `http://127.0.0.1` on any port. Without Flex, register the
bundle in `config/bundles.php`:

```php
Phil\CorsBundle\PhilCorsBundle::class => ['all' => true],
```

then write the rules of your paths, as [Configuration](configuration.md) explains. Without a rule, the bundle answers
nothing and adds no header.

The package needs PHP 8.5 or later and Symfony 8.2 or later: `symfony/config`, `symfony/dependency-injection`,
`symfony/http-foundation` and `symfony/http-kernel`.

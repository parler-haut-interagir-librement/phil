# PHIL CORS Bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/cors-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/cors-bundle)

**Browsers block cross-origin calls an API does not allow: per-path CORS rules for Symfony, preflight answered early,
no wildcard with credentials.**

phil/cors-bundle answers the Cross-Origin Resource Sharing (CORS) protocol for a Symfony application. Each path pattern
has its own policy: the origins, exact or by anchored pattern, the methods, the request headers, the response headers
the page may read, the credentials, the time a browser keeps a preflight, and the private network access of browsers
that ask for it. The bundle:

- answers the preflights of those paths before the router and the firewall, so no `OPTIONS` route is needed;
- sends `Vary: Origin` on every answer of those paths, so a shared cache never gives one origin's headers to another;
- removes the `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials` and `Access-Control-Expose-Headers`
  headers a controller set, when the policy does not allow the origin;
- refuses, while the container compiles, or on the first request for a value read from the environment, a wildcard
  with credentials, the origin `null`, an origin with a path or a default port, an unanchored origin pattern and a
  pattern that does not compile;
- keeps no state between requests, so it runs as is in a long-running worker.

## Status

| Part | State |
|---|---|
| `PhilCorsBundle` and its configuration `phil_cors` | Implemented and tested |
| Preflights, response headers and `Vary` (`CorsListener`, `CorsHeaders`) | Implemented and tested |
| Policies and path rules (`CorsPolicy`, `PathRule`, `PathPolicyResolver`) | Implemented and tested |
| The Flex recipe, in `recipe/0.1` | Prepared |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Configuration](doc/configuration.md) | Every option, with an example, and what is refused. |
| [How requests are answered](doc/behaviour.md) | Preflights, responses, `Vary`, error pages and the bounds on what a request sends. |
| [Coming from NelmioCorsBundle](doc/nelmio-migration.md) | The options of `nelmio_cors` and their counterparts. |
| [Extension points](doc/extension-points.md) | The services to replace, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\CorsBundle\PhilCorsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_cors.yaml
phil_cors:
    paths:
        '^/api/':
            allow_origins: ['https://app.example.com']
            allow_methods: [GET, POST, PUT, DELETE]
            allow_headers: [Content-Type, Authorization]
            allow_credentials: true
            max_age: 3600
        '^/feed\.json$':
            allow_origins: '*'
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

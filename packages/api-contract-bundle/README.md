# phil/api-contract-bundle

**Problem details, replayed idempotent requests, deprecation notices and OpenAPI verification, wired into a Symfony
application.**

phil/api-contract-bundle brings both sides of an unversioned HTTP API contract into Symfony. As a server, it answers
exceptions as RFC 9457 problem details. Controllers marked `#[Idempotent]` run once per key, through the guard of
`phil/idempotency`, and replay their responses. Controllers marked `#[DeprecatedEndpoint]` announce `Deprecation` and
`Sunset`. As a client, it records the deprecations of the APIs the application calls, logs them once a day, and lists
them with a command that fails in CI while one has a sunset. A second command verifies the application's OpenAPI
document against a level of rules and fails in CI when a finding reaches the chosen severity.

It is for Symfony applications that publish a REST and JSON API without version numbers, or call APIs that announce
their own retirement. The rules themselves live in `phil/api-contract` and `phil/idempotency`, plain PHP libraries.
Use those libraries alone outside Symfony, or when you want the values and decisions without the wiring.

The bundle plugs into the HTTP kernel through listeners on its controller, response and exception events, and wraps
Symfony's HTTP client. Doctrine DBAL, or a cache pool under a Symfony lock, keeps the idempotency keys, Symfony
Security scopes them by user, the Console component runs the two commands, and the Yaml component reads a YAML
document; each of these is optional. The design implements the library's ports with
adapters for each of these components.

## Status

| Part | State |
|---|---|
| `ProblemDetailsListener`, `ProblemException`, `ProblemDetailsResponse` | Implemented and tested |
| `#[Idempotent]`, `IdempotencyListener`, `CallerScopeResolver` | Implemented and tested |
| The idempotency store of `phil/idempotency` on a Doctrine connection or a cache pool, the schema listener | Implemented and tested |
| `#[DeprecatedEndpoint]`, `DeprecationListener` | Implemented and tested |
| `DeprecationObservingHttpClient`, `CacheObservedDeprecations`, `phil:api-contract:deprecations` | Implemented and tested |
| `phil:api-contract:verify`, the verifier service, the application's own rules by tag | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the table. |
| [Configuration](doc/configuration.md) | Every key, the services, replacing a port. |
| [Problems](doc/problems.md) | Exceptions as problem details. |
| [Idempotency](doc/idempotency.md) | `#[Idempotent]`, the answers and their problems, the scope, the store. |
| [Deprecations](doc/deprecations.md) | `#[DeprecatedEndpoint]` as a server; the HTTP client, the record and the command as a client. |
| [Verification](doc/verification.md) | `phil:api-contract:verify`, its options and exit codes, the application's own rules, CI. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

The contract itself — problem members, headers, the levels and rules of verification — is documented with
`phil/api-contract`, and the keys, fingerprints and decision with `phil/idempotency`.

## Quick start

```yaml
phil_api_contract:
    problem_details:
        paths: ['^/api/']
    verification:
        document: '%kernel.project_dir%/openapi.yaml'
```

```php
#[Route('/api/orders', methods: ['POST'])]
#[Idempotent]
public function create(Request $request): Response { /* runs once per key */ }

#[Route('/api/v0/orders', methods: ['GET'])]
#[DeprecatedEndpoint(deprecatedAt: '2026-11-01', sunsetAt: '2027-05-01', link: 'https://example.com/changes')]
public function legacy(): Response { /* … */ }

throw ProblemException::of(409, 'This order was already shipped.');
```

```bash
bin/console phil:api-contract:verify --level=strict
```

## Requirements

PHP 8.5 or later; `phil/api-contract` 0.1; `psr/clock` 1.0; Symfony 8.2's dependency injection, config, HTTP
foundation and HTTP kernel. Doctrine DBAL and ORM with DoctrineBundle, Symfony's HTTP client, a PSR-6 cache, PSR-3,
Symfony Console, Security and Yaml are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

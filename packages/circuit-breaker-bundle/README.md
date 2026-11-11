# phil/circuit-breaker-bundle

**Circuits declared in configuration, their states shared by the workers, and HTTP clients behind their circuits.**

phil/circuit-breaker-bundle brings `phil/circuit-breaker` into a Symfony application. The circuits are declared in the
`phil_circuit_breaker` configuration, one policy per key — an HTTP client, or a destination such as one webhook
endpoint — and checked when the container is built. Their states live in a PSR-6 cache pool shared by the workers, so
that a circuit open in one is open in all, or in a store of the application's. A circuit with `http_client` puts that
client behind it: while the circuit is open, a request is refused before it leaves, and its answers are counted —
a server error or a transport error as a failure, anything else as a success.

It is for any Symfony application that calls third parties it does not control: a payment provider, the partners its
webhooks reach, a geocoding API. An operator forces a circuit open through any service implementing
`CircuitOverride`, tagged by autoconfiguration; `phil/feature-flags-bundle` provides one that reads a flag per
circuit. The profiler shows the state of each circuit at the end of the request.

It is written from scratch, on the components Symfony already ships: Config and DependencyInjection for the
configuration, Cache for the store, HttpClient for the decorated clients, HttpKernel for the profiler.

## Status

| Part | State |
|---|---|
| Circuits from configuration, each checked at build time | Implemented and tested |
| States in a PSR-6 cache pool, or a store of the application's | Implemented and tested |
| An HTTP client behind its circuit, per client or destination | Implemented and tested |
| Overrides tagged and asked together, autoconfigured | Implemented and tested |
| A profiler panel of the circuits | Implemented, its collector tested |
| Console commands to inspect or reset a circuit | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the cache pool. |
| [Configuration](doc/configuration.md) | Every key of `phil_circuit_breaker`, and the errors of a bad circuit. |
| [HTTP clients](doc/http-clients.md) | A client behind its circuit: what is refused, what counts as a failure. |
| [Extension points](doc/extension-points.md) | The store of states, the overrides, the parameter of the circuit keys. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application with circuits. |
| [Reference](doc/reference.md) | Every class, service, tag, parameter and template of the bundle. |

## Quick start

```sh
composer require phil/circuit-breaker-bundle
```

```yaml
# config/packages/phil_circuit_breaker.yaml
phil_circuit_breaker:
    circuits:
        http.payments:
            failure_rate_percent: 50
            minimum_calls: 10
            window_seconds: 60
            open_seconds: 30
            http_client: payments.client
```

```php
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen;

try {
    $response = $paymentsClient->request('POST', '/charges', ['json' => $charge]);
} catch (CircuitOpen) {
    // the circuit is open: the request was not sent
}
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, and `phil/circuit-breaker`. `psr/cache` for the default store,
`symfony/http-client` for the clients behind their circuits, `symfony/web-profiler-bundle` for the panel — each only
when used.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).

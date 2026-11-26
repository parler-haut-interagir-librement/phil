# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\CorsBundle\PhilCorsBundle` | The bundle: its configuration `phil_cors`, its services, and `REQUEST_PRIORITY`, 250, the priority of the preflight listener. |
| `Phil\CorsBundle\Domain\CorsPolicy` | What one set of paths allows: origins, patterns, methods, headers, exposed headers, credentials, `max_age`, private network. Checks its values when built; `fromConfig()` builds one from the options of a path. |
| `Phil\CorsBundle\Domain\PathRule` | A pattern on the path and its policy; `matches()` tests a path, `compile()` checks a pattern and adds its delimiters. |
| `Phil\CorsBundle\Application\PolicyResolver` | The interface that finds the policy of a request, or none. |
| `Phil\CorsBundle\Infrastructure\PathPolicyResolver` | The resolver of the configuration: the first rule that matches the path. |
| `Phil\CorsBundle\Application\CorsHeaders` | `isPreflight()`, `preflight()` builds the answer to a preflight, `apply()` sets the headers of another response; the bounds `REQUEST_HEADERS_MAX_BYTES` and `REQUEST_HEADERS_MAX_COUNT`. |
| `Phil\CorsBundle\Infrastructure\EventListener\CorsListener` | Answers the preflights on `kernel.request` and decorates the responses on `kernel.response`. |
| `Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy` | A value refused when a policy or a rule is built. |

## `CorsPolicy`

| Member | Role |
|---|---|
| `__construct(allowOrigins, allowOriginPatterns, allowMethods, allowHeaders, exposeHeaders, allowCredentials, maxAge, allowPrivateNetwork)` | Builds and checks a policy; the defaults are those of the configuration. |
| `allowsOrigin(string $origin): bool` | Whether the origin is allowed, compared as sent; false beyond `ORIGIN_MAX_BYTES`, 512. |
| `allowOriginValue(string $origin): string` | `*` for any origin, the origin otherwise. |
| `allowsMethod(string $method): bool` | Whether the method is allowed, without case. |
| `allowsHeaders(list<string> $headers): bool` | Whether every lowercase name is allowed. |
| `$methods`, `$allowHeaders`, `$anyHeader`, `$exposeHeaders`, `$allowCredentials`, `$maxAge`, `$allowPrivateNetwork` | The values, read by `CorsHeaders`. |

## Services

| Service | Class | Role |
|---|---|---|
| `phil_cors.path_policy_resolver` | `PathPolicyResolver` | The rules of `phil_cors.paths`. |
| `Phil\CorsBundle\Application\PolicyResolver` | alias | The resolver the listener uses; `phil_cors.path_policy_resolver` by default. |
| `phil_cors.headers` | `CorsHeaders` | The headers of a policy. |
| `phil_cors.listener` | `CorsListener` | The listener of `kernel.request` (priority 250) and `kernel.response` (priority 0). |

With `enabled: false`, none is registered.

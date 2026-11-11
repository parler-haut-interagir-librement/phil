# Configuration

Every key is optional.

```yaml
# config/packages/phil_route_access_check.yaml
phil_route_access_check:
    environments: [dev, test]
    baseline: '%kernel.project_dir%/route-access-baseline.json'
    public_option: public
    attributes:
        - Symfony\Component\Security\Http\Attribute\IsGranted
    ignore:
        route_names: [_profiler, _wdt, _preview_error]
        paths: [/_profiler, /_wdt, /_error, /.well-known/, /healthz]
```

| Key | Default | Meaning |
|---|---|---|
| `environments` | `[dev, test]` | The kernel environments in which the check is registered; in any other, the bundle registers nothing. |
| `baseline` | `null` | The baseline file whose failures are tolerated, and the file `--generate-baseline` writes; `null`, none. |
| `public_option` | `public` | The route option that declares a route public, set to `true` or to the reason. |
| `attributes` | Symfony's `IsGranted` | The attribute classes read as checks on the controllers, with the arguments of `#[IsGranted]`. |
| `ignore.route_names` | `[_profiler, _wdt, _preview_error]` | Route name prefixes left aside. |
| `ignore.paths` | `[/_profiler, /_wdt, /_error]` | Path prefixes left aside. |

A list given replaces its default: to add a prefix, repeat the defaults beside it. A health probe or a
`/.well-known/` path is better declared public with its reason than ignored: the report then says why it is open.

A configured baseline that does not exist makes the command fail with exit code 2; create it with
`--generate-baseline` first.

## What is read from the security configuration

The firewalls and `access_control` entries are read from the services SecurityBundle builds, once every file is
merged: the request matchers of symfony/http-foundation are read back into their path, host, methods, port, client
addresses and attributes, and the parameters they name are resolved. A custom `request_matcher` is kept as its service
id, and the check treats its rule as conditional. An environment variable, whose value is only known at runtime, is
kept as its placeholder `%env(NAME)%`: a path written from one matches no route, and the routes it was written for are
reported uncovered rather than assumed protected.

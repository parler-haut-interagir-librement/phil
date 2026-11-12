# Installation

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The WHATWG URL parser of `ext-uri` |
| `ext-sodium` | any | Encrypts the shared result of a refresh |
| `symfony/security-bundle`, `symfony/security-http`, `symfony/security-core` | `^8.2` | The `oidc_login` authenticator |
| `web-token/jwt-library` | `^4.2` | What `oidc_login` verifies tokens with |
| `symfony/http-client` | `^8.2` | The calls to the provider |
| `symfony/lock` | `^8.2` | The refresh lock and the replay guard |
| `symfony/cache` | `^8.2` | The replay store and the refresh outcomes |
| `symfony/clock` | `^8.2` | Every date of the bundle, and the wait for a refresh |

```bash
composer require phil/identity-client-bundle:^0.1@dev
```

```php
// config/bundles.php
return [
    Phil\IdentityClientBundle\PhilIdentityClientBundle::class => ['all' => true],
];
```

## The firewall

The application declares the firewall, and the bundle fills it in. The firewall has to appear in `security.firewalls`,
so that its place among the others stays the application's choice:

```yaml
security:
    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt)
            security: false
        main:
            lazy: true
```

The block the bundle writes comes first, so anything set in `security.yaml` on the same firewall has the last word.
Options this bundle does not expose, such as `dpop` or a `client_certificate`, are set there. Two protections cannot be
turned off that way: the build stops when the firewall disables PKCE with `S256` or the signature check of the ID token,
and when `security.session_fixation_strategy` is `none`.

## The routes

The callback, the start path and the back-channel logout are routes of the security bundle:

```yaml
oidc_login:
    resource: security.route_loader
    type: service
```

The back-channel logout path receives calls from the provider, not from a browser. It must be reachable without a
session; an `access_control` rule covering it needs `PUBLIC_ACCESS`.

## Shared stores

The lock and cache services must be shared by every server of the application. Configure `framework.lock` with a store
other than the default `flock` as soon as the application runs on more than one host, and give `cache.app` a shared
adapter. The worker mode of FrankenPHP needs nothing more: no service of the bundle keeps state between requests.

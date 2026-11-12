# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | `readonly` classes, typed constants, `clone` with properties, `array_any()`. |
| `ext-ctype`, `ext-mbstring` | any | Numbers of a report; fields cut to a number of characters. |
| `ext-sodium` | any | Nonces and hashes in base64, encoded in constant time. |
| `ext-uri` | any | The target of a redirect, resolved as a browser resolves it. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | The date of a report; the `ViolationReported` event. |
| `symfony/config`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing` | `^8.2` | Configuration, wiring, the listener, the endpoint and its route. |
| `phil/rate-limit` | `^0.1` | The guard that refuses the throttle without its component, or on a pool local to each server in `prod`. |

Optional packages, each enabling one part:

| Package | Enables |
|---|---|
| `twig/twig` | `csp_nonce()` and `csp_meta()`. |
| `symfony/asset-mapper`, `symfony/twig-bridge` | The nonce on the tags of `importmap()`. |
| `symfony/rate-limiter` | The throttle of the report endpoint; the build fails without it unless `reporting.rate_limit` is `false`. |
| `phil/rate-limit-bundle` | The bodies counted by a named limiter of that bundle. |
| `symfony/cache` (or any PSR-6 pool) | The storage of the throttle and the window of deduplication. |
| `symfony/clock` | The `clock` service the reports are dated with. |
| `phil/ip-anonymizer` | The client's address kept truncated with each violation. |
| `doctrine/dbal`, `doctrine/doctrine-bundle` | The table of the violations. |
| `doctrine/orm` | The table added to the schema the ORM generates. |
| `symfony/console` | `phil:csp:purge-reports`. |
| `symfony/security-http` | `Clear-Site-Data` at logout. |
| `psr/log` | The warning of an external redirect. |
| `symfony/web-profiler-bundle` | The profiler panel. |

## Install with Composer

```sh
composer require phil/browser-security-bundle
```

## Register the bundle

```php
// config/bundles.php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle::class => ['all' => true],
];
```

Without any configuration, every response gets the [default policy](policies.md#the-default-policy), enforced, the
[neighbouring headers](headers.md) that break nothing, a refusal of [external redirects](redirects.md) and, when
`kernel.debug` is off, [HTTPS and HSTS](https.md). An application behind a proxy declares it first, or every request
looks plain: see [the proxy](https.md#behind-a-proxy).

## Import the route of the reports

```yaml
# config/routes/phil_browser_security.yaml
phil_browser_security:
    resource: '@PhilBrowserSecurityBundle/config/routes.php'
```

The route `phil_csp_report` answers `POST /_csp/report`, without a session. A prefix moves it; the policies follow,
since the bundle generates the address from the route. Without the route, and without `reporting.uri`, the policies
carry no reporting directive.

The endpoint must stay reachable without authentication: list its path in an `access_control` rule with
`PUBLIC_ACCESS`, or outside any firewall that requires a user.

## Create the table

With `reporting.storage` enabled and the ORM installed, the table `phil_csp_report` joins the schema the ORM generates:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, a migration creates it from `ReportTable::configure()`:

```php
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\ReportTable;

foreach (ReportTable::configure(new Schema())->toSql($connection->getDatabasePlatform()) as $sql) {
    $this->addSql($sql);
}
```

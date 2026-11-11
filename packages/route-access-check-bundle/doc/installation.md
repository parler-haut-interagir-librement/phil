# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/route-access-check` | `^0.1` | The check, its detectors, reports and baseline. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | The configuration and the compiler pass. |
| `symfony/console` | `^8.2` | The command. |
| `symfony/filesystem` | `^8.2` | The baseline file, written atomically. |
| `symfony/http-foundation`, `symfony/routing` | `^8.2` | The request matchers read back, the router's routes. |
| `symfony/security-bundle` | `^8.2` | The firewalls and `access_control` entries; without it, the check reads the attributes and route options only. |

## Installing

```sh
composer require --dev phil/route-access-check-bundle
```

Register the bundle for the environments where the check runs, if Symfony Flex did not:

```php
// config/bundles.php
return [
    // ...
    Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle::class => ['dev' => true, 'test' => true],
];
```

The bundle also guards itself: in an environment outside `phil_route_access_check.environments`, it registers no
service and no parameter. A production container built with the bundle installed carries nothing of it.

## Which environment to check

Run the command in the environment whose security configuration is the one to check — usually `test`, which
continuous integration builds anyway, or `dev`. A `when@prod` block of `security.yaml` is not read from another
environment: keep the access rules out of environment-specific blocks, or add `prod` to `environments` for a CI job
that builds the production container on purpose.

# Extension points

## Detectors of the application

A service implementing `Phil\RouteAccessCheck\Contract\AccessRuleDetector` is tagged
`phil_route_access_check.detector` by autoconfiguration and asked for the rules of every route, beside the three
detectors of the bundle. Write one for a rule the check cannot see: a voter that guards a namespace, a signed-URL
listener, a gateway. The interface, and a detector that also declares its rules ahead of the routes, are documented in
the library's `doc/extension-points.md`.

```php
namespace App\Security;

use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\RuleScope;

final readonly class SignedUrlDetector implements AccessRuleDetector
{
    public function detect(AnalysedRoute $route): iterable
    {
        if (true === $route->route->getOption('signed')) {
            yield new AccessRule('signed_url', AccessEffect::Protected, RuleScope::Route, 'a signed URL, checked by SignedUrlListener');
        }
    }
}
```

A detector is a service of the `dev` and `test` environments: register it under `when@dev` and `when@test`, or in a
directory the production container does not load, so that it costs nothing in production.

## Formats of the application

A service implementing `Phil\RouteAccessCheck\Contract\ReportFormatter` is tagged `phil_route_access_check.formatter`
by autoconfiguration; its `name()`, one of its own, becomes a value of `--format`.

## Services and parameters

| Id | What it is |
|---|---|
| `Phil\RouteAccessCheck\RouteAccessChecker` | The check, over the tagged detectors, the ignore list and the route reader. |
| `Phil\RouteAccessCheck\Security\SecurityMap` | The firewalls and entries, from the parameter `phil_route_access_check.security_map`. |
| `Phil\RouteAccessCheck\ControllerLocator` | The controllers, with the parameter `phil_route_access_check.controller_classes`. |
| `phil_route_access_check.security_map` | The firewalls and `access_control` entries `SecurityMapPass` read, as `SecurityMap::fromArray()` takes them. |
| `phil_route_access_check.controller_classes` | The class of each controller service whose id is not its class. |

Decorating `SecurityMap` gives the check another security map — a configuration the application keeps elsewhere.

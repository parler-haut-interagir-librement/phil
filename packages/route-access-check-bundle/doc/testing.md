# Testing

## The bundle's tests

The suite `tests/Unit` tests the services the configuration declares, the environments outside which nothing is
registered, a detector and a format of the application autoconfigured in a compiled container; `SecurityMapPass` on
the services SecurityBundle leaves before the optimisation passes — firewall map, firewall configurations, access map,
request matchers, expressions, parameters, aliases, custom matchers —; and the command: its formats, its exit codes,
its baseline generated, read and refused, its strict mode, its error output.

The suite `tests/Functional` boots a kernel with FrameworkBundle, SecurityBundle and the bundle, routes controllers
by their attributes, and runs the command: the firewalls and `access_control` entries of a real security
configuration reach the check, parameters resolved, and nothing is registered in `prod`.

From the monorepo root:

```sh
vendor/bin/phpunit packages/route-access-check-bundle/tests
```

## The check as a test of the application

The command is the CI step; the same check also runs as a test, with the service of the `test` environment:

```php
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RouteAccessTest extends KernelTestCase
{
    public function testEveryRouteHasAnAccessRule(): void
    {
        $container = self::getContainer();
        $report = $container->get(RouteAccessChecker::class)->check($container->get('router')->getRouteCollection());

        self::assertSame([], array_map(static fn (RouteVerdict $verdict): string => $verdict->route->name.': '.$verdict->problem(), $report->failures()));
    }
}
```

<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Infrastructure;

use Phil\WellKnownBundle\Infrastructure\Http\WellKnownController;
use Phil\WellKnownBundle\Infrastructure\Routing\WellKnownRouteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;

#[CoversClass(WellKnownRouteLoader::class)]
final class WellKnownRouteLoaderTest extends TestCase
{
    public function testOneRoutePerDocument(): void
    {
        $routes = ['phil_well_known_security_txt' => '/.well-known/security.txt', 'nodeinfo_document' => '/nodeinfo/2.1'];
        $loader = new WellKnownRouteLoader($routes);

        $collection = $loader->load('.', 'phil_well_known');

        self::assertSame(['phil_well_known_security_txt', 'nodeinfo_document'], array_keys($collection->all()));
        $route = $collection->get('nodeinfo_document');
        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/nodeinfo/2.1', $route->getPath());
        self::assertSame(['GET', 'HEAD'], $route->getMethods());
        self::assertSame(['_controller' => WellKnownController::class, '_phil_well_known' => 'nodeinfo_document', '_stateless' => true], $route->getDefaults());
        self::assertEquals([new ContainerParametersResource(['phil_well_known.routes' => $routes])], $collection->getResources());
    }

    public function testNoDocument(): void
    {
        $collection = new WellKnownRouteLoader()->load('.', 'phil_well_known');

        self::assertSame([], $collection->all());
        self::assertEquals([new ContainerParametersResource(['phil_well_known.routes' => []])], $collection->getResources());
    }

    public function testItLoadsItsTypeOnly(): void
    {
        $loader = new WellKnownRouteLoader();

        self::assertTrue($loader->supports('.', 'phil_well_known'));
        self::assertFalse($loader->supports('.', 'php'));
        self::assertFalse($loader->supports('phil_well_known'));
    }
}

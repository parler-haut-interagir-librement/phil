<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit;

use Phil\Checkin\CheckpointId;
use Phil\CheckinBundle\Infrastructure\Http\CheckinRequest;
use Phil\CheckinBundle\Infrastructure\Http\DeskConfig;
use Phil\CheckinBundle\Infrastructure\Http\Routes;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(DeskConfig::class)]
#[CoversClass(Routes::class)]
final class DeskConfigTest extends TestCase
{
    public function testTheConfigurationNamesTheEndpointsAndTheToken(): void
    {
        $config = new DeskConfig(new CheckinRequest(new FixedTokens()), $this->router(), true);

        self::assertSame([
            'checkpoint' => 'hall',
            'offline' => true,
            'urls' => ['scan' => '/_checkin/scan', 'manifest' => '/_checkin/manifest', 'sync' => '/_checkin/sync'],
            'csrfCookie' => 'csrf-token',
        ], $config->for(CheckpointId::of('hall')));
    }

    public function testWithoutACatalogOnlyTheScanIsNamed(): void
    {
        $config = new DeskConfig(new CheckinRequest(new FixedTokens()), $this->router());

        self::assertSame(['scan' => '/_checkin/scan', 'manifest' => null, 'sync' => null], $config->for(CheckpointId::of('hall'))['urls']);
        self::assertFalse($config->for(CheckpointId::of('hall'))['offline']);
    }

    public function testWithoutRoutingNoEndpointIsNamed(): void
    {
        self::assertNull(new DeskConfig(new CheckinRequest(new FixedTokens()))->for(CheckpointId::of('hall'))['urls']);
    }

    private function router(): UrlGenerator
    {
        $routes = new RouteCollection();

        foreach (Routes::NAMES as $path => $name) {
            $routes->add($name, new Route('/_checkin/'.$path));
        }

        return new UrlGenerator($routes, new RequestContext());
    }
}

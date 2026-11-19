<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Application;

use Phil\MapBundle\Application\Tiles\BaseLayerDefinition;
use Phil\MapBundle\Application\Tiles\DirectTiles;
use Phil\MapBundle\Application\Tiles\NoTiles;
use Phil\MapBundle\Application\Tiles\ProxiedTiles;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;

#[CoversClass(ProxiedTiles::class)]
#[CoversClass(DirectTiles::class)]
#[CoversClass(NoTiles::class)]
#[CoversClass(BaseLayerDefinition::class)]
final class TilesTest extends TestCase
{
    public function testProxiedTilesComeFromTheSiteWithoutReferrer(): void
    {
        $routes = new RouteCollection();
        $routes->add(ProxiedTiles::ROUTE, new Route('/_map/tiles/{z}/{x}/{y}'));

        $definition = new ProxiedTiles($this->source(), new UrlGenerator($routes, new RequestContext('/app')))->definition();

        self::assertEquals(new TileLayer('/app/_map/tiles/{z}/{x}/{y}', '© Example', ['maxZoom' => 17, 'referrerPolicy' => 'no-referrer']), $definition->tileLayer);
        self::assertSame([], $definition->extra);
    }

    public function testTheTemplateIsReadFromTheRouteOfTheFirstTile(): void
    {
        $urls = new class implements UrlGeneratorInterface {
            /** @var list<array<array-key, mixed>> */
            public array $asked = [];

            private ?RequestContext $context = null;

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context ??= new RequestContext();
            }

            /**
             * @param array<array-key, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                $this->asked[] = $parameters;

                return '/t/0/0/0';
            }
        };

        new ProxiedTiles($this->source(), $urls)->definition();

        self::assertSame([['z' => 0, 'x' => 0, 'y' => 0]], $urls->asked);
    }

    public function testDirectTilesAreAnonymousUnlessAskedOtherwise(): void
    {
        self::assertEquals(
            new TileLayer('https://t.example.org/{z}/{x}/{y}.png', '© Example', ['maxZoom' => 17, 'referrerPolicy' => 'no-referrer', 'crossOrigin' => 'anonymous']),
            new DirectTiles($this->source())->definition()->tileLayer,
        );
        self::assertEquals(
            new TileLayer('https://t.example.org/{z}/{x}/{y}.png', '© Example', ['maxZoom' => 17, 'referrerPolicy' => 'no-referrer']),
            new DirectTiles($this->source(), false)->definition()->tileLayer,
        );
    }

    public function testNoTiles(): void
    {
        self::assertFalse(new NoTiles()->definition()->tileLayer);
    }

    private function source(): TileSource
    {
        return TileSource::of('https://t.example.org/{z}/{x}/{y}.png', '© Example', 17);
    }
}

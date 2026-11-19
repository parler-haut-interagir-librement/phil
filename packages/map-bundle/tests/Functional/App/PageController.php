<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Functional\App;

use DateTimeImmutable;
use Phil\Geo\Coordinates;
use Phil\MapBundle\Application\Maps;
use Phil\MapBundle\Application\Neighbourhood;
use Phil\MapBundle\Application\Tiles\ProxiedTiles;
use Phil\MapBundle\Application\UnknownBoothAvailability;
use Phil\MapBundle\Domain\ValueObject\Booth;
use Phil\MapBundle\Domain\ValueObject\FloorPlan;
use Phil\MapBundle\Domain\ValueObject\MapPlace;
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use Phil\MapBundle\Infrastructure\Pmtiles\PmtilesLayer;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A page of maps: two places, three members in the neighbourhood, a floor plan of two booths, and, for the browser
 * suite, a PMTiles archive.
 */
final readonly class PageController
{
    public function __construct(
        private Maps $maps,
        private Environment $twig,
        private ProxiedTiles $proxied,
    ) {
    }

    public function __invoke(string $template = 'page.html.twig'): Response
    {
        // An archive whose package the page's import map lacks: the controller falls back on the proxied tiles.
        $archive = new Maps(new PmtilesLayer(SiteAddress::of('/maps/world.pmtiles'), PmtilesLayer::RASTER, '© Archive', 14, 'light', $this->proxied), new Neighbourhood(PrivacyGrid::of()), new UnknownBoothAvailability());

        return new Response($this->twig->render($template, [
            'archive' => $archive->places('Archive', [MapPlace::of('hall', 'Main hall', Coordinates::of(45.5017, -73.5673))], Coordinates::of(45.5, -73.6)),
            'places' => $this->maps->places('Our venues', [
                MapPlace::of('hall', 'Main hall <&>', Coordinates::of(45.5017, -73.5673), 'Doors at 9', SiteAddress::of('/venues/hall')),
                MapPlace::of('park', 'Park', Coordinates::of(45.51, -73.56)),
            ]),
            'neighbourhood' => $this->maps->neighbourhood('Members nearby', [Coordinates::of(45.5017, -73.5673), Coordinates::of(45.5018, -73.5672), Coordinates::of(46.8, -71.2)], Coordinates::of(45.5, -73.6)),
            'plan' => $this->maps->floorPlan('Hall A', FloorPlan::of(SiteAddress::of('/plans/hall.png'), 360, 180, [Booth::of('b1', 'Booth 1', 10, 10, 40, 20), Booth::of('b2', 'Booth 2', 100, 100, 50, 50)]), new DateTimeImmutable('2026-06-01T09:00:00Z'), new DateTimeImmutable('2026-06-01T17:00:00Z')),
        ]));
    }
}

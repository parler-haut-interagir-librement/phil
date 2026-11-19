# Maps

`Phil\MapBundle\Application\Maps` builds every map of the bundle on Symfony UX Map, and gives a `MapView`: the map, the
label that names it to assistive technologies, the title of its list, and the list itself. `phil_map()` renders it.

```php
use Phil\Geo\Coordinates;
use Phil\MapBundle\Application\Maps;
use Phil\MapBundle\Domain\ValueObject\MapPlace;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;

final readonly class VenuesController
{
    public function __construct(private Maps $maps, private Environment $twig) {}

    public function __invoke(): Response
    {
        $view = $this->maps->places('Our venues', [
            MapPlace::of('hall', 'Main hall', Coordinates::of(45.5017, -73.5673), 'Doors at 9', SiteAddress::of('/venues/hall')),
            MapPlace::of('park', 'Park', Coordinates::of(45.5100, -73.5600)),
        ]);

        return new Response($this->twig->render('venues.html.twig', ['map' => $view]));
    }
}
```

## Places

`places(string $label, iterable $places, ?Coordinates $center = null, float $zoom = 13)` puts a marker on each
`MapPlace`: an identifier, a name, `phil/geo`'s coordinates, and optionally a line about it and the page it links to,
a `SiteAddress` — a path of the site or an absolute address read by `phil/url`. Without a center, the map fits its
markers; a map without places is given one. A map shows 5,000 places at most.

The name and the line are text: they are escaped before they reach the marker's info window, which Leaflet writes as
HTML.

## The neighbourhood

`neighbourhood(string $label, iterable $positions, Coordinates $center, float $zoom = 12)` counts the positions it is
given — members, places, groups — by cell of the privacy grid, and draws the cells that hold the minimum as areas with
their count. It reads 100,000 positions at most. No position comes out: see [Privacy](privacy.md).

## Floor plans

`floorPlan(string $label, FloorPlan $plan, ?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null)` draws a
plan and its booths. Over a period, each booth takes the state its resource gives; without one, every booth is unknown.
See [Floor plans](floor-plans.md).

## Rendering

```twig
{{ phil_map(map) }}
{{ phil_map(map, {id: 'venues', heading: 'h3', class: 'isTall'}) }}
```

`phil_map(view, attributes)` renders a `<figure>`: Symfony UX Map's element, with the bundle's controller beside
Leaflet's, the role `region`, the view's label as its `aria-label`, and the list under it as its description; then the
list, a heading and an Item group of the PHIL UX kit, one item per place, area or booth, with its link and its line.

| Attribute | What it does |
|---|---|
| `id` | The figure's identifier; the list's is `<id>-list`. A random one when none is given. |
| `heading` | The element of the list's title: `h2` by default, `h3` to `h6`, or `p`. |
| Any other | Added to the map's element; `class` is added to `phil-map-canvas`. |

The figure has the class `phil-map` and `isPlaces`, `isNeighbourhood` or `isPlan`. Without JavaScript, the map's
element stays hidden and the list says what the map shows.

## The words

The list's titles, the areas' counts and the booths' states come from the `phil_map` translation domain, in English and
French; without a translator, or without a message, they are in English.

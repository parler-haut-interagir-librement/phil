# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Use other tiles | Set `tiles.source` and `tiles.url`, or implement `Application\Contract\BaseLayer` and alias it |
| Give the booths their states from your own bookings | Implement `Application\Contract\BoothAvailability` and name it in `booths.availability` |
| Cut large images with another library | Implement `Application\Contract\ImageTiler` and alias it |
| Change the words | Translate the `phil_map` domain, or override its messages |
| Change the list's markup | Override `@PhilMap/map.html.twig` in `templates/bundles/PhilMapBundle/map.html.twig` |
| Decorate the map further | Listen to the events of Symfony UX Map on the map's element, as the bundle's controller does |
| Change the colours | Override the classes of `phil-map/map.css`, or the tokens of PHIL UX's stylesheet |

## The ports

```php
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Domain\Enum\BoothState;

final readonly class StandBookings implements BoothAvailability
{
    public function __construct(private StandRepository $stands) {}

    public function states(array $booths, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $states = [];

        foreach ($booths as $booth) {
            $states[$booth->id] = $this->stands->isBooked($booth->id, $start, $end) ? BoothState::Taken : BoothState::Free;
        }

        return $states;
    }
}
```

The invariants:

- a `BoothAvailability` gives a state for every booth, under its identifier, books and holds nothing, and calls a booth
  without a resource unknown;
- a `BaseLayer` never sends the visitor's cookies or referrer to a host other than the site's;
- an `ImageTiler` reads an image's size before its pixels, writes `<z>/<x>/<y>.png` under its directory and nothing
  outside it, and gives the tiles of the native zoom unscaled.

# phil/geo

**Coordinates, distances and bounding boxes as PHP values, and geocoding through the provider you pick.**

`phil/geo` holds a point on the Earth as `Coordinates`, in decimal degrees of WGS 84. It measures the great-circle
distance between two points as a `Distance` in meters, kilometers or miles, and computes the `BoundingBox` a database
narrows a search to, poles and antimeridian included. It blurs a point to fewer decimals, to publish a place without
its exact position, and writes coordinates as schema.org `GeoCoordinates`. **It does not** draw maps, project
coordinates or know any place: those belong to the application.

It is for PHP applications that store places and show them by distance: a directory of stores, a calendar of events,
a list of venues. Geocoding comes as two ports. `Geocoder` goes from an address to places and back; `IpLocator` goes
from an IP address to a rough place. Adapters on Symfony's HTTP client cover Nominatim, Photon, Google Maps, Natural
Resources Canada, OpenCage, HERE, Mapbox, Azure Maps, Adresses Québec and ipstack, with a chain that asks each in
turn and a cache. `MaxMindLocator` reads MaxMind DB files, GeoLite2 City among them, on the server itself, so an IP
address looked up never leaves it.

`phil/geo` is a plain PHP library that requires only `ext-filter`; `symfony/http-client` and `symfony/cache` are
needed only by the adapters that use them. In a Symfony application, reach for `phil/geo-bundle`: it maps coordinates
in Doctrine, adds a form type and Twig functions, wires the geocoders from configuration and sorts places by distance
in the browser.

The design rests on immutable value types and on ports and adapters. The ports and `Place` name nothing but this
library, so a domain model can hold them. The MaxMind reader follows the format's specification and reads the file
where each lookup leads, never loading it whole.

## Status

| Part | State |
|---|---|
| `Coordinates`: validation, reading and writing `lat,lng`, equality, blurring | Implemented and tested |
| Great-circle distance (haversine), `Distance` in meters, kilometers and miles | Implemented and tested |
| `BoundingBox` around a point, poles and antimeridian included | Implemented and tested |
| Exceptions under one `GeoException` interface | Implemented and tested |
| Geocoding: `Geocoder`, `IpLocator`, `Place`; Nominatim, Photon, Google Maps, Natural Resources Canada, OpenCage, HERE, Mapbox, Azure Maps, Adresses Québec, ipstack, chain and cache | Implemented and tested |
| MaxMind DB files read on the server; `MaxMindLocator` for GeoLite2 and GeoIP2 City | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Coordinates](doc/coordinates.md) | `Coordinates`: bounds, text, equality, blurring. |
| [Distances](doc/distances.md) | Great-circle distances, `Distance`, bounding boxes, and finding places near a point. |
| [Geocoding](doc/geocoding.md) | The ports, the places, the adapters, the chain and the cache. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/geo
```

```php
use Phil\Geo\Coordinates;

$montreal = Coordinates::of(45.5017, -73.5673);
$quebec = Coordinates::fromString('46.8139,-71.208');

$montreal->distanceTo($quebec)->inKilometers(); // 233.03
$montreal->blurred(2)->toString();              // "45.5,-73.57"
```

## Requirements

PHP 8.5 or later with the filter extension; `symfony/http-client` for the geocoding adapters, `symfony/cache` for
`CachedGeocoder`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

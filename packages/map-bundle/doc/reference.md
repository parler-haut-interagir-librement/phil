# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\MapBundle\PhilMapBundle` | The bundle: its configuration `phil_map`, its services, the paths of AssetMapper and the renderer of Symfony UX Map. |
| `Domain\ValueObject\TileSource` | A tile template, its attribution and its deepest zoom; the address of one tile. |
| `Domain\ValueObject\SiteAddress` | A path of the site or an absolute address read by `phil/url`. |
| `Domain\ValueObject\MapPlace` | A place: identifier, name, coordinates, line, link. |
| `Domain\ValueObject\PrivacyGrid` | The fixed grid of the neighbourhood layer: a cell's size and the minimum. |
| `Domain\ValueObject\GridCell` | One cell, by row and column. |
| `Domain\ValueObject\ApproximateArea` | A cell as it is shown: corners, middle, count. |
| `Domain\ValueObject\FloorPlan` | An image, its size, its booths, its tiles; its scale on the flat reference system. |
| `Domain\ValueObject\Booth` | A rectangle of a plan and the resource it is booked as. |
| `Domain\ValueObject\TilePyramid` | What the tiler wrote: the size, the native zoom, the side and the number of tiles. |
| `Domain\Enum\BoothState` | `free`, `taken`, `closed`, `unknown`. |
| `Domain\Enum\TileMode` | `proxy`, `direct`, `pmtiles`, `none`. |
| `Domain\Exception\MapException` | What every exception of the bundle implements. |
| `Domain\Exception\InvalidMap` | A place, a source, a grid, a booth or a plan refused. |
| `Domain\Exception\TileUnavailable` | A tile the source did not give as the proxy accepts it. |
| `Domain\Exception\TilingFailed` | An image that could not be cut into tiles. |
| `Application\Maps` | Builds the maps of places, of the neighbourhood and of floor plans. |
| `Application\MapView` | A map ready to render: the map, its label, its list. |
| `Application\ListedPlace` | One line of the list: name, line, link. |
| `Application\Neighbourhood` | Counts positions by cell and keeps the cells with the minimum. |
| `Application\MapMessages` | The words of the maps, from `phil_map` or in English. |
| `Application\UnknownBoothAvailability` | Every booth unknown. |
| `Application\Contract\BaseLayer` | The tiles under a geographic map. |
| `Application\Contract\BoothAvailability` | The booths' states over a period. |
| `Application\Contract\ImageTiler` | Cuts an image into the tiles of a plan. |
| `Application\Tiles\BaseLayerDefinition` | The tile layer and the controller's description a base layer gives. |
| `Application\Tiles\ProxiedTiles` | The tiles through the site. |
| `Application\Tiles\DirectTiles` | The tiles from the source, without referrer nor cookies. |
| `Application\Tiles\NoTiles` | No tiles. |
| `Infrastructure\Http\TileProxyController` | The tile proxy. |
| `Infrastructure\Pmtiles\PmtilesLayer` | The PMTiles adapter. |
| `Infrastructure\Gd\GdImageTiler` | The tiler on GD. |
| `Infrastructure\Reservation\ReservationBoothAvailability` | The booths' states from `phil/reservation-bundle`. |
| `Infrastructure\Console\TileImageCommand` | `phil:map:tile-image`. |
| `Infrastructure\Twig\MapExtension` | `phil_map()` and `phil_map_kit()`. |
| `Infrastructure\Twig\KitComponents` | The names under which the recipes of the kit are installed. |
| `Infrastructure\DependencyInjection\MapServices` | Registers the services. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Names the recipes after the Toolkit's subdirectory. |
| `Infrastructure\DependencyInjection\BoothAvailabilityPass` | Puts `phil/reservation-bundle`'s availability in place when it is a service. |

| File | Role |
|---|---|
| `config/routes.php` | The route `phil_map_tile`. |
| `templates/map.html.twig` | A map, its label and its list. |
| `assets/src/map_controller.js` | The controller. |
| `assets/src/lib/plan.js` | The controller's pure decisions. |
| `assets/styles/map.css` | The stylesheet. |
| `translations/phil_map.{en,fr}.php` | The words. |

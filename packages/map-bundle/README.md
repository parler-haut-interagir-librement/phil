# phil/map-bundle

**Maps for Symfony on Symfony UX Map and Leaflet, with tiles no tile server can track the visitor through.**

phil/map-bundle draws three kinds of maps. A map of places shows markers where `phil/geo` says they are — venues,
shops, meeting points — fitted to them or around a center. A neighbourhood map shows where people, places or groups are
without showing any of them: positions are counted by cell of a fixed grid, and only the cells that hold enough of them
appear, as areas with their count. A floor plan shows an image — a hall, a venue, a campus — on Leaflet's flat reference
system with its booths drawn over it, each in the state its `phil/reservation` resource gives for a period: free, taken,
closed or unknown.

By default the browser asks the site for each tile, and the site fetches it from the tile source under its own name and
keeps it: the source sees the site, never the visitor. The tiles can also come straight from the source, without
referrer or cookies, or from a PMTiles archive the site serves itself. Every map comes with a list that says in text what
it shows, drawn with the Item recipe of the PHIL UX kit, which stays when JavaScript does not run.

It is for any Symfony site that shows places: an events site with its venues, a club with its meeting points, a members'
directory with its neighbourhoods, a trade show with its floor plan. The bundle builds on Symfony UX Map and its Leaflet
bridge and decorates them through their events: it adds a Stimulus controller beside Leaflet's, never a map of its own.

## Status

| Part | State |
|---|---|
| `TileSource`, `SiteAddress`, `MapPlace`: the tile template, the addresses, the places from `phil/geo` | Implemented and tested |
| `ProxiedTiles` and the tile proxy: tiles through the site, kept in a cache pool | Implemented and tested |
| `DirectTiles`, `NoTiles`: tiles from the source without referrer nor cookies, or none | Implemented and tested |
| `PmtilesLayer`: a self-hosted PMTiles archive, raster or vector, its package found in the import map | Implemented and tested; the fallback driven in Chrome by the browser suite |
| `PrivacyGrid`, `Neighbourhood`: approximate areas of a fixed grid, the cells below the minimum left out | Implemented and tested |
| `FloorPlan`, `Booth`, `BoothAvailability`: a plan on Leaflet's flat reference system and its booths' states | Implemented and tested |
| `ReservationBoothAvailability`: the states from `phil/reservation-bundle` | Implemented and tested |
| `GdImageTiler` and `phil:map:tile-image`: large images cut into tiles with GD | Implemented and tested |
| `Maps`, `MapView` and `phil_map()`: the maps and their lists, with the Item recipe of the kit | Implemented and tested |
| The `map` controller: plans, archives and the classes of booths and areas | Implemented; pure module tested under Node, the controller driven in Chrome by the browser suite |
| The Flex recipe: the bundle, the proxied tiles and the proxy's route | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the route, the controller, the recipe of the kit. |
| [Configuration](doc/configuration.md) | Every option, and the services registered. |
| [Maps](doc/maps.md) | Places, the neighbourhood and floor plans, built with `Maps` and rendered with `phil_map()`. |
| [Tiles](doc/tiles.md) | The proxy, direct tiles, PMTiles archives, and what each one tells whom. |
| [Privacy](doc/privacy.md) | Why the neighbourhood layer uses a fixed grid and a minimum, and what it never shows. |
| [Floor plans](doc/floor-plans.md) | The flat reference system, booths and their states, large images cut into tiles. |
| [JavaScript](doc/javascript.md) | The controller, the events of Symfony UX Map it listens to, and the stylesheet. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, and the checks in a browser. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/map-bundle symfony/ux-leaflet-map
php bin/console ux:install item --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_map.yaml
phil_map:
    tiles:
        proxy:
            user_agent: 'example.org maps (maps@example.org)'
```

```php
$view = $maps->places('Our venues', [
    MapPlace::of('hall', 'Main hall', Coordinates::of(45.5017, -73.5673), 'Doors at 9', SiteAddress::of('/venues/hall')),
]);
```

```twig
{{ phil_map(view) }}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Symfony UX Map 3.7 and its Leaflet bridge, `phil/geo` and `phil/url`. For the controller,
Stimulus 3 and AssetMapper; for the list, Symfony UX Twig Component and the Item recipe of the PHIL UX kit. For floor
plans cut into tiles, PHP's GD extension; for the booths' states, `phil/reservation-bundle`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

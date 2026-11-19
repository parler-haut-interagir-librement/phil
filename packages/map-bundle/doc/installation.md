# Installation

```sh
composer require phil/map-bundle symfony/ux-leaflet-map
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_map.yaml` and imports the tile proxy's
route under `/_map`. Without it, register the bundle in `config/bundles.php`:

```php
Phil\MapBundle\PhilMapBundle::class => ['all' => true],
```

and import the route:

```yaml
# config/routes/phil_map.yaml
phil_map:
    resource: '@PhilMapBundle/config/routes.php'
    prefix: /_map
```

## Symfony UX Map

The bundle draws with Symfony UX Map and its Leaflet bridge: their recipes register `UXMapBundle` and add Leaflet and
the bridge's controller to the import map. The bundle sets `ux_map.renderer` to `leaflet://default`, unless the
application names another renderer.

## The controller and the stylesheet

With AssetMapper, the bundle's controller is served from the bundle as `@phil/map-bundle/map_controller.js` and its
stylesheet as `phil-map/map.css`, without a build step. Symfony Flex adds the controller to `assets/controllers.json`
from the bundle's `assets/package.json`; add the stylesheet to the page:

```twig
<link rel="stylesheet" href="{{ asset('phil-map/map.css') }}">
```

## The list under each map

The list is drawn with the Item recipe of the PHIL UX kit, installed in the application by the Symfony UX Toolkit;
nothing is rendered from `vendor/`:

```sh
php bin/console ux:install item --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The template calls it through `phil_map_kit('Item')`, under the components subdirectory of the `ux_toolkit`
configuration; in an environment where the Toolkit is not enabled, as production often is, repeat it in
`kit_subdirectory`.

## Optional packages

| Package | For |
|---|---|
| `ext-gd` | `phil:map:tile-image`, which cuts a large image into the tiles of a floor plan |
| `phil/reservation-bundle` | The booths' states, from the resources they are booked as |
| `pmtiles`, `protomaps-leaflet` in the import map | A PMTiles archive, raster or vector: `php bin/console importmap:require pmtiles` |

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Symfony UX Map 3.7 or later.

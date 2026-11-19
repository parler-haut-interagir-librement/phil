# Configuration

The bundle's configuration lives under `phil_map`. Every option has a default:

```yaml
# config/packages/phil_map.yaml
phil_map:
    tiles:
        source: proxy                 # proxy, direct, pmtiles or none
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        max_zoom: 19
        allow_localhost: false
        anonymous: true
        proxy:
            cache: cache.app
            ttl: 604800
            user_agent: 'phil/map-bundle tile proxy'
            timeout: 5.0
            max_bytes: 524288
        pmtiles:
            url: ~                    # /maps/world.pmtiles
            kind: raster              # raster or vector
            attribution: ~
            max_zoom: 15
            flavor: light
    neighbourhood:
        cell_metres: 1000
        minimum: 3
    booths:
        availability: ~
    image_tiles:
        max_pixels: 40000000
        tile_size: 256
    kit_subdirectory: ~
```

| Option | Default | What it does |
|---|---|---|
| `tiles.source` | `proxy` | How the browser gets the tiles: through the site (`proxy`), from the source (`direct`), from a PMTiles archive (`pmtiles`), or none. See [Tiles](tiles.md). |
| `tiles.url` | the OpenStreetMap tiles | The template of the tile source, with `{z}`, `{x}` and `{y}`; read by `phil/url` with each placeholder replaced. |
| `tiles.attribution` | OpenStreetMap's | The attribution the tiles' licence requires, in HTML, shown on the map. |
| `tiles.max_zoom` | `19` | The deepest zoom of the source, from 0 to 22. |
| `tiles.allow_localhost` | `false` | Whether `localhost` is a host of the source and of the archive, for development and tests. |
| `tiles.anonymous` | `true` | Whether direct tiles are anonymous cross-origin requests, without cookies; the source then answers with CORS headers. |
| `tiles.proxy.cache` | `cache.app` | The cache pool that keeps the proxied tiles. |
| `tiles.proxy.ttl` | `604800` | How long a tile is kept, and cached by browsers, in seconds. |
| `tiles.proxy.user_agent` | `phil/map-bundle tile proxy` | The user agent the proxy names itself with; tile policies ask for one that names the site and a contact. |
| `tiles.proxy.timeout` | `5.0` | Seconds the source has to answer. |
| `tiles.proxy.max_bytes` | `524288` | The largest tile accepted, in bytes. |
| `tiles.pmtiles.url` | `null` | The archive, a path of the site or an address; required with the `pmtiles` source. |
| `tiles.pmtiles.kind` | `raster` | `raster` for an archive of images, `vector` for one of vector tiles. |
| `tiles.pmtiles.attribution` | `null` | The attribution of the archive; that of the source when null. |
| `tiles.pmtiles.max_zoom` | `15` | The deepest zoom of the archive. |
| `tiles.pmtiles.flavor` | `light` | The style of a vector archive, a flavor of `protomaps-leaflet`. |
| `neighbourhood.cell_metres` | `1000` | The side of a cell of the privacy grid, from 100 m to 50 km. See [Privacy](privacy.md). |
| `neighbourhood.minimum` | `3` | The fewest positions a cell holds to be shown, from 1 to 1000. |
| `booths.availability` | `null` | The service, a `BoothAvailability`, that gives the booths' states; `phil/reservation-bundle`'s when it is enabled, else none. |
| `image_tiles.max_pixels` | `40000000` | The largest image the tiler decodes; GD holds about five bytes a pixel in memory. |
| `image_tiles.tile_size` | `256` | The side of a tile, from 64 to 1024 pixels. |
| `kit_subdirectory` | `null` | Where the recipes of the PHIL UX kit are installed; the subdirectory of `ux_toolkit` when null. |

A tile template that is no address, or lacks a placeholder, and a PMTiles source without an archive stop the container
from compiling.

## Services

| Service | Role |
|---|---|
| `Phil\MapBundle\Application\Maps` | Builds the maps and their lists; public. |
| `Phil\MapBundle\Application\Neighbourhood` | Counts positions by cell of the grid; public. |
| `Phil\MapBundle\Application\Contract\BaseLayer` | The tiles the configuration chooses. |
| `Phil\MapBundle\Application\Contract\BoothAvailability` | The booths' states. |
| `Phil\MapBundle\Application\Contract\ImageTiler` | The GD tiler; public. |
| `Phil\MapBundle\Infrastructure\Http\TileProxyController` | The tile proxy, behind the route `phil_map_tile`. |

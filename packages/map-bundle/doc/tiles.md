# Tiles

A geographic map draws on tiles, images of 256 pixels fetched by the browser for each zoom and position. Whoever serves
them sees which part of the world the visitor looks at. `tiles.source` decides who that is.

| Source | The browser asks | The tile server sees |
|---|---|---|
| `proxy` (default) | the site | the site, under its user agent; never the visitor's address, cookies or referrer |
| `direct` | the tile server | the visitor's address; no referrer, and no cookies when `anonymous` is on |
| `pmtiles` | the site, for byte ranges of one archive | nobody: there is no tile server |
| `none` | nothing | nobody: the markers and shapes alone |

## The proxy

`ProxiedTiles` points Leaflet at the route `phil_map_tile`, `/_map/tiles/{z}/{x}/{y}` with the recipe's prefix. The
controller fetches the tile from the template of `tiles.url` with Symfony's HTTP client and keeps it in the cache pool
of `tiles.proxy.cache` for `tiles.proxy.ttl` seconds; browsers may keep it as long.

It fetches only what the source's grid holds — at zoom z, x and y from 0 to 2^z − 1, and z up to `tiles.max_zoom`;
anything else is answered 404 without a request. The source has `tiles.proxy.timeout` seconds; its answer is an image
— PNG, JPEG or WebP — of `tiles.proxy.max_bytes` at most, checked while it downloads, without redirects. Anything else
is answered 502 and not kept, so that the next request asks again. The proxy sends its own user agent and `Accept`
header and nothing of the visitor's request.

The default template is OpenStreetMap's standard tiles. Their usage policy asks for a user agent that names the site,
for caching, and forbids heavy traffic: they suit development and light use. A site with real traffic names a tile
provider it has an agreement with, or serves a PMTiles archive.

```yaml
phil_map:
    tiles:
        url: 'https://tiles.example.org/{z}/{x}/{y}.png'
        attribution: '© Example tiles, © OpenStreetMap contributors'
        proxy:
            user_agent: 'example.org maps (maps@example.org)'
            cache: cache.tiles
```

## Direct tiles

`DirectTiles` gives Leaflet the template itself, with `referrerPolicy: no-referrer` and, unless `tiles.anonymous` is
off, `crossOrigin: anonymous`: the browser sends no cookies, and the tile server must answer with CORS headers.

## PMTiles archives

A PMTiles archive holds every tile of a region in one file, read by range requests: the site serves it as any static
file, and no tile server exists. `PmtilesLayer` is the bundle's adapter for it:

```yaml
phil_map:
    tiles:
        source: pmtiles
        pmtiles:
            url: /maps/region.pmtiles
            kind: vector        # raster: images; vector: vector tiles drawn in the browser
            attribution: '© OpenStreetMap contributors'
            max_zoom: 15
            flavor: light
```

The archive is read in the browser by the `pmtiles` package for a raster archive and by `protomaps-leaflet` for a vector
one; both are optional, added to the import map by the application:

```sh
php bin/console importmap:require pmtiles
php bin/console importmap:require protomaps-leaflet
```

When AssetMapper's import map lacks the package, `PmtilesLayer` says so in the log and the map uses the proxied tiles.
In the browser, the controller takes the proxied tiles away before the map is made and gives them back only if the
package fails to load.

## A base layer of the application's

Another source is a `BaseLayer`; alias it:

```yaml
services:
    Phil\MapBundle\Application\Contract\BaseLayer: '@App\Maps\SatelliteTiles'
```

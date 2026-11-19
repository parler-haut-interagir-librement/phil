# Testing

## The bundle's tests

`tests/Unit` covers the tile source and its grid, the addresses and the places, the privacy grid — cells, their size,
their middle, the edges of the world, the poles —, the neighbourhood — the minimum, the order, the limit, no position
out —, floor plans and booths, their scale and their limits, the maps built with Symfony UX Map — markers, escaped info
windows, areas, booths and their states, the period —, the words in English and French, the three base layers and the
PMTiles adapter with and without its package in the import map, the tile proxy — the request it sends, the cache, every
failure answered 502 and not kept, the limit while downloading —, the GD tiler on every image type, its limits and its
transparent edges, the states from `phil/reservation-bundle`, the Flex recipe, the compiler passes, and the bundle's
configuration, services and prepended settings.

`tests/Functional` boots a kernel with FrameworkBundle, Twig, its components, Stimulus, Symfony UX Map and the bundle,
the Item recipe of the PHIL UX kit copied where `ux:install` puts it: it renders a page of three maps and checks their
markup and their lists, answers a tile through the proxy's route, and runs the tiler's command.

`tests/js` runs the pure module under Node's test runner: the class of each shape, the plan and the archive read from
the map's description.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/map-bundle/tests
node --test tests/js     # from packages/map-bundle
```

Every line of `src/` is held by the mutation bar of the monorepo: each mutant is killed.

## In a browser

`tests/Browser` drives Chrome with Panther on the functional suite's application, served by PHP's built-in server:
the four maps drawn by Leaflet with the bundle's controller, their tiles asked of the site's proxy without referrer, the
areas and the booths in the classes of their states, the floor plan on the flat reference system, a PMTiles archive
whose package is missing falling back on the proxied tiles, and the lists. It needs Leaflet and Stimulus from npm, which
`npm ci --ignore-scripts` in `tests/Browser` installs, and the chromedriver of the system; without Leaflet it is
skipped. It runs apart, like Infection:

```sh
composer test:browser
```

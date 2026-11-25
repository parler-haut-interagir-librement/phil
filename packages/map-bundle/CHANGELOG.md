# Changelog

All notable changes to `phil/map-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Changed

- Requires the latest minor of each dependency (`twig/twig` 3.31, `symfony/cache-contracts` 3.8,
  `symfony/http-client-contracts` 3.8, `symfony/translation-contracts` 3.8, …), and of each development requirement
  (`symfony/ux-twig-component` 3.8, `symfony/stimulus-bundle` 3.8).

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.1.0-alpha - 2026-11-20

### Added

- Maps on Symfony UX Map and its Leaflet bridge, decorated through their events by a Stimulus controller served by
  AssetMapper, with a stylesheet on the tokens of PHIL UX.
- `TileSource`: the tile template read by `phil/url`, its attribution and its grid.
- The tile proxy, the default: tiles fetched by the site under its own user agent, kept in a cache pool, bounded in size
  and time, never forwarding anything of the visitor; failures answered 502 and not kept.
- Direct tiles without referrer and, by default, without cookies; no tiles at all.
- `PmtilesLayer`: a self-hosted PMTiles archive, raster or vector, read by `pmtiles` or `protomaps-leaflet` from the
  import map, falling back on the proxied tiles when the package is missing.
- `MapPlace` and `SiteAddress`: places from `phil/geo` as markers, their texts escaped.
- `PrivacyGrid` and `Neighbourhood`: positions counted by cell of a fixed grid, only the cells that hold the minimum
  shown, never a position.
- `FloorPlan` and `Booth`: plans on Leaflet's flat reference system, as an image or as tiles, their booths in the classes
  of their states.
- `BoothAvailability`, with `ReservationBoothAvailability` on `phil/reservation-bundle`, put in place when it is a
  service, and `UnknownBoothAvailability`.
- `ImageTiler`, `GdImageTiler` and `phil:map:tile-image`: large images cut into tiles with GD, their size checked before
  their pixels.
- `Maps`, `MapView` and `phil_map()`: every map with the list of what it shows, drawn with the Item recipe of the PHIL UX
  kit, visible without JavaScript; the words in English and French.
- The Flex recipe in `recipe/0.1`: the bundle, the proxied tiles and the proxy's route.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

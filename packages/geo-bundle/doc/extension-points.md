# Extension points

What an application configures, replaces, decorates or listens to. The ports and value types are `phil/geo`'s.

## Geocoding

| Point | How |
|---|---|
| The providers, in the order they are asked | `geocoding.providers` and each provider's keys; several make a `ChainGeocoder` — see [Geocoding](geocoding.md) |
| Answers kept a while | `geocoding.cache.pool` and `lifetime`: a `CachedGeocoder` around the chain |
| The IP locator | `ip_locator.maxmind` or `ip_locator.ipstack`; MaxMind wins when both are configured |

| Service | How an application relies on it |
|---|---|
| `Phil\Geo\Geocoding\Geocoder` | Injected wherever addresses are geocoded; an alias of the provider, the chain or the cache. An application with a provider not shipped registers a `Geocoder` of its own — a `ChainGeocoder` over it and the configured `phil_geo.geocoder.<provider>` services, say — and points the alias at it |
| `Phil\Geo\Geocoding\IpLocator` | Injected to locate a visitor roughly; replaced by the application's own the same way |
| `phil_geo.geocoder.<provider>` | One service per provider configured, for an application that composes them itself |

## Forms and storage

| Point | How |
|---|---|
| Where a position is kept | `Phil\Geo\Coordinates` as an embeddable of two columns, mapped while `doctrine_mapping` is on; `false` for a mapping of the application's own — see [Doctrine](doctrine.md) |
| More than the field does | A form type extension of the application's on `CoordinatesType` — see [Forms](forms.md) |

## Twig and the browser

`geo_attributes()` writes `data-latitude` and `data-longitude`, blurred to the decimals asked for; the Stimulus
controller `phil--geo-bundle--distance-sort` reads them on its `item` targets, takes `latitude`, `longitude`,
`locate`, `system` and `locale` values, and dispatches `sorted`, with the origin, and `denied` when the browser gives
no position — see [Sorting by distance](distance-sort.md). The functions of `distance.js` — `distance()`,
`sortByDistance()`, `formatDistance()`, `positionOf()` — are exported for an application's own controllers.

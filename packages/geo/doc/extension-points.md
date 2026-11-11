# Extension points

The library is plain PHP and declares no service. Geocoding is behind two ports; the value types take what an
application chooses as arguments.

## `Geocoder`

`Phil\Geo\Geocoding\Geocoder` — places from an address, and from a point. See [Geocoding](geocoding.md).

| Method | Contract |
|---|---|
| `geocode(string $address, ?string $locale = null): list<Place>` | The places found, the best first; an empty list when nothing is found; `GeocodingFailed` when the provider cannot answer, its message naming the provider and never the request |
| `reverse(Coordinates $at, ?string $locale = null): list<Place>` | The same, from a point; a provider that finds nothing from points returns an empty list |

Ten adapters are shipped. `ChainGeocoder` asks several in turn, the next when one finds nothing or fails;
`CachedGeocoder` keeps answers in a PSR-6 pool for a lifetime the provider's terms allow. A geocoder of the
application's own — a provider not shipped, its own database of places — is used alone or in either.

## `IpLocator`

`Phil\Geo\Geocoding\IpLocator` — a rough place from an IP address.

| Method | Contract |
|---|---|
| `locate(string $ip): ?Place` | A city at best, or `null`; `InvalidPlace` for a string that is no IP address |

`IpstackLocator` asks ipstack; `MaxMindLocator` reads a MaxMind city database on the server and sends nothing. An IP
address is personal information: a locator of the application's own sends it nowhere it has not chosen.

## Value types

| Point | How |
|---|---|
| How much a published place is blurred | The decimals kept by `Coordinates::blurred()` |
| The unit of a distance | `Distance` in meters, kilometers or miles |
| Structured data | `SchemaOrg::of()` gives schema.org `GeoCoordinates` |

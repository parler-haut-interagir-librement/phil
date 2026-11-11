# Reference

| Type | Kind | Role |
|---|---|---|
| `Coordinates` | `final readonly class` | A point in decimal degrees; text, equality, blurring, distance |
| `Distance` | `final readonly class` | A distance in meters, kilometers or miles |
| `BoundingBox` | `final readonly class` | The latitudes and longitudes around a point within a distance |
| `Exception\GeoException` | interface | Every exception of the library |
| `Exception\InvalidCoordinates` | `final class`, `InvalidArgumentException` | A latitude, longitude, distance, precision or text refused |
| `Exception\InvalidPlace` | `final class`, `InvalidArgumentException` | A country code or an IP address refused |
| `Exception\GeocodingFailed` | `final class`, `RuntimeException` | A geocoding provider that could not answer |
| `Geocoding\Geocoder`, `Geocoding\IpLocator` | interfaces | The two ports of geocoding |
| `Geocoding\Place` | `final readonly class` | A place a provider found |
| `Geocoding\Adapter\NominatimGeocoder`, `GoogleMapsGeocoder`, `NrcanGeocoder`, `PhotonGeocoder`, `OpenCageGeocoder`, `HereGeocoder`, `MapboxGeocoder`, `AzureMapsGeocoder`, `AdressesQuebecGeocoder` | `Geocoder` | The providers of addresses |
| `Geocoding\Adapter\IpstackLocator` | `IpLocator` | The provider of IP addresses |
| `Geocoding\Adapter\MaxMindLocator` | `IpLocator` | A MaxMind city database on the server |
| `Geocoding\MaxMind\Database` | `final class` | A MaxMind DB file: `find(string $ip)`, `metadata()` |
| `Geocoding\MaxMind\Metadata` | `final readonly class` | Node count, record size, IP version, type, languages, build date |
| `Exception\InvalidDatabase` | `final class`, `RuntimeException` | A MaxMind database missing, unreadable or broken |
| `Geocoding\Adapter\ChainGeocoder`, `CachedGeocoder` | `Geocoder` | Several providers in turn; answers kept a while |

## schema.org

`SchemaOrg::of(Coordinates)` gives a `GeoCoordinates`, to embed in a `Place`. The array has a `@type` and no `@context`: it is meant to be embedded in a larger
JSON-LD document.

| Type | Kind | Role |
|---|---|---|
| `SchemaOrg` | `final class` | `of(Coordinates)`: schema.org `GeoCoordinates` |

<!-- phil-dev source-sha256=45b8ef869897a4d446f8bdb03987d3d0b85dff78f8388ddc3407c29ce243c04c -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Coordinates` | `final readonly class` | Un point en degrés décimaux ; texte, égalité, brouillage, distance |
| `Distance` | `final readonly class` | Une distance en mètres, kilomètres ou milles |
| `BoundingBox` | `final readonly class` | Les latitudes et longitudes autour d'un point, dans une distance donnée |
| `Exception\GeoException` | interface | Toutes les exceptions de la bibliothèque |
| `Exception\InvalidCoordinates` | `final class`, `InvalidArgumentException` | Une latitude, une longitude, une distance, une précision ou un texte refusé |
| `Exception\InvalidPlace` | `final class`, `InvalidArgumentException` | Un code de pays ou une adresse IP refusé |
| `Exception\GeocodingFailed` | `final class`, `RuntimeException` | Un fournisseur de géocodage qui n'a pas pu répondre |
| `Geocoding\Geocoder`, `Geocoding\IpLocator` | interfaces | Les deux ports du géocodage |
| `Geocoding\Place` | `final readonly class` | Un lieu trouvé par un fournisseur |
| `Geocoding\Adapter\NominatimGeocoder`, `GoogleMapsGeocoder`, `NrcanGeocoder`, `PhotonGeocoder`, `OpenCageGeocoder`, `HereGeocoder`, `MapboxGeocoder`, `AzureMapsGeocoder`, `AdressesQuebecGeocoder` | `Geocoder` | Les fournisseurs d'adresses |
| `Geocoding\Adapter\IpstackLocator` | `IpLocator` | Le fournisseur d'adresses IP |
| `Geocoding\Adapter\MaxMindLocator` | `IpLocator` | Une base de villes MaxMind sur le serveur |
| `Geocoding\MaxMind\Database` | `final class` | Un fichier MaxMind DB : `find(string $ip)`, `metadata()` |
| `Geocoding\MaxMind\Metadata` | `final readonly class` | Le nombre de nœuds, la taille des fiches, la version IP, le type, les langues, la date de construction |
| `Exception\InvalidDatabase` | `final class`, `RuntimeException` | Une base MaxMind absente, illisible ou endommagée |
| `Geocoding\Adapter\ChainGeocoder`, `CachedGeocoder` | `Geocoder` | Plusieurs fournisseurs à tour de rôle ; des réponses conservées un certain temps |

## schema.org

`SchemaOrg::of(Coordinates)` donne des `GeoCoordinates`, à intégrer dans un `Place`. Le tableau a un `@type` et pas de
`@context` : il est fait pour être intégré dans un document JSON-LD plus grand.

| Type | Genre | Rôle |
|---|---|---|
| `SchemaOrg` | `final class` | `of(Coordinates)` : les `GeoCoordinates` de schema.org |

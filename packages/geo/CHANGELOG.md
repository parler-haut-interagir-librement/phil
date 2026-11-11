# Changelog

All notable changes to `phil/geo` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Coordinates`, `Distance`, `BoundingBox`; `GeoException` and `InvalidCoordinates`.
- `SchemaOrg`: the schema.org form of the library's value, to embed in JSON-LD.
- Geocoding: the ports `Geocoding\Geocoder` and `Geocoding\IpLocator`, `Geocoding\Place`, `GeocodingFailed` and
  `InvalidPlace`, whose message shows a refused IP address cut to 64 bytes, control characters escaped. A
  `GeocodingFailed` from an unreadable answer gives the HTTP status or the kind of failure only,
  never the HTTP client's message, which names the request URL with its key and the address asked about.
- The adapters, on `symfony/http-client-contracts` and `symfony/cache-contracts`, suggested: `NominatimGeocoder`;
  `PhotonGeocoder`, Photon on OpenStreetMap data, without a key — the public server or one's own —, a locale sent
  only in a language the server knows; `GoogleMapsGeocoder`; `NrcanGeocoder`, the geolocation service of Natural
  Resources Canada, in English or in French, without a key; `OpenCageGeocoder`, `HereGeocoder`, `MapboxGeocoder`
  (temporary or permanent answers), `AzureMapsGeocoder`; `AdressesQuebecGeocoder`, the address register of Québec
  without a key; `IpstackLocator`, which asks `https://api.ipstack.com` by default, plain HTTP being a base URL chosen
  knowingly; `ChainGeocoder` and `CachedGeocoder`. A provider's country that is not two letters is a
  `GeocodingFailed`, which a `ChainGeocoder` passes over. The keys of `GoogleMapsGeocoder` and `IpstackLocator` are
  `#[SensitiveParameter]`.
- MaxMind databases read on the server: `Geocoding\MaxMind\Database` and `Metadata`, from the MaxMind DB format
  specification, the file read in blocks of 4 KB of which up to 64 (256 KB) are kept; `MaxMindLocator`, an
  `IpLocator` on a city database, where a country that is not two letters is a `GeocodingFailed`;
  `InvalidDatabase`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

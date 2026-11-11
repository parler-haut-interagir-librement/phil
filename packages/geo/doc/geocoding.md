# Geocoding

## The ports

| Type | Role |
|---|---|
| `Geocoding\Geocoder` | `geocode(string $address, ?string $locale)` and `reverse(Coordinates $at, ?string $locale)`: places, the best first |
| `Geocoding\IpLocator` | `locate(string $ip)`: a place, roughly — a city at best —, or null |
| `Geocoding\Place` | `coordinates`, `provider`, `label`, `street`, `locality`, `region`, `postalCode`, `countryCode` |
| `Exception\GeocodingFailed` | A provider that could not answer |

A part of an address a provider does not give is null; a country is its two letters of ISO 3166-1, in capitals. A
provider that answers that it found nothing gives an empty list, never a failure. A provider that is unreachable,
refuses the key, is over its quota, or answers what it should not — a place without coordinates, coordinates out
of bounds, a country that is not two letters, something other than JSON — is a `GeocodingFailed`. Its message
gives the provider and the HTTP status or the kind of failure, never the request: the URL of a request holds the key
and the address or IP address asked about, so neither the HTTP client's message nor its exception is kept, and a log
or an error page that shows the failure shows no secret and no personal information.

The ports and the places name nothing but this library: a domain model may hold them.

## The adapters

They take the `HttpClientInterface` of Symfony.

| Adapter | Takes | Asks |
|---|---|---|
| `NominatimGeocoder` | the user agent, a base URL — the public server by default —, the most places (5) | `/search` and `/reverse`, `jsonv2`, with the details of the address |
| `GoogleMapsGeocoder` | the key, a base URL | the Geocoding API: `address`, or `latlng` |
| `NrcanGeocoder` | a base URL — the service of Natural Resources Canada —, the most places (5) | `/{en,fr}/locate?q=` |
| `PhotonGeocoder` | a base URL — the public server —, the most places (5), the languages the server knows (`de`, `en`, `fr`) | `/api?q=` and `/reverse?lat=&lon=`, GeoJSON |
| `OpenCageGeocoder` | the key, a base URL, the most places (5) | `?q=` an address or `lat,lng`, without annotations |
| `HereGeocoder` | the key, the geocode and reverse URLs, the most places (5) | `/geocode?q=` and `/revgeocode?at=`, with `show=countryInfo` |
| `MapboxGeocoder` | the access token, a base URL, the most places (5), whether answers are permanent | `/forward?q=` and `/reverse?longitude=&latitude=` of the v6 API |
| `AzureMapsGeocoder` | the subscription key, a base URL, the most places (5), the API version (`2026-01-01`) | `/geocode?query=` and `/reverseGeocode?coordinates=` |
| `AdressesQuebecGeocoder` | a base URL — the service of the Gouvernement du Québec —, the most places (5) | `/findAddressCandidates?SingleLine=` and `/reverseGeocode?location=`, in WGS 84 |
| `IpstackLocator` | the access key, a base URL — `https://api.ipstack.com` | `/{ip}` |

The locale given becomes `accept-language` for Nominatim and `language` for Google Maps. Nominatim's usage policy
asks for a user agent that names the site and how to reach it, and one request a second at most on its public
server. ipstack refuses an IP address that is not one before asking. It asks over HTTPS; a plan that answers over
plain HTTP only takes `http://api.ipstack.com` as its base URL, and every lookup then sends the visitor's IP address
— personal information under Québec's Law 25 — and the access key in clear. `MaxMindLocator` reads a database on
the server and sends nothing.

The geolocation service of Natural Resources Canada is free and asks for no key, for Canada only: street addresses,
intersections, postal codes and the names of the Canadian Geographical Names Database. A locale in French asks for
French titles, any other for English ones. It finds places from words and never from a point, so `reverse()` finds
nothing and a chain asks the next geocoder. Its places carry the title the service gives as their label, `CA` as
their country, and the postal code when that is what it found; it gives no other part of an address. An answer
that is a message instead of places is a `GeocodingFailed`.

Photon finds places in OpenStreetMap data as one types. Its public server asks for no key and for reasonable use
— it throttles heavy users —; one's own server is a base URL. A server refuses a language it does not know, so a
locale is sent as `lang` only when its language — `fr` for `fr_CA` — is one the geocoder was given; otherwise
places come in their local names. A place carries its street — number and street —, city, state, postal code and
country code, and a label made of its name and these parts; a place that is a street, a city or a state gives its
name as that part.

OpenCage, HERE and Mapbox take the locale as an IETF tag — `fr-CA` for `fr_CA` —, Azure Maps as its
`Accept-Language` header. Their terms differ on what an application may keep, which matters to `CachedGeocoder`:

| Provider | Key | What may be kept | A point gives |
|---|---|---|---|
| OpenCage | its dashboard's | everything, as long as one likes | one place at most |
| HERE | the HERE platform's | thirty days at most, unless the right to keep it is bought: a cache lifetime under thirty days | up to the most places |
| Mapbox | an access token | nothing, unless `permanent` — billed —; its temporary answers are shown only with a Mapbox map | one place |
| Azure Maps | the Azure account's | as the Microsoft Product Terms say | the most precise place |
| Adresses Québec | none | its data is under CC BY 4.0: credit the Ministère des Ressources naturelles et des Forêts | the address there, or none |

The country is the alpha-2 code each gives — HERE's from `countryInfo`, which the adapter asks for. Adresses Québec
gives addresses of Québec only, from the province's address register, without language, region or country: its
places have `Québec` and `CA`, and a postal code as `A1A 1A1`. Its candidates come in no order, so they are sorted
by score; a street matched without a number has no street part. A point where it finds no address answers an error,
read as no place; an error on an address is a `GeocodingFailed`. The Gouvernement du Québec is replacing the service
with one on its new address register, announced for 2026: the base URL is configurable.

```php
$geocoder = new CachedGeocoder(
    new ChainGeocoder([
        new NominatimGeocoder($httpClient, 'Example (contact@example.com)'),
        new GoogleMapsGeocoder($httpClient, $key),
    ]),
    $cache,
    604800,
);

$places = $geocoder->geocode('4545 avenue Pierre-De Coubertin, Montréal', 'fr');
$places[0]->coordinates;    // Coordinates
```

## Chain and cache

`ChainGeocoder` asks its geocoders in turn and gives the places of the first that finds some. One that fails is
passed over; when every one fails, the last failure is thrown; when none finds anything, there is no place.

`CachedGeocoder` keeps an answer for its lifetime — a week by default — in a cache of `symfony/cache-contracts`,
by the operation, the address or the point, and the locale. A failure is not kept.

## MaxMind databases, on the server

`MaxMindLocator` locates an IP address in a MaxMind city database kept on the server — GeoLite2 City, free with a
MaxMind account, or GeoIP2 City. Nothing leaves the server: no request, no key, no quota, and the visitor's address
stays with the site, which a privacy law such as Québec's Law 25 favours.

```php
use Phil\Geo\Geocoding\Adapter\MaxMindLocator;
use Phil\Geo\Geocoding\MaxMind\Database;

$locator = new MaxMindLocator(new Database('/var/lib/geoip/GeoLite2-City.mmdb'), 'fr');

$place = $locator->locate('81.2.69.142');
$place?->locality;     // "Londres", or the English name when the database has no French one
$place?->countryCode;  // "GB"
```

- The place has the city, the first subdivision as its region, the postal code and the country; its label is the
  city, the region and the country's name, in the locale given.
- A network the database does not hold, or a record without a position — every record of a country database —, gives
  no place.
- An address that is not one throws `InvalidPlace`, whose message shows at most its first 64 bytes, control
  characters escaped; a file that is missing, unreadable or broken, `InvalidDatabase`.

`Database` reads any MaxMind DB file, as its format specification (version 2.0) describes: `find($ip)` gives the
record of the network an address belongs to, as the database holds it, and `metadata()` what the file says of
itself. The file is opened on the first lookup and read where each lookup leads, never loaded whole: it is read in
blocks of 4 KB, and a `Database` keeps up to 64 of them (256 KB) for the lookups that follow. An IPv4 address
is found in an IPv6 database; an IPv6 address in an IPv4 database finds nothing. A 64- or 128-bit integer too large
for PHP is its hexadecimal digits, `0x` first.

The database is the application's: it downloads it with its MaxMind account, keeps it up to date — `geoipupdate`
replaces the file, and a new `Database` reads the new one —, and shows the attribution MaxMind's licence asks for.


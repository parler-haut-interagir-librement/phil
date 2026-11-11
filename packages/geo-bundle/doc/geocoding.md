# Geocoding

```yaml
phil_geo:
    geocoding:
        providers: [nrcan, nominatim, google_maps]   # asked in this order
        nominatim:
            user_agent: 'Example (contact@example.com)'
            base_url: 'https://nominatim.openstreetmap.org'
            limit: 5
        google_maps:
            api_key: '%env(GOOGLE_MAPS_KEY)%'
        nrcan:
            limit: 5
        photon:
            base_url: 'https://photon.komoot.io'
            languages: [de, en, fr]
        opencage:
            api_key: '%env(OPENCAGE_KEY)%'
        here:
            api_key: '%env(HERE_KEY)%'
        mapbox:
            access_token: '%env(MAPBOX_TOKEN)%'
            permanent: false        # true: answers that may be kept, billed
        azure_maps:
            subscription_key: '%env(AZURE_MAPS_KEY)%'
        adresses_quebec:
            limit: 5
        cache:
            pool: cache.app
            lifetime: 604800
    ip_locator:
        maxmind:
            database: '%kernel.project_dir%/var/geoip/GeoLite2-City.mmdb'
            locale: fr
            account_id: '%env(MAXMIND_ACCOUNT_ID)%'
            license_key: '%env(MAXMIND_LICENSE_KEY)%'
        ipstack:
            access_key: '%env(IPSTACK_KEY)%'
```

| Option | Default | Role |
|---|---|---|
| `geocoding.providers` | none | `nominatim`, `google_maps`, `nrcan`, `photon`, `opencage`, `here`, `mapbox`, `azure_maps`, `adresses_quebec`; none, and no `Geocoder` is registered |
| `geocoding.nominatim.user_agent` | | Required with Nominatim: the site and how to reach it |
| `geocoding.nominatim.base_url` | the public server | Another server, one's own |
| `geocoding.nominatim.limit` | `5` | The most places an address gives, 1 to 40 |
| `geocoding.google_maps.api_key` | | Required with Google Maps |
| `geocoding.google_maps.base_url` | the Geocoding API | |
| `geocoding.nrcan.base_url` | the service of Natural Resources Canada | |
| `geocoding.nrcan.limit` | `5` | The most places an address gives, 1 to 40 |
| `geocoding.photon.base_url` | the public server | One's own server |
| `geocoding.photon.limit` | `5` | The most places an address gives, 1 to 40 |
| `geocoding.photon.languages` | `[de, en, fr]` | The languages the server knows; a locale in another is not sent |
| `geocoding.opencage.api_key` | | Required with OpenCage |
| `geocoding.opencage.base_url`, `limit` | its API, `5` | The most places an address gives, 1 to 100 |
| `geocoding.here.api_key` | | Required with HERE |
| `geocoding.here.geocode_url`, `reverse_url`, `limit` | its API, `5` | The most places, 1 to 100 |
| `geocoding.mapbox.access_token` | | Required with Mapbox |
| `geocoding.mapbox.base_url`, `limit` | the v6 API, `5` | The most places an address gives, 1 to 10 |
| `geocoding.mapbox.permanent` | `false` | Answers that may be kept, which Mapbox bills; temporary ones may not be cached |
| `geocoding.azure_maps.subscription_key` | | Required with Azure Maps |
| `geocoding.azure_maps.base_url`, `limit`, `api_version` | its API, `5`, `2026-01-01` | The most places an address gives, 1 to 20 |
| `geocoding.adresses_quebec.base_url`, `limit` | the service of the Gouvernement du Québec, `5` | The most places an address gives, 1 to 40 |
| `geocoding.cache.pool` | none | A cache pool that keeps the answers |
| `geocoding.cache.lifetime` | `604800` | Seconds an answer is kept, a week |
| `ip_locator.maxmind.database` | none | The path of a MaxMind city database: registers it as the `IpLocator`, before ipstack |
| `ip_locator.maxmind.locale` | `en` | The language of the names, English when the database has none in it |
| `ip_locator.maxmind.account_id`, `license_key` | none | The MaxMind account `phil:geo:maxmind:update` downloads with: both or neither, and only with a `database`; together they register the command |
| `ip_locator.maxmind.edition` | `GeoLite2-City` | The edition downloaded: `GeoIP2-City` on a paid account |
| `ip_locator.maxmind.download_url` | MaxMind's | |
| `ip_locator.ipstack.access_key` | none | Registers the ipstack locator |
| `ip_locator.ipstack.base_url` | `https://api.ipstack.com` | `http://api.ipstack.com` on a plan that answers over plain HTTP only, which sends the visitor's IP address and the access key in clear |

One provider is the `Geocoder`; several are a `ChainGeocoder`, asked in their order; with a pool, the whole is a
`CachedGeocoder`. Each adapter takes the `http_client` service of FrameworkBundle. A pool keeps every provider's
answers, so the configuration refuses a pool when the providers include Mapbox without `permanent`: its terms forbid
keeping its temporary answers. Keep a lifetime under thirty days with HERE ([the terms of each](../../geo/doc/geocoding.md)).

| Id | |
|---|---|
| `phil_geo.geocoder.{provider}` — `nominatim`, `google_maps`, `nrcan`, `photon`, `opencage`, `here`, `mapbox`, `azure_maps`, `adresses_quebec` | each provider listed |
| `phil_geo.geocoder.chain` | with several providers |
| `phil_geo.geocoder.cached` | with a pool |
| `Phil\Geo\Geocoding\Geocoder` | alias of the last of them |
| `phil_geo.ip_locator.ipstack` | with an access key; aliased as `Phil\Geo\Geocoding\IpLocator` when no MaxMind database is named |
| `phil_geo.maxmind.database`, alias `Phil\Geo\Geocoding\MaxMind\Database` | with a MaxMind database |
| `phil_geo.ip_locator.maxmind`, alias `Phil\Geo\Geocoding\IpLocator` | with a MaxMind database |
| `phil_geo.maxmind.updater`, `phil_geo.maxmind.update_command` | with a MaxMind database, `account_id` and `license_key` |

The MaxMind database comes first: an address looked up on the server never leaves it. The file is the
application's — kept up to date by `phil:geo:maxmind:update` or by `geoipupdate` —, and the path need not exist when
the container is built: it is opened on the first lookup. Reading it needs no HTTP client: without `account_id` and
`license_key`, nothing that downloads is registered, and the application may go without `symfony/http-client`.
ipstack's locator is registered with its access key even beside a database, for whoever asks for it by its id.

```php
public function __construct(private Geocoder $geocoder) {}

$places = $this->geocoder->geocode($address, $request->getLocale());
```

The places, the failures and the providers are `phil/geo`'s: see its geocoding document.

## Keeping the MaxMind database up to date

```sh
bin/console phil:geo:maxmind:update           # downloads only when MaxMind has a newer database
bin/console phil:geo:maxmind:update --force   # downloads it anyway
```

The command is registered with a MaxMind database, `account_id`, `license_key` and `symfony/console`; it asks
MaxMind through the `http_client` service. It asks MaxMind for the SHA-256 of the
latest archive of the edition, with the account and the licence key; when it is the checksum kept beside the
database, in `GeoLite2-City.mmdb.sha256`, nothing is downloaded. Otherwise it:

1. downloads the archive next to the database, under a name of its own, as it arrives;
2. checks it against that checksum;
3. reads `GeoLite2-City.mmdb` out of it, block by block, never the whole archive in memory;
4. opens it, to make sure it is a MaxMind database of the edition asked for;
5. renames it over the old one, in one step, and keeps its checksum.

Any failure — an account refused, an archive that does not match, a file that is not the edition — leaves the
database in place and its temporary files removed, and the command fails with the reason. One update runs at a time:
it holds a lock on `GeoLite2-City.mmdb.lock`, left beside the database, and a second run — a cron and a forced
update, two hosts on shared storage — fails at once without touching the first one's files. Run it daily, as MaxMind
publishes GeoLite2 twice a week:

```text
0 4 * * * cd /var/www/site && bin/console phil:geo:maxmind:update --no-interaction
```

A web request opens the database on its first lookup, so it reads the new file at once. A long-running worker — a
Messenger consumer, FrankenPHP in worker mode — keeps the file it opened until it restarts.


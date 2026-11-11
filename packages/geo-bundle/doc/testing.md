# Testing

| Area | What is covered |
|---|---|
| `CoordinatesType`, `CoordinatesTransformer` | Submitting, showing, an empty optional position, half a position, a position off the Earth |
| `GeoExtension` | Distances, their writing in each system and locale, the attributes of a place blurred or not |
| `PhilGeoBundle` | The services, the embeddable prepended with the ORM only, and left to the application on demand |
| `GeocodingServices` | No provider, one, several chained and cached, the IP locator, every option and bound, the options a provider needs |
| `DatabaseUpdater`, `phil:geo:maxmind:update` | Archives the tests write — headers as tar writes them, a directory, a file in the way, an archive inside the archive, a database after the end —; the checksum kept and the download skipped, forced; every refusal leaving the database in place; the account sent; the output, plain and coloured |
| Geocoding in an application | Nominatim answered by a `MockHttpClient` as `http_client`, through `cache.app`: one request for two questions |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, TwigBundle
and the bundle: `GeoBundleTest` stores a position in its two columns, submits the form in `fr_CA` and renders the Twig
functions. Installed on its own, where FrameworkBundle is a development requirement, the suite is skipped.

`tests/js` runs `distance.js` and the controller under Node's test runner, Stimulus resolved to a stub.

```sh
vendor/bin/phpunit packages/geo-bundle/tests
```

```sh
node --import ./packages/geo-bundle/tests/js/register.mjs --test packages/geo-bundle/tests/js/distance-sort.test.mjs
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/geo-bundle/tests/Benchmark
```

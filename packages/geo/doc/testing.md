# Testing

| Area | What is covered |
|---|---|
| `Coordinates` | Bounds, NaN and infinities, text read and written, equality, blurring, distances against known values, antipodes |
| `Distance` | Units, comparison, refusals |
| `BoundingBox` | Points in every direction, edges, poles, the antimeridian, a radius larger than the Earth |
| Geocoding | `Place` and its errors; each adapter, Natural Resources Canada, Photon, OpenCage, HERE, Mapbox, Azure Maps and Adresses Québec among them, against answers of `MockHttpClient` — places, every part of an address, the queries sent, the user agent, nothing found, keys refused, transport errors, answers that are not JSON or lack coordinates —; the chain, the cache, its keys and its lifetime |
| MaxMind | Databases the tests write themselves (`MmdbWriter`): records of 24, 28 and 32 bits, IPv4 and IPv6 trees, every type of the data section in each of its sizes, pointers of the four sizes, values 64 deep, each broken file refused; the city record made a place, in a locale and in English |

Infection holds the library at 100 % of mutants killed.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/geo/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/geo/tests/Benchmark
```

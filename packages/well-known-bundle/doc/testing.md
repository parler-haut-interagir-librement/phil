# Testing

| Area | What is covered |
|---|---|
| `WellKnownPath` | Names, absolute paths, route names, the bounds, every refused path |
| `SecurityTxt` | Every field and its order, the required ones, each refused value, `Expires` dates and days the calendar lacks |
| `WebFingerQuery`, `WebFingerResource` | `acct:`, `https:` and other schemes, normalization, repeated `rel`, the bounds, every refused query |
| `WebFingerDescriptor`, `WebFingerLink`, `WebFinger`, `StaticWebFingerResolver` | The JRD written, merging, filtering by relation, resolvers in order |
| `NodeInfo`, `NodeInfoSoftware`, `NodeInfoUsage`, `ConfiguredNodeInfoProvider` | The 2.1 document, empty objects, every refused value |
| `Did` | DIDs, methods, the bounds |
| The documents | Bodies, media types, CORS, redirects, signing, hosts, `400` and `404` |
| `WellKnownController` | Cache headers, `ETag` and `304`, a document's own cache, errors and redirects, an unknown document |
| `WellKnownRouteLoader` | Routes, methods, defaults, the cache resource, the type |
| `PhilWellKnownBundle`, `WellKnownServices`, `WellKnownPass` | Defaults, every document, files, every refused configuration, file and tag |
| `SecurityTxtExpiryCheck` | Up, degraded from the warning, down from `Expires`, the days rounded up, the date in UTC, the refused warning, its registration |
| A whole application | Every document served with its headers, `304` and `HEAD`, the bodies, the signer, WebFinger with a resolver, `change-password`, `405`, every address a `404` when its document is off, and the expiry among the checks of phil/monitoring-bundle |

```sh
vendor/bin/phpunit packages/well-known-bundle/tests
```

## Fuzzing

The `webfinger-query` target reads text nobody wrote as a WebFinger query: it returns a query whose resource reads
back to the same URI, or throws `InvalidValue`, and nothing else. From the monorepo root:

```sh
PHIL_DEV_PACKAGES=well-known-bundle composer fuzz
```

## Benchmarks

`WebFingerBench` serves a WebFinger query among 1,000 accounts, an unknown account, and a fixed document, through the
controller:

```sh
composer bench -- packages/well-known-bundle/tests/Benchmark
```

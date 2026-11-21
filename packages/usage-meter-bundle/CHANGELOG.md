# Changelog

All notable changes to `phil/usage-meter-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `PhilUsageMeterBundle` and the `phil_usage_meter` configuration: the store, the DBAL connection and table names,
  the clock, the limit providers, the subscription features and their prefix, the anchor provider, the meters, the
  rate limiters and the secret of the idempotency keys' hashes.
- `ConfiguredMeters`: the meters of the configuration — a limit and exactly one of a calendar period in a time zone,
  a rolling window in buckets, or a billing cycle from an anchor — built once at load time so that a bad meter fails
  the container build.
- `UsageMeterServices`: the `Meters`, `UsageStore` and public `UsageMeter` services; the DBAL store with
  `UsageSchemaListener` on the connection's `postGenerateSchema` event, the in-memory store, or the application's;
  the limit providers in a `LimitChain`, with `FeatureLimits::fromSubscriptions()` last when the subscription features
  are on; and a check that every rate limiter counts on a declared meter.
- `MeterLimiter`: a limiter of `phil/rate-limit-bundle`, tagged `phil_rate_limit.limiter` with its name, key strategy
  and amount, one per entry of `rate_limiters`, that counts each request on its meter for the request's key, so that the
  subject's limits and the meter's period apply; refused with the meter's limit and the seconds until its window frees
  some use; with `idempotency_key`, a POST, PUT, PATCH or DELETE request counted once per `Idempotency-Key` header, the
  key kept as an HMAC of the limiter, the method, the URI, the body and the value, a malformed header answered
  `400 Bad Request`; the container build failed when rate limiters are declared without `PhilRateLimitBundle`.
- `PurgeUsageCommand`, `phil:usage-meter:purge`: the counters and keys no window counts any more forgotten.
- The Flex recipe in `recipe/0.1`: the bundle registered and `config/packages/phil_usage_meter.yaml`, the counters
  on the `default` DBAL connection, without meters.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

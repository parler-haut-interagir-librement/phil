# Changelog

All notable changes to `phil/rate-limit-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.3-alpha.

### Changed

- The development requirements ask for the latest minor of each dependency (`twig/twig` 3.31,
  `symfony/polyfill-intl-normalizer` 1.43, `symfony/polyfill-mbstring` 1.43, `symfony/translation-contracts` 3.8).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

### Security

- The keys of the counts were the HMAC of the address or of the user under the secret itself, `%kernel.secret%` by
  default, the same hash other bundles kept for the same address. `ClientIpKey`, `IpRouteKey` and `UserKey` derive
  their keys by HKDF-SHA256 under `phil_rate_limit.client_ip_key`, `phil_rate_limit.ip_route_key` and
  `phil_rate_limit.user_key` (`USAGE` of each). The counts start again at the update.

## 0.1.2-alpha - 2026-11-20

One person, or one client, counts once whatever the form of their identifier or of
their address.

### Added

- `IdentifierKey::normalize()`: the form of an identifier a key is built from — compatibility decomposition, case
  folding, combining marks, format and control characters removed, trimmed —, as Symfony's login throttling builds its
  own. It goes through `symfony/string`, now required, and gives the same key with the intl and mbstring extensions or
  with the polyfills of `symfony/string`, for every character Unicode 13.0 assigns; a test runs it on the polyfills.
- Tests of limiters that count one request together, on the limiters of `symfony/rate-limiter`: a compound limiter
  whose tighter part refuses, and several rules on one route, in either order.

### Changed

- The intl and mbstring extensions are suggested, for speed; `symfony/string` joins the requirements.
- The functional tests keep their compiled container in a directory of their own checkout and environment, and boot the
  kernels that must fail on an empty cache; `symfony/filesystem` joins the development requirements.

### Security

- The `user` strategy hashed the identifier as the user provider gave it: `Ada` and `ada`, an accented letter written
  composed or decomposed, or an identifier with a zero-width space counted apart. It is normalized by `IdentifierKey`
  first.
- An IPv4-mapped IPv6 address, `::ffff:203.0.113.7`, counted apart from `203.0.113.7`: the `ip`, `user_or_ip` and
  `ip_route` strategies read the IPv4 address it holds.

## 0.1.1-alpha - 2026-11-14

### Security

- `lock_factory` defaults to `auto`: each limiter reserves under `lock.factory` when it is defined, as FrameworkBundle
  does, so that two requests of one key that read the same state can no longer both pass a full limit. `null` keeps
  reserving without a lock; a named factory that is not defined still fails the container build.

### Changed

- Documentation: login forms are limited with Symfony's `security.firewalls.<name>.login_throttling`, which counts
  each attempt before the password is checked and groups IPv6 addresses by /64; the examples limit a password reset
  instead, and the bundle stays for the APIs, the other forms, the controllers and the routes.

## 0.1.0-alpha - 2026-11-12

### Added

- `Domain\Attribute\RateLimit`: a repeatable attribute of controller classes and actions naming a limiter, with an
  optional amount and key strategy, checked when it is built.
- `Domain\ValueObject\Decision`: what a limiter answered — accepted or refused, the limit, what is left, the seconds
  until the reset and until a refused request may pass, the window and the limiter's name —, and which of two decisions
  leaves less.
- `Domain\HeaderStyle`: the draft `RateLimit-Policy` and `RateLimit` fields, the legacy `RateLimit-Limit`,
  `RateLimit-Remaining` and `RateLimit-Reset`, both or none.
- `Domain\Exception\RateLimitException`, `InvalidRateLimit` and `UnknownLimiter`.
- `Application\Contract\Limiter`, `KeyResolver` and `AddressNormalizer`: the ports of the limiters, the key
  strategies and the normalization of the client's address.
- `Application\Limiters` and `KeyResolvers`: the limiters with their default key and amount and whether they only
  observe, and the key strategies, by name from service locators.
- `Application\RateLimitRules`: the route default `_rate_limit` and the form option `rate_limit` read as rules — a name,
  a map of `limiter`, `amount` and `key`, or a list of up to sixteen.
- `Application\RateLimitEnforcer`: the rules of a request consumed in order, a request without a key left uncounted,
  a form's submissions keyed apart, keys over 200 bytes replaced by their SHA-256, the first refusal dispatched and
  thrown, the tightest accepted decision returned; the refusal of a limiter in observation dispatched as
  `RateLimitObserved` and logged as a warning on the `rate_limit` channel, without the key, and the request let through.
- `Application\RateLimitHeaders`: the rate-limit headers of a decision in the configured style, none without a limit.
- `Application\Event\RateLimitExceeded`: the limiter, the hashed key, the decision, the route and the form of a
  refusal.
- `Application\Event\RateLimitObserved`: the same fields, for a refusal a limiter in observation did not enforce.
- `Application\Exception\TooManyRequests`: `429 Too Many Requests` with `Retry-After` and the rate-limit headers.
- `Infrastructure\Http\RateLimitListener`: the attributes of the class, then of the action, then the route default
  held before the controller runs, on main requests only, and the headers of the tightest limiter on the response.
- `Infrastructure\Http\TooManyRequestsResponder`: an RFC 9457 problem as `application/problem+json` to a request whose
  format or first acceptable type is JSON, the Twig page `@PhilRateLimit/too_many_requests.html.twig` to the others,
  Symfony's error page without Twig; the words translated in the `phil_rate_limit` domain.
- `Infrastructure\Key\ClientIpKey`, `UserKey`, `UserOrIpKey` and `IpRouteKey`: the `ip`, `user`, `user_or_ip` and
  `ip_route` strategies, each an HMAC-SHA-256 under the bundle's secret; an IPv6 address counts by its /64 unless a
  normalizer is given.
- `Infrastructure\IpAnonymizer\NetworkAddress`: the client's network rather than its host, with the truncation of
  phil/ip-anonymizer.
- `Infrastructure\RateLimiter\SymfonyLimiter`: a factory of symfony/rate-limiter as a limiter, the wait rounded up to
  the second, the reset from the limiter or the window, no header for `no_limit`.
- `Infrastructure\Form\RateLimitTypeExtension`: the submissions of a root form limited by its `rate_limit` option or
  the `forms` section, a refusal added as a translated form error.
- `PhilRateLimitBundle` and the `phil_rate_limit` configuration: the secret, the header style, the error template, the
  cache pool and lock factory, `single_server`, the network prefixes, the limiters — fixed window, sliding window,
  token bucket, compound, no limit or a factory service, with their key, amount, pool, storage, lock and `observe` —
  and the forms.
- `Infrastructure\DependencyInjection\RateLimitServices`: the services of the configuration, each limiter's options
  checked by building it once.
- `Infrastructure\DependencyInjection\LimitersPass`: the limiters tagged `phil_rate_limit.limiter`, with their `key`,
  `amount` and `observe`, and the strategies
  tagged `phil_rate_limit.key_resolver` collected; the build failed on a missing cache pool, storage or lock factory, two
  limiters of one name, a key strategy or a form limiter nothing declares.
- The guard of `phil/rate-limit`, required: the build failed in `prod` on a pool or a storage local to each server,
  a `CacheStorage` followed to its pool, unless `single_server` says the application runs on one.
- `Infrastructure\Http\RateLimitCacheWarmer`: the `_rate_limit` default and the `#[RateLimit]` attributes of every route
  checked when the container is built, an unknown limiter or key strategy stopping the build.
- The template `too_many_requests.html.twig` and the English and French translations.
- The documentation in `doc/`, one file per topic, indexed by the README.
- The benchmark of the listener's overhead.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

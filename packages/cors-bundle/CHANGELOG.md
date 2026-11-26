# Changelog

All notable changes to `phil/cors-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- The configuration `phil_cors`: `enabled`, and `paths`, regular expressions on the path in the order they are read,
  each with its policy — `allow_origins`, `allow_origin_patterns`, `allow_methods` (`GET`, `HEAD`, `POST` by default),
  `allow_headers`, `expose_headers`, `allow_credentials`, `max_age` and `allow_private_network`.
- Checks while the container compiles, or when the services are built for a value read from the environment: a
  wildcard with credentials, `*` beside other values or among the methods, the origin `null`, an origin with a path, a
  default port or a port above 65535, an origin pattern not anchored by `^` and `$`, a pattern that does not compile, a
  method or header that is not an HTTP token, a path without an origin or a method, and a `max_age` outside 0 to 86400.
- `CorsPolicy`, built and checked in code or from the configuration: exact origins in a hash table, anchored patterns,
  methods and headers compared without case, an `Origin` longer than 512 bytes never allowed.
- `PathRule` and `PathPolicyResolver`, the first rule that matches the path info; `PolicyResolver`, the interface an
  application aliases to choose policies its own way.
- `CorsHeaders`: the answer to a preflight, `204` with the `Access-Control-Allow-*` headers only when the origin, the
  method and every requested header are allowed, the requested headers echoed when any header is allowed, and
  `Access-Control-Allow-Private-Network` when asked and allowed; `Access-Control-Request-Headers` bounded to 4096 bytes
  and 64 names; on other responses, `Vary: Origin` always, the application's `Access-Control-Allow-Origin`,
  `Access-Control-Allow-Credentials` and `Access-Control-Expose-Headers` removed, then set for an allowed origin.
- `CorsListener`: preflights of the configured paths answered on `kernel.request` at priority 250, before the router
  and the firewall; responses of those paths decorated on `kernel.response`, error pages included; sub-requests left
  alone; no state between requests.
- The Flex recipe, in `recipe/0.1`: the bundle registered and a rule for `^/api/` whose origins match
  `CORS_ALLOW_ORIGIN`.
- The documentation in `doc/`: installation, configuration, how requests are answered, coming from NelmioCorsBundle,
  extension points, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

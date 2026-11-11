# Changelog

All notable changes to `phil/well-known-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilWellKnownBundle` and the `phil_well_known` configuration: `cache_max_age` and one section per document, each
  off until it is turned on. A value a document could not serve is refused when the container is built.
- `WellKnownController`: every document from one controller, with `X-Robots-Tag: noindex`; a successful answer that
  sets no cache of its own is `public` for `cache_max_age` seconds, carries an `ETag` and answers `304` to a request
  that holds it.
- `WellKnownRouteLoader` and `config/routes.php`: one exact route per document, `GET` and `HEAD`, stateless, loaded
  again when the documents change.
- `WellKnownDocument`, `#[AsWellKnownDocument]` and the `phil_well_known.document` tag, with a `path` under
  `/.well-known/` or absolute and an optional `route`; `WellKnownPass` refuses a bad path, a path or a route served
  twice, and a class that is not a document. `WellKnownPath` checks the paths.
- `security.txt` (RFC 9116): `SecurityTxt`, with `Contact` and `Expires` required, `Encryption`, `Acknowledgments`,
  `Preferred-Languages`, `Canonical`, `Policy` and `Hiring`, each value checked against its field; an `Expires` in the
  past refused when the container is built. `SecurityTxtSigner`, the port of an OpenPGP cleartext signature.
  `SecurityTxtExpiryCheck`, registered with phil/monitoring-bundle: its `security_txt` health check is degraded from
  `security_txt.expiry_warning_days` days before `Expires`, 30 by default, and down once the date has passed.
- `change-password`: a `302` to a route, with its parameters, or to an `https://` URL or a path.
- WebFinger (RFC 7033): `WebFingerQuery`, read from the raw query string with its repeated `rel`, and bounded;
  `WebFingerResource`, `acct:` and `https:` URIs; `WebFingerDescriptor` and `WebFingerLink`, written as JRD;
  `WebFingerResolver`, autoconfigured after the `StaticWebFingerResolver` of `webfinger.resources`; `WebFinger`, which
  merges the descriptors and filters the links. `400` for a bad query, `404` with an empty body for a resource no
  resolver knows; CORS on every answer. A fuzz target for the query reader.
- NodeInfo 2.1: `NodeInfo`, `NodeInfoSoftware` and `NodeInfoUsage`, the protocols and services of the 2.1 schema;
  `NodeInfoProvider` and `ConfiguredNodeInfoProvider`; the discovery document at `/.well-known/nodeinfo` and the
  document at `nodeinfo.path`.
- `did.json` for `did:web`, from the configuration or a JSON file, served as `application/did+json` or
  `application/did+ld+json`; `did-configuration.json` with JWT or JSON-LD linked DIDs, from the configuration or files;
  `atproto-did`, the site's DID or the DID of each host; `Did`, which checks the syntax.
- `apple-app-site-association` and `assetlinks.json`, from Android apps with their fingerprints and from statements.
- `documents`: fixed documents of the configuration, from a string or a file, with their media type and CORS.
- `InvalidValue` and `WellKnownException`.
- `WebFingerBench`: a WebFinger query among 1,000 accounts, an unknown one, and a fixed document.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

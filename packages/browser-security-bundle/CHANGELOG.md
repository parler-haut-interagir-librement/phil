# Changelog

All notable changes to `phil/browser-security-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-25

### Added

- `ContentSecurityPolicyContext::scriptNonce()` and `styleNonce()`, and `Infrastructure\ErrorHandler\ErrorPageNoncePass`,
  which hands them to `HtmlErrorRenderer::setNonce()` of the ErrorHandler component: the HTML error page and the
  exception page carry the nonces of the response. `symfony/error-handler` ^8.2 joins `require`.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, …).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

### Security

- The HTML error page of the ErrorHandler component wrote its `<style>` element without a nonce: under a policy
  without `'unsafe-inline'`, the browser refused it, and a site that loosened the policy to show its error pages
  loosened it for every page.
- The throttle of the reports counted each client by the HMAC of its address under the secret itself,
  `%kernel.secret%` by default, the same hash other bundles kept for the same address. `ReportReceiver` derives its
  key by HKDF-SHA256 under `phil_browser_security.report_throttle` (`ReportReceiver::USAGE`).

## 0.2.0-alpha - 2026-11-20

The reports go to a path, or to `public_origin`, never to the host a request names;
the default policy refuses `<base>` and Strict-Transport-Security includes the subdomains, as ASVS asks.

### Added

- `public_origin`: the canonical address of the site, such as `https://example.com`, that the reports of violations go
  to, read by `phil/url` when the kernel boots; `ReportEndpoint::OPTION` and `ReportEndpoint::origin()`.
- `phil/url` joins `require`.
- The [ASVS 5.0 mapping](doc/asvs.md), and tests of the default policy sent whole and of the age of
  Strict-Transport-Security.

### Changed

- `report-uri` and `Reporting-Endpoints` name the path of the route, or of a path given in `reporting.uri`, which the
  browser resolves against the page; with `public_origin`, the path is put on it. An absolute `reporting.uri` is sent
  as it is.
- `ReportEndpoint::uri()` takes no request; its constructor takes the origin and whether `localhost` is accepted, last
  and optional.
- The default policy declares `base-uri 'none'` instead of `'self'`: a page that needs a `<base>` element declares
  `base-uri` in its own policy.
- `https.strict_transport_security.include_subdomains` is true by default: the header says `max-age=31536000;
  includeSubDomains`. A site whose subdomains are not all served over HTTPS sets it to false.

### Security

- `report-uri` was built on the `Host` header of the request: a request naming another host sent the reports, with the
  addresses of the pages, to that host, and could leave its name in a cached response.

## 0.1.1-alpha - 2026-11-14

### Security

- The report throttle the bundle builds reserves under `lock.factory` when it is defined, as FrameworkBundle does,
  so that two bodies of one client that read the same window can no longer both pass its last place.

## 0.1.0-alpha - 2026-11-12

### Added

- `Directive`, the directives of Content Security Policy Level 3 a policy declares, `InlineKind` and `Disposition`.
- `SourceSyntax`: the grammar of each directive's values — keywords, nonces, hashes, schemes, hosts, sandbox flags,
  Trusted Types policies —, bounded to 1,024 bytes, an unquoted keyword refused with a hint; `InvalidPolicy`.
- `Policy`: directives in declaration order, `'none'` alone, a directive added from `default-src` when it falls back
  on it, the header written once, and the nonces, hashes and reporting directives of a request added when it is read.
- `Protection` and `ExternalRedirectAction`; `ProtectionSyntax`: the values of each protection, what adding to one means
  and the text of its header.
- `PermissionsPolicy`, `ViolationReport` and its fingerprint, `StoredReport`, and the `ReportRepository` port.
- `PolicyRegistry` and `PolicyDefinition`: named policies, `extends`, the hashes of inline code, Trusted Types,
  `'strict-dynamic'` and reporting per policy; `UnknownPolicy`.
- `PolicyOverride`, the `#[ContentSecurityPolicy]` attribute and the `_csp` route default: another named policy,
  sources added, replaced or removed, report-only or enforced, or no policy.
- `ProtectionOverride`, the `#[BrowserSecurity]` attribute, the `_browser_security` route default and
  `BrowserSecurityContext`: the same three verbs for every other protection, by the name of the header or of the
  protection.
- `RequestState`, kept in the attributes of the main request: the overrides of the policy and of the protections,
  nonces of 128 random bits created on first
  use, hashes, the nonce shared with styles for Turbo.
- `ContentSecurityPolicyContext`: nonces, inline hashes and overrides from code.
- `HeaderBuilder`: `Content-Security-Policy`, `Content-Security-Policy-Report-Only` beside it, `report-uri`,
  `report-to` and `Reporting-Endpoints`.
- `SecurityHeaders` and `ResolvedHeaders`: `Permissions-Policy`, `Cross-Origin-Opener-Policy`,
  `Cross-Origin-Embedder-Policy` and the report-only form of each, `Cross-Origin-Resource-Policy`, `Referrer-Policy`,
  `X-Content-Type-Options`, `X-Frame-Options` by path, `Strict-Transport-Security` over HTTPS only,
  `X-Permitted-Cross-Domain-Policies`, `Origin-Agent-Cluster`, `Integrity-Policy` and its report-only form.
- `ContentSecurityPolicyListener` on `kernel.controller` and `kernel.response`: a response that carries a policy keeps
  it, a response whose policy lists a nonce is made private, the paths of the profiler are left out.
- `SecurityHeadersListener`: a response keeps the headers it carries unless an override names them; `ReportEndpoint`,
  the address of the reports of a response.
- `HttpsRedirectListener`: plain requests sent to HTTPS, on when `kernel.debug` is off.
- `CookieSigner` and `SignedCookiesListener`: the named cookies signed, a cookie whose signature fails taken away, the
  secret replaced without signing everyone out.
- `ExternalRedirectListener`: a redirect to a host not allowed refused, sent to a path of the site or logged, its target
  resolved by `Uri\WhatWg\Url`.
- `Clear-Site-Data` at logout on every firewall, with the listener of `symfony/security-http`.
- `UserContentPolicy`, `#[UserContent]`, the route default `_user_content` and `UserContentListener`: the strict headers
  of a file someone uploaded — a sandboxed policy of its own, the report-only one removed, `nosniff`, a type of
  `application/octet-stream` when none is given, `inline` only for the types that cannot run and `attachment` for the
  others, with a safe file name, `Cross-Origin-Resource-Policy`, `no-referrer`, and `X-Frame-Options: DENY` —, applied
  by route, by controller or by response before the other headers.
- `ReportParser` for `application/csp-report`, `application/reports+json` and `application/json`, the reports of the
  policy and those of the cross-origin and integrity policies, eight levels deep,
  a bounded number of reports, addresses without credentials, query nor fragment, samples of 40 characters;
  `InvalidReport`.
- `ReportReceiver` and `Receipt`: media type, size, throttle on a keyed digest of the address, one report per
  fingerprint and body, the `ViolationReported` event within a window of deduplication; the `ReportThrottle`,
  `ReportDeduplicator` and `ClientAddressAnonymizer` ports.
- `ReportController` and the route `phil_csp_report`, `POST /_csp/report`, stateless.
- `RateLimiterThrottle` on `symfony/rate-limiter`, `LimiterReportThrottle` on the limiter
  `phil_browser_security.report` of
  phil/rate-limit-bundle, the `RateLimitGuard` of phil/rate-limit, required, which refuses a rate limit without its
  component or, unless `single_server` is set, on a pool local to each server in `prod`, `CacheDeduplicator` on a
  PSR-6 pool,
  `TruncatedClientAddress` on `phil/ip-anonymizer`.
- `DbalReportRepository` on the table `phil_csp_report`, with its capacity, `ReportTable`, `ReportSchemaListener`
  and the command `phil:csp:purge-reports`.
- `CspExtension`, the Twig functions `csp_nonce()` and `csp_meta()`, and `NoncedImportMapRuntime`, the nonce on the
  tags of `importmap()`.
- `CspDataCollector` and its profiler panel.
- The configuration `phil_browser_security`: `enforce`, `report_only`, `policies`, `exclude_paths`, `importmap`,
  `reporting`, `headers`, `https`, `signed_cookies`, `external_redirects`, `clear_site_data`, `user_content` and
  `clock`.
- The fuzz target of the report parser and the benchmarks of the headers of a response.
- The documentation in `doc/`, one file per topic, indexed by the README, and its French mirror, `README.fr.md` and
  `doc/fr/`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

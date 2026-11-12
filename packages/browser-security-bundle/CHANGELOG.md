# Changelog

All notable changes to `phil/browser-security-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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

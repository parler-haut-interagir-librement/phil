# Reference

## Domain

| Type | Role |
|---|---|
| `Domain\Enum\Directive` | A directive a policy declares; whether it takes sources, falls back on `default-src`, takes script keywords. |
| `Domain\Enum\InlineKind` | `script` or `style`, and the directives that govern each. |
| `Domain\Enum\Disposition` | `enforce` or `report`. |
| `Domain\Enum\Protection` | What a page can adjust beside its policy, by the name of the header or of the protection. |
| `Domain\Enum\ExternalRedirectAction` | `refuse`, `fallback` or `log`. |
| `Domain\Syntax\SourceSyntax` | The grammar of every value; `hashOf()`, the source of an inline element. |
| `Domain\Syntax\ProtectionSyntax` | The values of each protection, what adding to one means, the text of its header. |
| `Domain\ValueObject\Policy` | Directives and values, checked; the header with the tokens of a request. |
| `Domain\ValueObject\PermissionsPolicy` | The value of `Permissions-Policy`. |
| `Domain\ValueObject\ViolationReport` | A violation as kept; its fingerprint. |
| `Domain\ValueObject\StoredReport` | A violation with its count and times. |
| `Domain\Contract\ReportRepository` | Where the violations are kept. |
| `Domain\Exception\InvalidPolicy` | A directive, value or header value refused. |

## Application

| Type | Role |
|---|---|
| `Application\PolicyRegistry` | The named policies of the configuration. |
| `Application\PolicyDefinition` | A named policy, with `'strict-dynamic'` and reporting. |
| `Application\PolicyOverride` | What a route, controller or service changes. |
| `Application\Attribute\ContentSecurityPolicy` | The policy of a controller. |
| `Application\ProtectionOverride` | What a route, controller or service changes in any protection. |
| `Application\Attribute\BrowserSecurity` | The protections of a controller. |
| `Application\UserContentPolicy` | The strict headers of a file someone uploaded, and `apply()`. |
| `Application\Attribute\UserContent` | The policy of user content on the responses of a controller. |
| `Application\BrowserSecurityContext` | Overrides of the protections from code. |
| `Application\ResolvedHeaders` | The headers of one response. |
| `Application\CookieSigner` | Signs and verifies the value of a cookie. |
| `Application\RequestState` | The overrides, nonces, hashes and headers of a request. |
| `Application\ContentSecurityPolicyContext` | Nonces, hashes and overrides from code. |
| `Application\HeaderBuilder` | The policy headers of a response. |
| `Application\SecurityHeaders` | The neighbouring headers, by path and with the overrides of a response. |
| `Application\ReportParser` | Reads both report formats. |
| `Application\ReportReceiver` | Receives a body: type, size, throttle, reports, store, event. |
| `Application\Receipt` | What a body ended in, with its status. |
| `Application\Event\ViolationReported` | A new violation. |
| `Application\Contract\ReportThrottle` | How many bodies a client may post. |
| `Application\Contract\ReportDeduplicator` | Whether a violation was announced within the window. |
| `Application\Contract\ClientAddressAnonymizer` | The client's address as kept. |
| `Application\Exception\UnknownPolicy` | A policy name not declared. |
| `Application\Exception\InvalidReport` | A body that cannot be read. |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Http\ContentSecurityPolicyListener` | Reads the overrides of the policy, writes its headers. |
| `Infrastructure\Http\SecurityHeadersListener` | Reads the overrides of the protections, writes the neighbouring headers. |
| `Infrastructure\Http\HttpsRedirectListener` | Sends plain requests to HTTPS. |
| `Infrastructure\Http\SignedCookiesListener` | Signs and checks the named cookies. |
| `Infrastructure\Http\ExternalRedirectListener` | Stops the redirects to a host not allowed. |
| `Infrastructure\Http\UserContentListener` | Applies the policy of user content by route default or attribute. |
| `Infrastructure\Http\ReportEndpoint` | The address of the reports of a response. |
| `Infrastructure\Http\ReportController` | The endpoint of the reports. |
| `Infrastructure\Twig\CspExtension` | `csp_nonce()` and `csp_meta()`. |
| `Infrastructure\Twig\NoncedImportMapRuntime` | `importmap()` with the script nonce. |
| `Infrastructure\RateLimiter\RateLimiterThrottle` | The throttle on `symfony/rate-limiter`. |
| `Infrastructure\RateLimit\LimiterReportThrottle` | The throttle on a limiter of phil/rate-limit-bundle. |
| `Infrastructure\Cache\CacheDeduplicator` | The window of deduplication in a PSR-6 pool. |
| `Infrastructure\IpAnonymizer\TruncatedClientAddress` | The address through `phil/ip-anonymizer`. |
| `Infrastructure\Doctrine\DbalReportRepository` | The violations in `phil_csp_report`. |
| `Infrastructure\Doctrine\ReportTable` | The table. |
| `Infrastructure\Doctrine\ReportSchemaListener` | The table in the schema of the ORM. |
| `Infrastructure\Console\PurgeReportsCommand` | `phil:csp:purge-reports`. |
| `Infrastructure\Profiler\CspDataCollector` | The profiler panel. |
| `PhilBrowserSecurityBundle` | The configuration and the wiring; it adds the `RateLimitGuard` of `phil/rate-limit`. |

## Services

| Id | Public |
|---|---|
| `Phil\BrowserSecurityBundle\Application\HeaderBuilder` | yes |
| `Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext` | yes |
| `Phil\BrowserSecurityBundle\Application\BrowserSecurityContext` | yes |
| `Phil\BrowserSecurityBundle\Application\UserContentPolicy` | yes |
| `Phil\BrowserSecurityBundle\Application\ReportReceiver` | yes, with reporting |
| `Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository` | yes, with the store |
| `phil_browser_security.report_limiter` | no; the rate limiter factory built by the bundle |
| `phil_browser_security.report_limit` | no; the limiter `phil_browser_security.report` of phil/rate-limit-bundle, with that bundle |
| `phil_browser_security.importmap_runtime` | no; the runtime of `importmap()` |

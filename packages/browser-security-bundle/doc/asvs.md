# ASVS 5.0 mapping

The requirements of [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) the bundle meets, with the
test that shows each one, and those it leaves to the application. The guide of the monorepo,
[ASVS 5.0: what the application keeps](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md), gathers what every package leaves.

## Met by the bundle

| Requirement | Level | How | Shown by |
|---|---|---|---|
| 3.4.1 HSTS on every response, one year or more, subdomains from L2 | 1 | `Strict-Transport-Security: max-age=31536000; includeSubDomains` by default; a shorter `max_age` for a rollout is sent as configured, never with `preload` | `PhilBrowserSecurityBundleTest::testStrictTransportSecurityLastsAYearByDefault` |
| 3.4.3 A policy with `object-src 'none'` and `base-uri 'none'`, an allowlist or nonces | 2 | The default policy, sent whole on every page; nonces per response with `csp_nonce()` | `HeaderBuilderTest::testTheDefaultPolicyIsSentWhole` |
| 3.4.4 `X-Content-Type-Options: nosniff` on every response | 2 | A header of every response | `PhilBrowserSecurityBundleTest`, the default headers |
| 3.4.5 A referrer policy | 2 | `Referrer-Policy: strict-origin-when-cross-origin` | `PhilBrowserSecurityBundleTest`, the default headers |
| 3.4.6 `frame-ancestors` on every response | 2 | `frame-ancestors 'self'` in the default policy, beside `X-Frame-Options` | `HeaderBuilderTest::testTheDefaultPolicyIsSentWhole` |
| 3.4.7 A location to report violations | 3 | `report-uri` and `report-to`, to the route of the bundle or to `public_origin` | `BrowserSecurityBundleTest::testAForgedHostNeverChoosesWhereTheReportsGo` |
| 3.2.1 Content not rendered in the wrong context | 1 | The headers of user content: a sandboxed policy, `attachment` for every type that can run | [User content](user-content.md) and its tests |
| 5.4.1, 5.4.2 A file name the server chooses, encoded | 2 | The name given to the user-content headers, cleaned of paths, controls and quotes, written with `filename*` | [User content](user-content.md) and its tests |
| 3.7.2 Redirects to another host only on an allowlist | 2 | The external redirect check, its allowed hosts and three actions | [External redirects](redirects.md) and its tests |
| 4.1.2 Only pages redirected from HTTP to HTTPS | 2 | The redirect by route, `exclude_paths` and the `https-redirect` override | [HTTPS](https.md) and its tests |

## Left to the application

| Requirement | Level | Why it is the application's |
|---|---|---|
| 3.1.1 The browser features the application expects, documented | 3 | Its own documentation; this bundle's [headers](headers.md) list what it sends |
| 3.3.1 to 3.3.4 `Secure`, `SameSite`, `__Host-`, `HttpOnly` cookies | 1, 2 | Set by `framework.session` and by each cookie the application writes; [signed cookies](cookies.md) sign values, they do not set attributes |
| 3.4.2 A fixed or allowlisted `Access-Control-Allow-Origin` | 1 | The CORS configuration of the application, such as a CORS bundle |
| 3.4.8 `Cross-Origin-Opener-Policy` on documents | 3 | Available as `headers.cross_origin_opener_policy`, off by default: a page that opens payment or sign-in windows must be tried first |
| 3.5.3, 3.5.8 Safe methods and `Sec-Fetch-*` checks | 1, 3 | Routes and controllers; the bundle sends headers and reads none of the request's |
| 3.6.1 Subresource Integrity for external assets | 3 | The asset pipeline; the bundle names hashes in the policy, it does not compute them for third-party files |
| 3.7.5 Behaviour on a browser without the expected features | 3 | A product decision |

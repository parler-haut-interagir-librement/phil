# Neighbouring headers

The headers that go with a content security policy, each written only when the configuration gives it a value and
only when the response does not carry it already. Every value is checked when the container is built; a page changes
any of them with an [override](overrides.md#every-other-protection).

```yaml
phil_browser_security:
    headers:
        permissions_policy:
            camera: []
            microphone: []
            geolocation: [self, 'https://maps.example.com']
            fullscreen: ['*']
        cross_origin_opener_policy: same-origin
        cross_origin_opener_policy_report_only: ~
        cross_origin_embedder_policy: credentialless
        cross_origin_embedder_policy_report_only: ~
        cross_origin_resource_policy: same-site
        referrer_policy: strict-origin-when-cross-origin
        content_type_options: nosniff
        permitted_cross_domain_policies: none
        origin_agent_cluster: '?1'
        frame_options:
            default: SAMEORIGIN
            paths:
                /embed: ~
                /admin: DENY
```

| Header | Values | Default |
|---|---|---|
| `Permissions-Policy` | origins by feature: `[]` for none, `self`, `*`, or `https://host[:port]` | none |
| `Cross-Origin-Opener-Policy` and its `-Report-Only` | `same-origin`, `same-origin-allow-popups`, `noopener-allow-popups`, `unsafe-none` | none |
| `Cross-Origin-Embedder-Policy` and its `-Report-Only` | `require-corp`, `credentialless`, `unsafe-none` | none |
| `Cross-Origin-Resource-Policy` | `same-site`, `same-origin`, `cross-origin` | `same-site` |
| `Referrer-Policy` | the eight values of the specification | `strict-origin-when-cross-origin` |
| `X-Content-Type-Options` | `nosniff` | `nosniff` |
| `X-Frame-Options` | `DENY`, `SAMEORIGIN` | `SAMEORIGIN` |
| `X-Permitted-Cross-Domain-Policies` | `none`, `master-only`, `by-content-type`, `by-ftp-filename`, `all`, `none-this-response` | `none` |
| `Origin-Agent-Cluster` | `?1`, `?0` | `?1` |
| `Integrity-Policy` and its `-Report-Only` | the destinations blocked, `script` and `style` | off |

`null` sends nothing. A feature name is lower case, digits and hyphens; any other value fails the container build.
`X-XSS-Protection` is left out on purpose: the auditor it controlled is gone from browsers, and the header could only
open holes.

`Cross-Origin-Embedder-Policy: require-corp` blocks every cross-origin resource that does not opt in: try it as
`credentialless` first, or send its `-Report-Only` form for a while.

## Frames by path

`frame_options.default` is the value of every path, and `frame_options.paths` the value by prefix, on the boundary of a
segment: `/embed` covers `/embed` and `/embed/map`, not `/embedded`. The longest prefix wins, and `null` sends nothing.
The directive `frame-ancestors` of the policy takes precedence in every browser that reads it; this header speaks to
those that do not.

## Reports

When reporting is on, the report-only forms of the cross-origin policies name the endpoint of the bundle, and both forms
of the integrity policy too. The endpoint keeps their reports with the violations of the policy, under the name of
their header, in `effective_directive`.

```http
Cross-Origin-Opener-Policy-Report-Only: same-origin; report-to="csp"
Integrity-Policy-Report-Only: blocked-destinations=(script), sources=(inline), endpoints=(csp)
Reporting-Endpoints: csp="https://www.example.com/_csp/report"
```

## Integrity

`Integrity-Policy` refuses the scripts loaded without an `integrity` attribute, and it is off by default: try
`integrity_policy_report_only` first, then turn `integrity_policy` on.

```yaml
phil_browser_security:
    headers:
        integrity_policy_report_only:
            enabled: true
            blocked_destinations: [script]
```

## Clearing the site data at logout

Symfony writes `Clear-Site-Data` with the option `clear_site_data` of a firewall's `logout`. The bundle registers that
same listener for every firewall at once, with what the browser forgets:

```yaml
phil_browser_security:
    clear_site_data:
        on_logout: [cookies, storage]   # [] for nothing
```

The values are `*`, `cache`, `cookies`, `storage`, `clientHints`, `executionContexts`, `prefetchCache` and
`prerenderCache`. A firewall that sets its own option keeps it. `storage` empties the local storage of the origin, an
unsent draft included, and `cache` can be slow in some browsers. A route that names `Clear-Site-Data` in an override,
after the deletion of an account for instance, sends it on any response. The browser reads this header over HTTPS only.

## What Symfony does already

Symfony sets no response header of this list on a page: the bundle writes them. For logout it reuses
`ClearSiteDataLogoutListener` from `symfony/security-http`, as above.

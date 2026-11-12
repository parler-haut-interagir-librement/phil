# phil/browser-security-bundle

**The protections a Symfony application asks of the browser, in one bundle: one configuration, adjustable page by
page.**

phil/browser-security-bundle writes the `Content-Security-Policy` header of every response from policies declared in the
configuration, directive by directive, and checked when the container is built: a source written without its quotes,
a stray `;` or an unknown directive fails the build, not a page. Each request gets its own nonces, created when a
template first asks for one: `csp_nonce('script')` and `csp_nonce('style')` in Twig, the tags of AssetMapper's
`importmap()` — through which Stimulus and Turbo load —, and the `csp-nonce` meta tag Turbo reads. A stricter policy can
run beside the enforced one as `Content-Security-Policy-Report-Only`.

Beside it come the headers a site wants on every response: `Permissions-Policy`, the three `Cross-Origin-*-Policy`
headers and the report-only forms of two of them, `Referrer-Policy`, `X-Frame-Options` by path, `nosniff`,
`X-Permitted-Cross-Domain-Policies`, `Origin-Agent-Cluster` and `Integrity-Policy`, offered off. It also redirects plain
requests to HTTPS and sends HSTS, signs the cookies it is told to, refuses a redirect to a host not allowed, and has
Symfony's listener clear the site data at logout on every firewall. A file someone uploaded is served with a policy of
its own, applied by route, by controller or by response: a sandboxed policy that loads nothing, `nosniff`, and an
attachment unless its type cannot run.

Every one of these is global in the configuration and adjustable for one page: a route default, a controller attribute
or a service adds, replaces or removes it for its own responses. The bundle builds on what Symfony does already and says
so, topic by topic.

The reports come back to an endpoint that reads both formats — `application/csp-report` and the Reporting API's
`application/reports+json` —, bounded in size and in rate, deduplicated, with the client's address never kept raw.
Each new violation dispatches an event, a DBAL table can keep them with their count, and the profiler shows the policy
of each response.

## Status

| Part | State |
|---|---|
| Policies by directive, checked at build time, named policies and inheritance | Implemented and tested |
| Overrides by route default, controller attribute and service | Implemented and tested |
| Nonces for Twig, `importmap()` and Turbo, `'strict-dynamic'`, hashes, Trusted Types | Implemented and tested |
| Enforced and report-only policies side by side | Implemented and tested |
| `Cache-Control: private` on a response whose policy lists a nonce | Implemented and tested |
| The report endpoint counted by a named limiter of phil/rate-limit-bundle | Implemented and tested |
| Report endpoint, both formats, the reports of the other policies, bounds, throttle, deduplication, anonymized address, event | Implemented and tested, fuzzed |
| DBAL store of the violations and its purge command | Implemented and tested |
| Neighbouring security headers, by path and by page, profiler panel | Implemented and tested |
| Overrides of every protection by route default, controller attribute and service | Implemented and tested |
| HTTPS redirect and HSTS, signed cookies, external redirects, `Clear-Site-Data` at logout | Implemented and tested |
| The policy of user content: strict headers for uploaded files, by route, attribute or response | Implemented and tested |
| Building the headers of a response | Benchmarked |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the route of the reports, the table. |
| [Configuration](doc/configuration.md) | Every option of `phil_browser_security`. |
| [Policies](doc/policies.md) | Directives and their values, the default policy, named policies, inheritance, hashes, Trusted Types. |
| [Overrides](doc/overrides.md) | Add, replace and remove, for the policy and every other protection: attributes, route defaults, services. |
| [Nonces](doc/nonces.md) | `csp_nonce()`, `csp_meta()`, `importmap()`, Turbo, Stimulus, `'strict-dynamic'`, caching. |
| [Deployment](doc/deployment.md) | Report-only first, then enforced, with both headers at once. |
| [Reports](doc/reports.md) | The endpoint, the two formats, the bounds, the throttle, deduplication, privacy, the event, the store. |
| [Neighbouring headers](doc/headers.md) | Every header beside the policy, frames by path, integrity, `Clear-Site-Data` at logout. |
| [HTTPS](doc/https.md) | The redirect, HSTS, a proxy, and what Symfony does already. |
| [Signed cookies](doc/cookies.md) | Signing, rotating the secret, one page. |
| [External redirects](doc/redirects.md) | The allowed hosts, the three actions, one page. |
| [User content](doc/user-content.md) | The strict headers of an uploaded file, by route, by controller or by response. |
| [Worker mode and profiler](doc/runtime.md) | What lives where during a request, `kernel.reset`, the profiler panel. |
| [Extension points](doc/extension-points.md) | The ports, services and events an application uses or replaces. |
| [Testing](doc/testing.md) | The tests, the mutation bar, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/browser-security-bundle
```

```yaml
# config/packages/phil_browser_security.yaml
phil_browser_security:
    policies:
        default:
            directives:
                default-src: ["'self'"]
                img-src: ["'self'", 'data:']
                object-src: ["'none'"]
                base-uri: ["'self'"]
                frame-ancestors: ["'self'"]
            strict_dynamic: true
    reporting:
        storage: true
```

```yaml
# config/routes/phil_browser_security.yaml
phil_browser_security:
    resource: '@PhilBrowserSecurityBundle/config/routes.php'
```

```twig
<head>
    {{ csp_meta() }}
    {{ importmap('app') }}
    <style nonce="{{ csp_nonce('style') }}">body { margin: 0 }</style>
</head>
```

## Requirements

PHP 8.5 or later with `ctype`, `mbstring`, `sodium` and `uri`, Symfony 8.2 or later, and `phil/rate-limit`. Twig,
AssetMapper, the rate limiter, a PSR-6 pool, Doctrine DBAL, the console, security-http and `phil/ip-anonymizer` are
optional, each enabling one part.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

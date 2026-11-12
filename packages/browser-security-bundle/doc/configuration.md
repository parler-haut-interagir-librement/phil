# Configuration

Every option of `phil_browser_security`, with its default.

```yaml
phil_browser_security:
    # The named policy sent as Content-Security-Policy, or ~ to enforce none.
    enforce: default
    # The named policy sent as Content-Security-Policy-Report-Only, or ~.
    report_only: ~

    # The named policies; without any, "default" is the default policy.
    policies:
        default:
            extends: ~                # a policy whose directives this one takes
            directives:               # sources by directive; true for a directive without value
                default-src: ["'self'"]
            strict_dynamic: false     # 'strict-dynamic' next to the nonces and hashes of script-src
            report: true              # report-uri and report-to on this policy
            inline:                   # inline code allowed by the SHA-256 of its exact content
                script: []
                style: []
            trusted_types:
                enabled: false        # require-trusted-types-for 'script'
                policies: []          # the Trusted Types policies the page may create
                allow_duplicates: false

    # Prefixes of the paths whose responses get no header.
    exclude_paths: ['/_wdt', '/_profiler']

    # The script nonce on the tags of importmap().
    importmap: true

    reporting:
        enabled: true
        group: csp                    # the endpoint's name in Reporting-Endpoints and report-to
        uri: ~                        # an https address or a path, instead of the route phil_csp_report
        max_bytes: 65536              # 512 to 1,048,576
        max_reports: 20               # reports read from one body, 1 to 100
        rate_limit:
            enabled: true
            limit: 60                 # bodies per client
            interval: '1 minute'
            cache_pool: cache.app     # the storage of the limiter built by the bundle; a shared one in prod
            limiter: ~                # the service id of a rate limiter factory of the application
            single_server: false      # accepts a pool local to the server in prod
        deduplication:
            enabled: true
            window: 3600              # seconds during which a violation reported again dispatches no event
            cache_pool: cache.app
        client_address:
            enabled: true             # with phil/ip-anonymizer installed
            ipv4_prefix: 24
            ipv6_prefix: 48
        storage:
            enabled: false
            connection: default       # the DBAL connection of phil_csp_report
            capacity: 10000           # rows at most; a new violation is not kept beyond
            retention: 30             # days, the default of phil:csp:purge-reports

    headers:
        permissions_policy: {}        # origins by feature: camera: [] gives camera=()
        cross_origin_opener_policy: ~ # same-origin, same-origin-allow-popups, noopener-allow-popups, unsafe-none
        cross_origin_opener_policy_report_only: ~
        cross_origin_embedder_policy: ~ # require-corp, credentialless, unsafe-none
        cross_origin_embedder_policy_report_only: ~
        cross_origin_resource_policy: same-site # same-origin, cross-origin
        referrer_policy: strict-origin-when-cross-origin
        content_type_options: nosniff
        permitted_cross_domain_policies: none
        origin_agent_cluster: '?1'    # ?0 for one agent cluster per site
        frame_options:
            default: SAMEORIGIN       # DENY, or ~ to send nothing
            paths: {}                 # the value by prefix of a path; ~ sends nothing
        integrity_policy:
            enabled: false
            blocked_destinations: [script]
        integrity_policy_report_only:
            enabled: false
            blocked_destinations: [script]

    https:
        redirect:
            enabled: ~                # null follows kernel.debug: on when it is off
            status: 308               # 301, 302, 307 or 308
            port: 443
            exclude_paths: []         # prefixes of the paths served over both
        strict_transport_security:
            enabled: ~                # null follows kernel.debug: on when it is off
            max_age: 31536000
            include_subdomains: false
            preload: false            # needs include_subdomains and a year or more

    signed_cookies:
        names: []                     # the cookies signed on every page
        secret: '%kernel.secret%'
        previous_secrets: []          # no longer used to sign, still accepted

    external_redirects:
        enabled: true
        action: refuse                # refuse, fallback or log
        fallback: /                   # the path a refused redirect goes to with "fallback"
        allow: []                     # hosts, or domains written with a leading dot

    clear_site_data:
        on_logout: [cookies, storage] # what the browser forgets at logout, on every firewall; [] for nothing

    user_content:                     # the headers of a file someone uploaded, see User content
        directives:                   # its own policy; default-src 'none', base-uri, form-action, frame-ancestors 'none', sandbox
            default-src: ["'none'"]
            sandbox: true
        inline_types: [image/png, image/jpeg, text/plain] # the media types shown in the browser
        cross_origin_resource_policy: same-origin # same-site, cross-origin

    # The service id of the PSR-20 clock.
    clock: clock
```

## What is checked when the container is built

- every directive and every value, with the grammar of [Policies](policies.md#values);
- `extends`, which names a declared policy, without a cycle;
- `enforce` and `report_only`, which name declared policies;
- `reporting.uri`, an `https` address or a path, without space, quote, comma nor semicolon;
- `reporting.group`, 1 to 64 letters, digits, `-` and `_`;
- every neighbouring header, against the values its specification lists;
- the frame options by path, the names of the signed cookies, the allowed hosts, the fallback and the directives of
  `Clear-Site-Data`;
- `preload`, which needs `include_subdomains` and a year or more;
- the policy of user content, its inline types — never one that can run as a page or a script — and its
  `Cross-Origin-Resource-Policy`;
- in the `prod` environment, the `cache_pool` of the throttle, which the guard of `phil/rate-limit` refuses when it is
  local to each server, unless `single_server` is set.

A failure throws `InvalidConfigurationException`, with the path of the option and the reason.

## Keys

The names of the policies and of the directives keep their hyphens: `default-src`, not `default_src`.

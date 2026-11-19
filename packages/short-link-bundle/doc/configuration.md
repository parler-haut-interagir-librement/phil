# Configuration

Every option of `phil_short_link`, with its default:

```yaml
# config/packages/phil_short_link.yaml
phil_short_link:
    enabled: true
    # The domains that serve the short links, at least one, in order.
    domains:
        - { host: go.example.com, locale: ~, scheme: https }
        - { host: lien.example.fr, locale: fr }
    # Where the root of a domain sends; none answers it with a 404.
    home_url: ~
    # localhost and the names under .localhost as domains and targets, in development and tests.
    allow_localhost: false
    entity_manager: doctrine.orm.entity_manager
    code:
        alphabet: abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789
        min_length: 6
        max_number: 1099511627775
        blocklist: []
        default_blocklist: true
        custom: true
    targets:
        allowed_hosts: []
        any_host: false
        check: false
    rate_limit:
        enabled: true
        limit: 30
        interval: 1 hour
        cache_pool: cache.app
        limiter: ~
        single_server: false
    qr_code:
        level: M
        quiet_zone: 4
        dark: '#000000'
        light: '#ffffff'
        size: 256
        scale: 8
    admin:
        enabled: true
        role: ROLE_ADMIN
        layout: '@PhilShortLink/admin/layout.html.twig'
        timezone: UTC
    visitors:
        link_previews: []
        robots: []
    analytics:
        site: ~
        event: short_link_visit
```

| Option | What it does |
|---|---|
| `domains` | The short link domains. A host name alone — no port, no path, no IP address —, read by phil/url: in lower case, an international name in its ASCII form. `locale` limits a domain to the links of that locale; `scheme` is the one written in the links' addresses. |
| `home_url` | An absolute `http` or `https` URL the root of every domain sends to with a `302`. |
| `allow_localhost` | Accepts `localhost` and the names under `.localhost`, for development and tests only. |
| `entity_manager` | The entity manager that keeps the links. |
| `code.alphabet` | The characters of the codes, each once, among letters, digits, `-`, `.`, `_` and `~`, at least three. |
| `code.min_length` | The shortest drawn code, from 0 to 255. |
| `code.max_number` | The largest number a drawn code writes, 1,000,000 or more: more numbers mean fewer collisions and longer codes. |
| `code.blocklist` | Words a code avoids, added to the default list. |
| `code.default_blocklist` | Keeps the default list of rude words in English and French. |
| `code.custom` | Lets an author choose a code instead of drawing one. |
| `targets.allowed_hosts` | The hosts a link may send to. Empty by default: no link can be created until the application names them, or allows any host. |
| `targets.any_host` | Lets a link send to any host, the short link domains excepted. |
| `targets.check` | Reads each target through phil/safe-http before saving it. |
| `rate_limit.*` | The links one author may create in a window, on a sliding window kept in `cache_pool`, or on the rate limiter factory named by `limiter`. In the `prod` environment, a pool local to each server is refused unless `single_server` says the application runs on one server. |
| `qr_code.*` | The error correction level, the quiet zone in modules, the colours as `#rrggbb`, the SVG size in pixels and the PNG scale in pixels a module. |
| `admin.*` | The administration: the role that opens it, the layout its pages extend, the time zone its dates are written in. |
| `visitors.link_previews` | Pieces of a `User-Agent` that name a link preview of a messaging service or a social network, matched without case, added to the known ones. |
| `visitors.robots` | Pieces of a `User-Agent` that name a crawler, a script or a monitor, matched without case, added to the known ones. |
| `analytics.site` | The phil/analytics-bundle site the visits of people count for; none counts nothing. |
| `analytics.event` | The server event, declared in the site's `server_events`. |

A configuration that names a package missing from the application — `targets.check` without phil/safe-http,
`analytics.site` without phil/analytics-bundle, the administration without Twig, the forms or the CSRF tokens — fails
the container build with a message that says which.

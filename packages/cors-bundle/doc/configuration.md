# Configuration

The bundle's configuration lives under `phil_cors`:

```yaml
# config/packages/phil_cors.yaml
phil_cors:
    enabled: true
    paths:
        '^/api/admin/':
            allow_origins: ['https://admin.example.com']
            allow_methods: [GET, POST, PUT, PATCH, DELETE]
            allow_headers: [Content-Type, Authorization, X-Requested-With]
            expose_headers: [ETag, Location]
            allow_credentials: true
            max_age: 3600
        '^/api/':
            allow_origins: ['https://app.example.com', 'http://localhost:5173']
            allow_origin_patterns: ['^https://[a-z0-9-]+\.preview\.example\.com$']
            allow_methods: [GET, POST]
            allow_headers: [Content-Type]
        '^/feed\.json$':
            allow_origins: '*'
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the bundle's services; `false` registers none. |
| `paths` | `{}` | Regular expressions on the path of the request, each with its policy. |

## Paths

Each key of `paths` is a regular expression on the path of the request, its path info without the query string, written
without delimiters, such as `^/api/`. The bundle reads them in the order of the configuration and applies the policy of
the first that matches: put the narrower paths first. A path that no key matches is left alone: no header is added,
and a preflight goes on to the router.

Each path takes these options:

| Option | Default | What it does |
|---|---|---|
| `allow_origins` | `[]` | Exact origins, such as `https://app.example.com`, or `*` alone for any origin. One string is read as a list of one. |
| `allow_origin_patterns` | `[]` | Regular expressions on the whole origin, anchored by `^` and `$`, written without delimiters. |
| `allow_methods` | `[GET, HEAD, POST]` | The methods a cross-origin request may use. |
| `allow_headers` | `[]` | The request headers it may send, or `*` alone for any. |
| `expose_headers` | `[]` | The response headers its page may read besides the safelisted ones, or `*` alone for any. |
| `allow_credentials` | `false` | Whether cookies, TLS client certificates and HTTP authentication travel with the request. |
| `max_age` | `null` | The seconds a browser keeps the answer to a preflight, from 0 to 86400; `null` sends no `Access-Control-Max-Age`, and the browser keeps it for 5 seconds. |
| `allow_private_network` | `false` | Whether a public page may reach this server on a private network, for the browsers that ask with `Access-Control-Request-Private-Network`. |

Each path needs at least one origin, in `allow_origins` or `allow_origin_patterns`, and one method.

### Origins

An origin is what the browser sends in the `Origin` header: a scheme, a host and a port when it is not the scheme's
default, such as `https://app.example.com` or `http://localhost:5173`. The bundle compares it byte for byte with the
exact origins, written in lowercase when the configuration has capitals. Other schemes are origins too, such as
`capacitor://localhost` for a mobile application.

A pattern matches the whole origin: it starts with `^` and ends with `$`, so that `https://app.example.com.evil.test`
or `https://evilexample.com` never pass for an allowed origin. Escape the dots: `^https://[a-z0-9-]+\.example\.com$`.

### Headers

`allow_headers` lists the headers a request may send besides the ones every browser may send without asking
(`Accept`, `Accept-Language`, `Content-Language`, and `Content-Type` with a form or text value). Names are compared
without case. With `*`, the answer to a preflight names the headers the browser asked for, `Authorization` included,
rather than sending `*`, which `Authorization` does not match.

### Using environment variables

Each value of a list may come from an environment variable, an origin or a pattern per variable. A pattern lets one
variable allow several origins:

```yaml
phil_cors:
    paths:
        '^/api/':
            allow_origins: ['%env(FRONT_ORIGIN)%']
            allow_origin_patterns: ['%env(CORS_ALLOW_ORIGIN)%']
```

```sh
# .env
FRONT_ORIGIN=https://app.example.com
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

A whole list from one variable, such as `%env(csv:CORS_ALLOW_ORIGIN)%`, is not supported: name one variable per value.

A path whose options read the environment is checked when the application first builds the CORS services, on its first
request, rather than when the container compiles: the variables are known only then. The checks and their messages are
the same, raised as `Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy`.

## What stops the build

Without environment variables, the configuration is checked when the container compiles: a mistake stops
`cache:clear` or `cache:warmup` with an `InvalidConfigurationException` that names the path and the problem, and never
reaches a request.

| Refused | Why |
|---|---|
| `*` in `allow_origins`, `allow_headers` or `expose_headers` with `allow_credentials: true` | Browsers refuse a wildcard with credentials; list the values. |
| `*` beside other values | `*` stands alone. |
| `*` in `allow_methods` | List the methods by name. |
| The origin `null` | Sandboxed frames, local files and some redirects send it: any page can claim it. |
| An origin with a path, a trailing slash or no scheme | An `Origin` header never has one. |
| An origin with its scheme's default port, such as `https://a.test:443`, or a port above 65535 | Browsers never send one. |
| An origin pattern without `^` at the start or `$` at the end | Another host could start or end with an allowed one. |
| A path or origin pattern that does not compile | It would match nothing, or fail on each request. |
| A method or header name that is not an HTTP token | It cannot be sent in a header. |
| A path without an origin, or without a method | It would allow nothing. |
| A `max_age` below 0 or above 86400 | Browsers cap it, Chromium at 7200 seconds, Firefox at 86400. |

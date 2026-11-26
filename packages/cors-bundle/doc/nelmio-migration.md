# Coming from NelmioCorsBundle

The options of `nelmio_cors` map to those of `phil_cors` as follows. The policy of a path stands alone: there is no
`defaults` section to merge, so each path lists what it allows.

| `nelmio_cors` | `phil_cors` |
|---|---|
| `paths` with `^/api/` keys | `paths`, the same keys: regular expressions on the path, the first match wins |
| `allow_origin` | `allow_origins` for exact origins, `allow_origin_patterns` for regular expressions |
| `origin_regex: true` | Move the expressions to `allow_origin_patterns`, each anchored by `^` and `$` |
| `allow_methods` | `allow_methods`; `GET`, `HEAD` and `POST` by default |
| `allow_headers` | `allow_headers` |
| `expose_headers` | `expose_headers` |
| `allow_credentials` | `allow_credentials` |
| `max_age` | `max_age`, or `null` to send none |
| `allow_private_network` | `allow_private_network` |
| `hosts` | Not an option: alias `PolicyResolver` to a service that reads the host, as [Extension points](extension-points.md) shows |
| `forced_allow_origin_value` | Not an option: the bundle always answers the request's origin, or `*` for any origin without credentials |
| `skip_same_as_origin` | Not needed: a same-origin request gets the same headers, which browsers ignore |

The recipe's environment variable `CORS_ALLOW_ORIGIN` keeps its meaning, a regular expression: give it to
`allow_origin_patterns`.

```yaml
# before
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: [GET, OPTIONS, POST, PUT, PATCH, DELETE]
        allow_headers: [Content-Type, Authorization]
        expose_headers: [Link]
        max_age: 3600
    paths:
        '^/': null
```

```yaml
# after
phil_cors:
    paths:
        '^/':
            allow_origin_patterns: ['%env(CORS_ALLOW_ORIGIN)%']
            allow_methods: [GET, POST, PUT, PATCH, DELETE]
            allow_headers: [Content-Type, Authorization]
            expose_headers: [Link]
            max_age: 3600
```

`OPTIONS` needs no listing: the bundle answers the preflight itself.

Some configurations the other bundle accepts stop the build here: `*` with `allow_credentials`, an origin pattern that
is not anchored, the origin `null`, and an origin with a path or a trailing slash. [Configuration](configuration.md#what-stops-the-build)
lists them with their reasons.

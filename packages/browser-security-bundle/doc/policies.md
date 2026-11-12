# Policies

A policy is a list of directives, each with its values, written in the order they are declared:

```yaml
phil_browser_security:
    policies:
        default:
            directives:
                default-src: ["'self'"]
                script-src: ["'self'"]
                img-src: ["'self'", 'data:', 'https://images.example.com']
                object-src: ["'none'"]
                frame-ancestors: ["'self'"]
                upgrade-insecure-requests: true
```

```http
Content-Security-Policy: default-src 'self'; script-src 'self'; img-src 'self' data: https://images.example.com; object-src 'none'; frame-ancestors 'self'; upgrade-insecure-requests
```

## Directives

| Kind | Directives | Values |
|---|---|---|
| Fetch, falling back on `default-src` | `script-src`, `script-src-elem`, `script-src-attr`, `style-src`, `style-src-elem`, `style-src-attr`, `img-src`, `font-src`, `connect-src`, `media-src`, `object-src`, `frame-src`, `child-src`, `worker-src`, `manifest-src` | sources |
| Default | `default-src` | sources |
| Document and navigation | `frame-ancestors`, `form-action`, `base-uri` | locations: no nonce, hash nor keyword that loosens scripts |
| Flags | `upgrade-insecure-requests` | `true` |
| Sandbox | `sandbox` | `true`, or its `allow-*` flags |
| Trusted Types | `require-trusted-types-for`, `trusted-types` | `'script'`; policy names, `'none'`, `'allow-duplicates'`, `*` |

`report-uri` and `report-to` are not declared: the bundle writes them from the [reporting](reports.md)
configuration. A directive given `false` is left out — handy to remove one from a parent policy.

## Values

| Value | Example |
|---|---|
| A quoted keyword | `'self'`, `'none'`, `'unsafe-inline'`, `'unsafe-eval'`, `'strict-dynamic'`, `'unsafe-hashes'`, `'report-sample'`, `'wasm-unsafe-eval'`, `'inline-speculation-rules'`, `'unsafe-allow-redirects'` |
| A nonce or a hash | `'nonce-…'`, `'sha256-…'`, `'sha384-…'`, `'sha512-…'` |
| A scheme | `data:`, `blob:`, `https:` |
| A host | `cdn.example.com`, `*.example.com`, `https://api.example.com:8443/v1/` |
| Everything | `*` |

A value is 1,024 bytes at most, and never holds a space, a `;`, a `,` nor a stray quote: no value can add a directive
or a policy of its own. A keyword written without its quotes — `self`, `none`, `nonce-abc` — is refused with a hint,
since a browser would read it as a host. A source directive given no value is `'none'`, and `'none'` stands alone.

## The default policy

Without any policy in the configuration, `default` is:

```text
default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self';
connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self';
upgrade-insecure-requests
```

Everything comes from the site itself; nothing is embedded as an object; `<base>` and forms stay on the site; only the
site frames its pages; requests go over HTTPS. The nonces of each page are added to `script-src` and `style-src`.

## Named policies

A page that needs another policy names it. A preview of untrusted CSS in a sandboxed `<iframe>` is the typical case:

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"], object-src: ["'none'"] }
        preview:
            directives:
                default-src: ["'none'"]
                style-src: ["'unsafe-inline'"]
                img-src: ['data:']
                frame-ancestors: ["'self'"]
                sandbox: true
            report: false
```

A controller or a route then selects it, see [Overrides](overrides.md).

## Inheritance

`extends` takes the directives of another policy and replaces those the policy declares:

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"], img-src: ["'self'"] }
        gallery:
            extends: default
            directives: { img-src: ["'self'", 'https://images.example.com'] }
```

`gallery` is `default-src 'self'; img-src 'self' https://images.example.com`. A policy may extend one that extends
another; a cycle fails the build. `strict_dynamic`, `report`, `inline` and `trusted_types` are the policy's own.

## Hashes of inline code

The inline code a policy knows in advance is allowed by its SHA-256, computed when the container is built:

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"] }
            inline:
                script: ["document.documentElement.classList.add('js')"]
```

The content must be exact, whitespace included. A hash may also be written as a source, `'sha256-…'`, and a service
allows inline code written at run time through `ContentSecurityPolicyContext::allowInline()`.

## Trusted Types

```yaml
phil_browser_security:
    policies:
        default:
            trusted_types:
                enabled: true
                policies: [app, dompurify]
                allow_duplicates: false
```

adds `require-trusted-types-for 'script'; trusted-types app dompurify`: the DOM sinks then take only the objects
those policies create. Without policies, no policy may be created at all.

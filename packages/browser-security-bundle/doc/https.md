# HTTPS

## What Symfony does already

`schemes: [https]` on a route and `requires_channel: https` on an access rule send a plain request to HTTPS; they stay
the right tools for one route or one area, and keep working beside the bundle. Neither writes the rule for a whole site
nor sends `Strict-Transport-Security`, which are the two things the bundle adds. The address of the request comes from
`Request::isSecure()`, so `framework.trusted_proxies` applies.

## The redirect

```yaml
phil_browser_security:
    https:
        redirect:
            enabled: ~            # null follows kernel.debug: on when it is off
            status: 308           # 301, 302, 307 or 308
            port: 443
            exclude_paths: [/health]
```

A plain request is redirected as soon as its route is known, and before the firewall reads anything of it. Prefixes of
`exclude_paths` are served over both, for a health check that a load balancer asks over HTTP. An address that matches no
route is answered 404 before the redirect has a say.

## Strict-Transport-Security

```yaml
phil_browser_security:
    https:
        strict_transport_security:
            enabled: ~            # null follows kernel.debug: on when it is off
            max_age: 31536000
            include_subdomains: false
            preload: false
```

The header goes on responses to HTTPS requests only. `preload` needs `include_subdomains` and a `max_age` of a year or
more: anything else fails the build. A browser remembers this header for `max_age` seconds, so raise it in steps on a
site that has never sent it, and turn on `include_subdomains` only when every subdomain answers over HTTPS.

## One page

The `https-redirect` name acts before the controller is known, so only a route can change it:

```yaml
legacy_callback:
    path: /callback
    controller: App\Controller\CallbackController
    defaults:
        _browser_security:
            remove: [https-redirect]     # or replace: { https-redirect: true } to force it when it is off
```

## Behind a proxy

An application behind a proxy or a load balancer that ends TLS must declare it in `framework.trusted_proxies`, with
`trusted_headers` listing `x-forwarded-proto`. Without it every request looks plain, and the redirect sends the browser
in a loop.

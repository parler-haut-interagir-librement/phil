# External redirects

A response that sends the browser to another host is stopped unless that host is allowed: an address taken from the
request cannot become an open redirect.

```yaml
phil_browser_security:
    external_redirects:
        enabled: true
        action: refuse         # refuse, fallback or log
        fallback: /            # the path a refused redirect goes to with "fallback"
        allow:
            - pay.example.com  # one host
            - .example.net     # that domain's subdomains
```

| Action | Effect |
|---|---|
| `refuse` | The response becomes `403 Forbidden`. |
| `fallback` | The response becomes a redirect to `fallback`, a path of the site. |
| `log` | The response goes out, and a warning is logged. |

The target is resolved by `Uri\WhatWg\Url` as a browser resolves it, so `//evil.example`, `/\evil.example` and a stray
tab are seen for what they are. Only `http` and `https` targets can pass; the same host as the request always does. A
hosted payment page or an identity provider goes in `allow`, or in the route that redirects to it. Only the host is
logged: an address can carry a token.

## What Symfony does already

`HttpUtils` keeps the target path of a login on the site with its `domain` pattern, and nothing else in Symfony looks at
a `Location` header.

## One page

```php
use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;

#[BrowserSecurity(add: ['external-redirects' => ['checkout.example.org']])]
public function pay(): Response
```

`remove: ['external-redirects']` turns the check off for that page.

# Signed cookies

A signed cookie cannot be changed by the browser: the application reads and writes plain values, the bundle adds the
signature on the way out and checks it on the way in. A cookie whose signature fails is taken away from the request, as
if the browser had not sent it.

```yaml
phil_browser_security:
    signed_cookies:
        names: [theme, cart_token]
        secret: '%kernel.secret%'
        previous_secrets: []
```

The signature is an HMAC-SHA-256 of the name and the value under a key derived from the secret, so that one cookie's
value does not pass for another's. It proves who wrote the value; the browser can still read it, so nothing confidential
belongs in it.

To replace the secret, move the old one to `previous_secrets`: it still verifies, and only the new one signs. Remove it
once the longest-lived cookie has expired.

## What Symfony does already

`UriSigner` signs addresses, and the remember-me cookie carries its own signature; Symfony signs no other cookie. Leave
the session cookie out of `names`: its value is already a random identifier, and the session listener reads it before
this one runs.

## One page

Cookies are checked as soon as the route is known, so only a route can change the list:

```yaml
beta:
    path: /beta
    controller: App\Controller\BetaController
    defaults:
        _browser_security:
            add: { signed-cookies: [beta_flag] }
```

`add`, `replace` and `remove: [signed-cookies]` all apply. A listener of `kernel.request` above priority 30 sees the
signed value.

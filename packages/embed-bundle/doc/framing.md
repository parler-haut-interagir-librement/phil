# Framing

Who may put a page in a frame is the response's `frame-ancestors` directive, in its content security policy; the older
`X-Frame-Options` header says only `DENY` or `SAMEORIGIN`, and a browser that sees both obeys `frame-ancestors`.

| The request | `frame-ancestors` | `X-Frame-Options` |
|---|---|---|
| A valid token | the token's origin | dropped |
| Another token | `'none'` | kept |
| No token | the site's own | the site's own |

## With phil/browser-security-bundle

When `phil/browser-security-bundle` is enabled, the framing goes through its `BrowserSecurityContext`: an override of
the response's protections replaces the sources of `frame-ancestors` in the site's policy, and removes
`X-Frame-Options` when the origin is allowed. The rest of the policy stays the site's. The script's route allows itself
to be loaded by other sites the same way, with `Cross-Origin-Resource-Policy: cross-origin`.

## Without it

`HeaderFraming` keeps the decision on the request, and writes a `Content-Security-Policy` header of its own with
`frame-ancestors` alone, beside any other the application sends: a browser enforces every policy it receives, so an
application policy that says `frame-ancestors 'self'` still forbids the frame. Without a policy of its own, or with
`phil/browser-security-bundle`, there is nothing to reconcile.

`framing: header` keeps the bundle's header even when `phil/browser-security-bundle` is enabled; `framing:
browser_security` requires it.

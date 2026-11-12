# Return to

A link to the start path can say where the sign-in should land:

```twig
<a href="{{ path('_oidc_login_start_main', {return_to: app.request.requestUri}) }}">Sign in</a>
```

The parameter stays on the application. It is read when the authorization request is built, its validated value goes
into the session, and the provider never sees it.

## What is accepted

`DeclaredOriginsReturnToPolicy` accepts:

- a path of the application, such as `/account?tab=a`; the fragment is dropped;
- an address on an origin listed in `return_to.origins`.

An origin is a bare `https://host[:port]`, or `http` on a loopback host. The candidate is read with the WHATWG parser,
the one browsers follow, and the answer is rebuilt from the parsed address. Anything else is discarded: backslashes,
control characters, embedded credentials, scheme-relative addresses, paths that normalize to `//host`, and values
beyond 2048 bytes.

## What happens when it is refused

The candidate is dropped without an error, a target remembered earlier is forgotten, and the sign-in lands on
`paths.landing`. A `ReturnToDiscarded` event reports it, without the refused value.

Symfony applies one more check when it redirects: an absolute address must be on the host of the request or within the
session cookie domain, or the browser goes to `/`. An origin the session does not reach would not find the user signed
in anyway.

A protected page reached while signed out needs no parameter: the firewall remembers it as usual.

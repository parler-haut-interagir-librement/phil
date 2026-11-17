# Security

The checks of `phil/lti` — signatures by `RS256` only, issuers and audiences, times, nonces and states used once, the
deployment, the scopes — apply as its security page describes. The bundle adds:

## Routes and firewalls

- The launch route verifies every launch with the authenticator before any code of the application runs; a refused
  launch answers 401 with the reason, never the token.
- The authorization endpoint of the platform answers only a member signed in, and only a registered client and redirect
  address; the application's `LaunchMessages` decides what the member may launch.
- The token endpoint and the services take no cookie and no session: put them in a firewall with `security: false`.
  Each call carries a bearer token of its scope; the tokens are kept by hash.
- The services read a body of 64 KiB at most and answer 404 for a context or a line item that is not the tool's.

## The page that posts a launch

`FormPostResponder` answers with `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, and a
`Content-Security-Policy` whose `form-action` is the origin it posts to, whose `frame-ancestors` are the application
and that origin, and whose `script-src` is a nonce drawn for each response.

The form posts itself through a short inline script of the template, the only script that nonce allows. The page thus
needs nothing of the application: neither its importmap nor its Stimulus application, which an override of the block
`head` would otherwise have to load. The button stays for a reader without scripts. The choice weighed three ways:

- an inline script under a nonce, kept: the page works the moment the bundle is installed, and the policy still refuses
  any other script, injected or not;
- the importmap of the application loaded by the template: it ties the page to the name of an entry point and to the
  application's scripts, and the policy would have to allow them;
- the Stimulus controller alone, as in 0.1: the page stays on its message until the application loads its importmap,
  which nothing told it to do.

The template receives the nonce as `csp_nonce`. A template of the application that extends it and adds scripts gives
them the nonce, for instance `{{ importmap('app', {nonce: csp_nonce}) }}`. The Stimulus controller `auto-submit` then
finds the form already sent, by its `data-phil-lti-submitted`, and does not post it twice. A policy the application adds
to the response, through a listener or a reverse proxy, is enforced as well: it must allow the nonce, or the button
takes over.

## Cookies in a frame

A platform often shows the tool in a frame. The browser then treats the tool's cookies as third-party cookies: after a
launch, the session of the tool keeps only with `SameSite=None; Secure`, partitioned where the browser supports it:

```yaml
framework:
    session:
        cookie_samesite: none
        cookie_secure: true
```

The login states of the bundle are kept in the table, not in a cookie: the launch itself does not depend on them.
Where the browser blocks third-party cookies, open the tool in a window (`DocumentTarget::Window`).

## Keys

The private keys are sealed at rest by the KMS, or read from the environment; they never enter the code or the
configuration files. Opened, they are RSA JSON Web Keys of 2048 to 8192 bits that `web-token/jwt-library` signs with;
a key out of these bounds is refused when it is read. Rotate them with `phil:lti:rotate-keys --if-due`.

## Outbound requests

Every request the bundle sends goes through `phil/safe-http`: the token endpoint, the Assignment and Grade Services and
the Names and Role Provisioning Service of a platform through `SafeHttpClient::send()` — HTTPS only, private and
reserved networks refused, no redirect, the `Authorization` header never sent off the origin, 20 seconds and 4 MiB at
most —, the key sets of the other side through `SafeHttpClient::get()`, 10 seconds and 256 KiB at most, read at most
once a minute per address. The HTTP client of `http_client` is wrapped by both, never called directly.

## Messages

The message `PublishScore` carries the identifier of a pending score only: the score, and the learner it names, stay in
the table until they are sent.

## What stays with the application

The firewalls and the access control of its own pages; what of a member the platform shares with each tool; the
sanitising of the HTML of content items it shows; the purge, by `phil:lti:purge`.

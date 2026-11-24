# Security

Every check of phil/saml-sp runs on each response: see its security guide for the XML attacks, the signatures,
signature wrapping, the checks of the profile, encryption and replay. The bundle adds what an application needs around
them.

## The request answered

The ID of each request is kept in the cache pool for `request_lifetime`, ten minutes by default, and travels as the
relay state; the response comes back with it. A response must answer that request — in `InResponseTo`, in the
confirmation of its assertion — for the identity provider it was sent to, once: a request is forgotten as it is answered.

## The browser

With `bind_to_browser` on, the default, the login sets a cookie, `phil_saml_sp_binding`, holding a random token whose
hash is kept with the request; the response must come back with that cookie. A response captured from another browser,
or a sign-in started by another site in the person's browser and completed elsewhere, is refused. The cookie is
`HttpOnly`, `Secure` and `SameSite=None` — the response is posted by the page of the identity provider, a cross-site
`POST` a `Lax` cookie would not follow —, scoped to `/`, and cleared once the person is signed in. A browser keeps a
`SameSite=None` cookie only over HTTPS: on plain HTTP, in development, the recipe sets `secure_cookie: false` and
`bind_to_browser: false`, and the sign-in is bound to no browser there.

## Where people go

The `target` of the login and the relay state of an unsolicited response are followed only when they are a path of the
application: one leading `/`, no `//`, no backslash, no control character, 2048 bytes at most. Anything else goes to
`default_target_path`: no redirect to another site.

## Unsolicited responses

A response that answers no request, which an identity provider sends when a person opens the application from its
portal, is refused unless `allow_unsolicited` is set for that identity provider. Such a response is bound to no browser:
a response captured in transit could sign its holder in within the minutes it is valid, and a page could make a person
sign in to an account of the attacker. Allow it only for an organisation whose portal needs it, and keep the clock skew
and `max_age` short.

## Time

The clock is the `clock` service. `clock_skew`, two minutes by default, widens every time check; `max_age`, ten minutes,
bounds the age of a response. Keep the servers synchronised: a skew larger than the setting refuses every sign-in.

## Replay

An assertion is accepted once. With a Doctrine DBAL connection, the bundle keeps the accepted assertions in the table
of `DbalReplayGuard`: the first use of an assertion is one `INSERT` on the primary key, so of two servers that receive
the same assertion at the same moment, the database lets one insert it and refuses the other. Without a connection,
`CacheReplayGuard` keeps them in `cache_pool`; it reads, then writes, so two copies posted within the same instant to
two servers could both pass, and the pool must be shared by every server.

A cache pool local to each server — APCu, an array, files, the system pool, and `cache.app`, which is on the
filesystem by default — remembers an assertion on the server that accepted it only: an attacker who captured a response
posts it again to another server behind the load balancer, which has never seen it and accepts it. So in the `prod`
environment the container fails to build when the accepted assertions would be kept in such a pool, and says what to
do:

- configure a Doctrine DBAL connection, which the bundle then takes, and create the table (see
  [Installation](installation.md#the-table-of-the-accepted-assertions));
- or name a `cache_pool` on a shared store — Redis, Memcached, a database;
- or, on a single server, set `single_server: true`.

The check reads the service definitions — the parent adapter of the pool, or its class —, follows aliases and parents,
and takes any other pool as shared. Other environments are not checked.

## The cache

The pool holds the pending requests and the fetched metadata, and the IDs of the accepted assertions without a
database. It is shared by every server of the application, so that a response reaching another server is still checked
against its request; and it is not cleared on deploy, or a pending sign-in, or an assertion still valid, would be
forgotten. A pool of its own, on a shared store, is clearer.

## Answers

A refused response answers 403 with one sentence; a logout message refused, 400; an identity provider out of reach at
the login, 503. None carries the reason, which goes to `SamlResponseRefused`. The page of the HTTP-POST binding runs no
inline script, works under a strict Content Security Policy, and is never cached; neither are the redirects.

## Keys

The private keys are read from the environment, in PEM, at the first request that needs them, never written to the
container cache. `phil:saml:check` warns before a certificate expires. The service provider never signs with SHA-1.

## Workers

The services keep nothing of a request: the key pairs and the loaded metadata are forgotten between the requests of a
worker by `kernel.reset`, and the HTTP client of phil/safe-http forgets the addresses it resolved.

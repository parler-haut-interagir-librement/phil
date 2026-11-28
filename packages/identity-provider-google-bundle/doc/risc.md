# Cross-Account Protection

Google's Cross-Account Protection (RISC) tells the applications a person signs in to with Google when something happens
to their Google account: its sessions or tokens were revoked, the account was disabled — hijacked, or made in bulk —,
enabled again, or deleted. Google posts each event as a security event token (RFC 8417), a JWT it signs, to the
receiver's endpoint (RFC 8935).

## Turning it on

```yaml
phil_identity_provider_google:
    risc:
        enabled: true
        holds_pool: cache.identity_holds   # a durable pool, see below
```

The endpoint is `POST /identity/google/risc` with the recipe's prefix, route `phil_identity_provider_google_risc`. It is
stateless, and left to anonymous access by the firewall: the token's signature is what authenticates Google.

## What the endpoint checks

1. The body is `application/secevent+jwt` — or `application/jwt` —, at most 16 KiB, read to one byte past it and no
   further, a JWT in the compact form.
2. Its signature: RS256 only, against the keys the configuration document
   `https://accounts.google.com/.well-known/risc-configuration` names, read through phil/safe-http and cached, by
   Symfony's `OidcSignatureVerifier`.
3. Its claims: the issuer `https://accounts.google.com/`, one of `risc.audiences` among its audiences, an identifier
   (`jti`), an issue time no older than `risc.max_age` and at most five minutes ahead, and an `events` object of 16
   events at most. A token without `events` — an ID token, for example — is refused.
4. A token whose identifier was acted on before is acknowledged without being acted on again.

| Answer | When |
|---|---|
| `202 Accepted` | The token was acted on, or was received before. |
| `400` with `{"err": "…", "description": "…"}` | The token is refused: `invalid_request` (not a security event token, too long, too old), `authentication_failed` (the signature), `invalid_issuer`, `invalid_audience`. |
| `503` with `Retry-After: 60` | Google's keys could not be read: Google sends the token again later. |
| `404` | `risc.enabled` is off. |

A token is remembered only once it was acted on, so that a failure halfway — the database down — lets Google's retry
finish the work.

## What each event does

| Event | What the receiver does |
|---|---|
| `sessions-revoked` | Closes the sessions of the account linked to the Google account. |
| `tokens-revoked` | Closes them too: the person withdrew the application's access at Google. |
| `token-revoked` | Names one token by its hash; the package kept none, so it names no account. |
| `account-disabled` | Closes them; with the reason `hijacking`, also holds the Google account (see below). |
| `account-enabled` | Releases the Google account. |
| `account-purged` | Closes the sessions: the Google account was deleted. |
| `account-credential-change-required` | Nothing more than the event: the application may ask the person to review their account. |
| `verification` | Nothing: it is Google's test of the stream. |
| `session-revoked` (Shared Signals Framework, CAEP) | Closes the sessions, for a provider that sends it. |

The sessions are closed by the bundle's `Sessions::closeAll()`, which also revokes the tokens the bundle issued in them
as an OpenID provider. Without the bundle's module `sessions`, nothing is closed, and the event is still dispatched.

An event about a Google account linked to no account changes nothing but a hold. An event of another type is left out,
as RFC 8417 asks. An event whose subject is not an issuer and a subject identifier — a token Google names by its hash,
which this package never kept — names no account.

After acting, the receiver dispatches one `SecurityEventReceived` per event, with the provider, the type, the account's
identifier, the reason and the sessions closed: see [Extension points](extension-points.md#the-event).

## Held accounts

A Google account disabled as hijacked is held: `GoogleSignIn` refuses it, with `external.failed`, until an
`account-enabled` event releases it. The holds are kept by `CacheSubjectHolds` in a PSR-6 pool, without expiry, under a
hash of the provider and the subject. The pool must keep what it is given — a Doctrine DBAL, PDO or Redis pool without
eviction —, not a filesystem cache a deploy clears, or a held account is let in again:

```yaml
framework:
    cache:
        pools:
            cache.identity_holds:
                adapter: cache.adapter.doctrine_dbal
```

`risc.hold_hijacked: false` turns the holds off; the sessions are still closed.

## Registering the stream at Google

Google sends events once the application registered its endpoint, with the RISC API of its Google Cloud project:

1. enable the **RISC API** in the project of the OAuth client;
2. create a service account with the role **RISC Configuration Admin**, and sign in as it to get an access token for the
   RISC API;
3. call `https://risc.googleapis.com/v1beta/stream:update` with the push delivery method, the endpoint's address and the
   events requested;
4. call `https://risc.googleapis.com/v1beta/stream:verify` with a state: a `verification` event comes back to the
   endpoint, answered `202`.

Google's guide, "Protect user accounts with Cross-Account Protection", gives each request. The package does not make
these calls: they need the service account's key, which belongs to the operator's tools, not to the application.

## Other providers

The receiver is written for any transmitter of the Shared Signals Framework. `TransmitterSettings` names the provider
whose linked identities the events are about, the issuer of its tokens, the audiences, the issuers of the subjects and
the accepted age; `TransmitterSettings::google()` is Google's. A package of another provider builds a
`SecurityEventReceiver` with its own settings and the `OidcSignatureVerifier` of its own configuration document, and
routes a `RiscController` to it.

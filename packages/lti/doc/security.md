# Security

What each check of the package protects against, and what stays with the application.

## Tokens

| Check | Against |
|---|---|
| `RS256` only, whatever the header says; the key chosen by the side, by `kid` | Tokens signed with `none`, or with HMAC and the public key as secret. |
| A key set of the registered address only | Tokens signed by a key a forger publishes. |
| `iss` and `aud` checked before the signature | Tokens of another platform or for another client reaching the key set. |
| `azp` the client when present, required when `aud` lists several | Tokens meant for another party of a shared audience. |
| `exp`, `iat` no older than ten minutes, `nbf`, by a PSR-20 clock with five seconds of leeway | Old or forged-time tokens. |
| `nonce` bound to the login state, used once | Replayed launches. |
| `state` taken once from the store, expiring after ten minutes | Launches without a login, login cross-site request forgery. |
| `jti` of a client assertion used once | Replayed assertions at the token endpoint. |
| Tokens of 64 KiB, JSON of 16 levels, key sets of 32 keys and 256 KiB at most | Resource exhaustion by large or deep input. |
| Critical header parameters and the unencoded payload (`b64`) refused | Extensions the verifier does not implement. |
| Form read by `Jwt::parse()` before `web-token/jwt-library` sees the token | A large or deep token reaching the JSON reader and the signature check of the library unbounded. |

The messages of the exceptions say which check failed; none repeats a token.

## Addresses and requests

Every address is read by `phil/url`: no credentials in an address, no IP address, no `localhost` outside development.
The key sets are read through `phil/safe-http`; the services — the token endpoint, Assignment and Grade Services, Names
and Role Provisioning Services — are called through `SafeHttpServiceClient`, over `SafeHttpClient::send()`: HTTPS only,
every address resolved and refused when it is private or reserved, no proxy of the environment, no redirect followed by
default, the `Authorization` header never sent off the origin of the address, the silence of the host, the whole
duration and the bytes of the answer bounded — 4 MiB by default. A message of a failure names the address, never a
header's value. The target of a launch must be on the tool's origin; the redirect address
of an authentication request must be one the tool registered, compared whole.

## Keys

The signatures are made and checked by `web-token/jwt-library` with `RS256` only. A key is an RSA key of 2048 to 8192
bits, with an odd public exponent of at least 3; a key of a key set that names another algorithm or another use is
skipped. A dump of a private key shows its identifier and its public half only.

## Access tokens

The platform gives an access token once and keeps only its SHA-256; a token grants only the scopes asked for that are
registered for the tool; the services check the scope of each call and that the tool is placed in the context.

## What stays with the application

- **The session of the launch.** A tool shown in a frame of the platform sets its cookies as third-party cookies:
  `SameSite=None; Secure`, partitioned where the browser supports it. The state store of the package does not depend on
  cookies, but the application's session after the launch does.
- **The member signed in.** On the platform, `LaunchIssuer::authorize()` compares the login hint with the one the
  application gives for the member signed in; the application decides what the member may launch.
- **The personal data.** The platform chooses what of the member it shares — name, email, picture —, and the tool keeps
  what it receives under its own privacy policy. A subject is opaque and stable per tool.
- **The HTML of a content item**, which the platform sanitises before it shows it.
- **The private keys**, which the application keeps out of its code and sealed at rest.
- **The purge** of expired states, nonces and access tokens in persistent stores.

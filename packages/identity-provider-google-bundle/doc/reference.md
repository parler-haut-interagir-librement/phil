# Reference

Every public type of the package, with its role.

## Domain

| Type | Role |
|---|---|
| `Domain\Risc\SecurityEventType` | The security events read, by their URI, and whether each closes the sessions. |
| `Domain\Risc\SecurityEvent` | One event: its type, the issuer and identifier of its subject, its `reason` and `state`. |
| `Domain\Risc\SecurityEventToken` | The claims of a security event token, read within bounds: issuer, identifier, issue time, audiences, events. |
| `Domain\Exception\InvalidSecurityEventToken` | A token refused, with its RFC 8935 error code. |

## Application

| Type | Role |
|---|---|
| `Application\GoogleSettings` | The client ID, the accepted Workspace domains, the profile, the prompt, the clock skew and the lifetime of a sign-in; the name, label, issuer and discovery address of Google. |
| `Application\GoogleSignIn` | The authorization code flow: `start()` gives Google's address, `finish()` the identity. |
| `Application\GoogleClaims` | The claims of a verified ID token mapped to the bundle's `ExternalIdentity`. |
| `Application\SignInAttempts`, `Application\SignInAttempt` | The sign-ins waiting in the session, five at most, each taken once. |
| `Application\TransmitterSettings` | A transmitter of security events and what the receiver accepts of it; `google()` is Google's. |
| `Application\SecurityEventTokenReader` | Checks a security event token's signature, issuer, audience and age. |
| `Application\SecurityEventReceiver` | Acts on the events of a token: sessions closed, subjects held or released, events dispatched. |
| `Application\Receipt` | What came of a token: received before, events, sessions closed. |
| `Application\ReplayGuard` | The identifiers of the tokens acted on, in a PSR-6 pool. |
| `Application\LinkedSessions` | The account a subject is linked to, and the closing of its sessions through the bundle. |
| `Application\Contract\SubjectHolds` | The port of the held subjects. |
| `Application\Event\SecurityEventReceived` | The event dispatched once per security event acted on. |
| `Application\Exception\TransmitterUnreachable` | The transmitter's keys could not be read: the endpoint answers 503. |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Federation\GoogleProvider` | The bundle's `ExternalProvider` for Google, tagged `phil_identity_provider.external_provider`. |
| `Infrastructure\Http\RiscController` | The push endpoint of security events. |
| `Infrastructure\Http\SafeHttpTransport` | Symfony's `HttpClientInterface` over phil/safe-http, for Symfony's OpenID Connect pieces. |
| `Infrastructure\Http\SafeHttpResponse`, `Infrastructure\Http\ReadChunk` | An answer read by phil/safe-http, and its chunks, as Symfony's contract gives them. |
| `Infrastructure\Cache\CacheSubjectHolds` | The held subjects in a PSR-6 pool. |
| `Infrastructure\DependencyInjection\GoogleServices` | The services, from the configuration. |
| `PhilIdentityProviderGoogleBundle` | The bundle and its configuration tree. |

## Routes

| Name | Path | Method |
|---|---|---|
| `phil_identity_provider_google_risc` | `/risc`, under the prefix of the import | `POST` |

The sign-in uses the routes of phil/identity-provider-bundle: `phil_identity_provider_external_start`
(`/login/with/{provider}`) and `phil_identity_provider_external_callback`.

## Services

| Service | What it is |
|---|---|
| `Phil\IdentityProviderGoogleBundle\Infrastructure\Federation\GoogleProvider` | The provider, tagged for the bundle. |
| `Phil\IdentityProviderGoogleBundle\Application\GoogleSignIn` | The flow. |
| `Phil\IdentityProviderGoogleBundle\Application\GoogleSettings` | The settings. |
| `phil_identity_provider_google.transport` | The HTTP client of Symfony's OpenID Connect pieces, over phil/safe-http. |
| `phil_identity_provider_google.discovery`, `phil_identity_provider_google.signatures` | Symfony's `OidcDiscovery` and `OidcSignatureVerifier` for Google's sign-in. |
| `phil_identity_provider_google.risc_discovery`, `phil_identity_provider_google.risc_signatures` | The same for the security events, with `risc.enabled`. |
| `Phil\IdentityProviderGoogleBundle\Application\SecurityEventReceiver`, `Phil\IdentityProviderGoogleBundle\Application\TransmitterSettings` | The receiver and Google's transmitter, with `risc.enabled`. |
| `Phil\IdentityProviderGoogleBundle\Application\Contract\SubjectHolds` | An alias of `CacheSubjectHolds`, with `risc.enabled` and `risc.hold_hijacked`. |
| `Phil\IdentityProviderGoogleBundle\Infrastructure\Http\RiscController` | The endpoint. |

# Changelog

All notable changes to `phil/identity-provider-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.3.0-alpha: the links of the emails point to `public_origin`, and the `prod` environment refuses
to boot without it or `framework.trusted_hosts`; registration is limited by default; the keys of the tables take the
binary collation on MySQL and MariaDB, to migrate; the provider gains client assertions, the `client_credentials` grant
and resources, new columns and a table to migrate, and refresh tokens bound to their session by default. See
[Upgrading from 0.2](doc/installation.md#upgrading-from-02).

### Added

- `public_origin`: the canonical address of the site, such as `https://example.com`, that the links sent by email
  point to. `Application\Contract\LinkOrigin`, the port that puts each link on it, and
  `Infrastructure\Routing\RouterLinkOrigin`, which generates the link while the router's context names that origin and
  gives the request's context back at once; the origin and the links are read by `phil/url`.
- `registration.rate_limit`, on by default: `per_ip` (`limit` 10, `interval` 1 hour), `per_address` (`limit` 5,
  `interval` 1 hour) and `resend_delay` (5 minutes), with `Infrastructure\Security\RegistrationRateLimiter` and the
  service `phil_identity_provider.registration_limiter`. A refused registration answers `429` with the form and
  `error.registration.too_many`, in English and in French.
- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, and its `FIELDS`: the keys
  that compare byte by byte on MySQL and MariaDB.
- `OneTimeCodeAuthenticator::MAX_FAILURES` and `FAILURES`: the wrong codes in a row a session may post.
- Client authentication by `private_key_jwt` (RFC 7523): a confidential client registers the public keys it signs client
  assertions with — `Client::register()` and `ClientRegistry::register()` take `$jwks`, `--jwks` on
  `phil:identity-provider:client:create` — and receives no secret. `Application\Oidc\ClientAssertions` checks the
  signature with the key the `kid` names, `iss` and `sub`, `aud` — the issuer, or the token endpoint at the issuer's
  origin —, `exp`, `iat` and `nbf` within `oidc.client_authentication.clock_skew`, an expiry at most
  `oidc.client_authentication.assertion_max_lifetime` ahead, and a `jti` used once, recorded in the new table
  `phil_identity_assertion` (`Domain\Model\Oidc\UsedAssertion`, `GrantRepository::useAssertion()`).
  `ClientRegistry::replaceJwks()`, `Client::replaceJwks()` and `phil:identity-provider:client:keys` replace the keys;
  the event `identity.oidc.client_keys_replaced` (`IdentityEventType::ClientKeysReplaced`).
- `oidc.client_authentication.methods`, the methods the provider accepts — all four by default —, and
  `Domain\Oidc\ClientAuthMethod`; `Client::accepts()` names the one method of each client. `Tokens::authenticateClient()`
  takes the method and the path of the token endpoint, last and optional; `ClientCredentials` reads
  `client_assertion_type` and `client_assertion`, and gives the method.
- The `client_credentials` grant (`Tokens::clientCredentials()`): a confidential client that registered resources asks
  an access token for itself, for one of its resources, whose `sub` is the client, without an ID token nor a refresh
  token; userinfo refuses it. `oidc.extra_scopes` declares the scopes of resources.
- Resources (RFC 8707): `Client::resources()`, `allowsResource()`, `--resource` on
  `phil:identity-provider:client:create`; the `resource` of a token request, compared in its canonical form, is the
  `aud` of the access token and of introspection (`IssuedToken::audience()`, column `audience`). The errors
  `invalid_target` and `unauthorized_client` (`OAuthError::invalidTarget()`, `unauthorizedClient()`).
- `oidc.refresh_token_bound_to_session`, on by default, and `oidc.session_max_age`: a refresh token issued in a session
  is refused once the session is closed or older than the bound, its family revoked, and never expires past the bound.
  `Tokens` takes the `AccountSessionRepository`, last and optional.
- `oidc.id_token.acr`, `oidc.id_token.idp` and `oidc.id_token.session_start`: the optional claims `acr`, `idp` and
  `session_start` of the ID token, listed by the discovery document with `acr_values_supported`.
- `Application\Contract\ClaimsProvider`, the port of the claims an application adds to userinfo, autoconfigured with the
  tag `phil_identity_provider.claims_provider`, and `Application\Oidc\UserClaims`, which adds them after the claims of
  the scopes and refuses a provider that answers a claim already set. `Tokens::bearer()` gives the client of the token
  as its third member; `UserinfoController` takes the `UserClaims`, last and optional.
- Back-channel logout (OpenID Connect Back-Channel Logout 1.0), with `oidc.backchannel_logout` (`enabled`, `bus`,
  `http_client`, `timeout`): a client registers its address (`--backchannel-logout-uri`, `Client::backchannelLogoutUri()`,
  column `backchannel_logout_uri`); when a session closes, `Sessions` tells the clients it issued tokens to through the
  port `Application\Contract\LogoutNotifier`, whose adapter `Infrastructure\Messenger\MessengerLogoutNotifier`
  dispatches one `SendLogoutToken` of identifiers per client; `SendLogoutTokenHandler` posts the logout token that
  `Application\Oidc\LogoutTokens` signs, and throws `DeliveryFailed` on a failed answer so that Messenger tries again.
  `Sessions` takes the `LogoutNotifier`, last and optional; the discovery document announces
  `backchannel_logout_supported` and `backchannel_logout_session_supported`. `symfony/messenger` and
  `symfony/http-client` are suggested.
- `Jws::unverifiedClaims()`: the claims of a token read under the same bounds, its signature not verified, to learn the
  client a client assertion names.
- The documentation of client authentication, token families, refresh tokens bound to the session, resources and the
  `client_credentials` grant, and the threats of token families.

### Changed

- In the `prod` environment, the bundle refuses to boot when neither `public_origin` nor `framework.trusted_hosts` is
  set. The origin is read when the kernel boots, in every environment: one with a path, a query, a fragment or an IP
  address is refused, and `localhost` is accepted only in debug mode.
- `registration` is a section that can be turned off: `registration: true` and `registration: false` keep their
  meaning, and `registration: { rate_limit: false }` turns the limits off.
- `symfony/rate-limiter` joins `require`.
- `IssuedToken::accountId()` returns null for a token of the `client_credentials` grant; the column `account_id` of
  `phil_identity_token` is nullable. `Client` gains the nullable columns `jwks` and `resources`; `Client::isPublic()` is
  false for a client with keys, and `Client::rotateSecret()` refuses a client without a secret. Migration: run
  `bin/console doctrine:migrations:diff` and apply it.
- `TokenController::AUTH_METHODS` is removed: the discovery document lists the methods of
  `oidc.client_authentication.methods`, `private_key_jwt` among them, with
  `token_endpoint_auth_signing_alg_values_supported`, and the grant `client_credentials`. Introspection answers `aud`.
- `phil:identity-provider:client:list` shows the clients with keys and their resources.
- A refresh token of a session that ended is refused: set `oidc.refresh_token_bound_to_session: false` to keep the
  refresh tokens of a closed session alive.
- `AccountLinks` and `LoginLinkController` take a `LinkOrigin`, last and optional; the sign-in link is generated
  without the request. `AccountController` takes a `RegistrationRateLimiter`, last and optional.
- On MySQL and MariaDB, the identifiers of every table, `email` of `phil_identity_account`, `client_id`, `account_id`,
  `session_id` and `family` where they are, and `provider` and `subject` of `phil_identity_linked` take the `utf8mb4`
  character set and the `utf8mb4_bin` collation in the schema the ORM generates. Migration: run
  `bin/console doctrine:migrations:diff` and apply the `ALTER TABLE … MODIFY` it writes. PostgreSQL and SQLite keep
  their default, which compares bytes already.
- The tests generate the keys of P-256 with a short coordinate when they run: the repository keeps no private key.

### Fixed

- A refresh that narrows the scope keeps `openid`: its answer still carries an ID token, and its access token still
  reads userinfo.
- The token endpoint refuses an optional parameter — `code_verifier`, `scope` — longer than 4096 bytes with
  `invalid_request`, as it does a required one.

### Security

- The links of the reset, verification and sign-in emails were built on the `Host` header of the request: a request
  naming another host sent the person a genuine signed link to that host, and one click handed its token over. They
  are built on `public_origin`, or on a host `framework.trusted_hosts` accepted.
- Registration had no limit: each request cost a password hash, a row and an email.
- The code of the second step could be guessed without limit within one session: five wrong codes in a row sign the
  session out.
- A session that holds no proof, such as one restored by `remember_me`, answered `max_age` as if its person had just
  signed in: it signs in again.
- Under the default case-insensitive collation of MySQL and MariaDB, the client `App` was found for `app`, the
  federated subject `AbC` for `abc`, and an address with an accent for the one without.

## 0.2.0-alpha - 2026-11-17

Introspection, userinfo and the authorization endpoint refuse what they accepted,
the modules that keep secrets need a key or an explicit option, the address is verified by a form, and three
repository contracts gain a method.

### Added

- `login_limiter` and the service `phil_identity_provider.login_limiter`, `Infrastructure\Security\LoginRateLimiter`:
  the limiter the firewall's `login_throttling` names with its option `limiter`. It counts per address — an IPv6
  client by its /64 network —, per identifier and address, and per identifier whatever the address, against guessing
  spread over many addresses; its keys are keyed hashes. Needs `symfony/rate-limiter`, suggested. Its cache pool is
  handed to the `RateLimitGuard` of `phil/rate-limit`, which joins `require`: in the `prod` environment, a pool local
  to each server fails the container build unless `login_limiter.single_server` is true.
- `secrets.allow_plaintext_secrets`, false by default: whether TOTP secrets and private signing keys may be kept in
  clear when `secrets.kms_key` is null.
- `Application\Contract\Transactions`, with `Infrastructure\Doctrine\DoctrineTransactions` and
  `Infrastructure\Memory\ImmediateTransactions`: work that writes to several stores at once.
- `Accounts::resetPassword()`: the password a reset link asks for, set once.
- The template `account/verify` and the texts `title.verify` and `verify.confirm`, in English and in French.

### Changed

- With the module `totp` or `oidc`, a null `secrets.kms_key` is refused unless `secrets.allow_plaintext_secrets` is
  true; a key without `symfony/key-management` is refused instead of falling back to `PlainSecretCipher`. Setting a
  key encrypts what is written from then on: the threat model says how to replace what was written in clear before.
- `GET /verify-email` shows a page that asks to confirm; `POST /verify-email`, with the link and a CSRF token,
  verifies the address.
- `Accounts::changePassword()` takes `$keepSession` and closes every other session of the account; `Accounts` takes
  `Sessions`, last and optional.
- `AccountUser::isEqualTo()` compares the password hash: a session signed in before the password changed or was
  removed is signed out at its next request.
- `AccountRepository::apply()`: a repository of the application's own implements it, reading the row again and holding
  it while the change applies. `Account::$createdAt` is no longer a read-only property, for the same reason as those
  of `TotpCredential`. The schema does not change.
- `Sessions` takes `Transactions`, last and optional, and revokes the tokens of a session before it closes the row.
- `SecondFactorListener::OPEN_ROUTES`: the routes open before the second step, by their exact names.
- `AuthorizationRequestParser::PROMPTS` is public and no longer holds `select_account`; the discovery document reads
  `prompt_values_supported` from it.

- `GrantRepository::consumeCode()` and `GrantRepository::rotateToken()`: a repository of the application's own
  implements them, each by one conditional write that tells whether this call changed the row.
- `SecondFactorRepository::apply()`: a repository of the application's own implements it, reading the row again and
  holding it while the change applies.
- `TotpCredential::$secret` and `TotpCredential::$createdAt` are no longer read-only properties, so that the store can
  read the row again over the object. The schema does not change.
- `doctrine/dbal` moves from `require-dev` to `require`: the Doctrine stores name its types and lock modes.
- `SigningKeys` takes a `LockFactory`, after the clock; `symfony/lock` joins `require`, and `oidc.lock_factory` names the
  service, `lock.factory` by default.

### Security

- `Tokens::introspect()` answers `active: false` for the tokens of a disabled account, as `Tokens::bearer()` refuses
  them; an access or refresh token of a disabled account was reported active until it expired or was revoked.
- `Tokens::exchangeCode()` marks the code used with `GrantRepository::consumeCode()`: two requests presenting the same
  code at the same time could both receive tokens; the second is now refused as a reuse and revokes the family.
- `Tokens::refresh()` rotates the refresh token with `GrantRepository::rotateToken()`: two requests presenting the same
  refresh token at the same time could both rotate it; the second is now refused as a reuse and revokes the family.
- `SecondFactors` accepts a TOTP counter and crosses out a recovery code through `SecondFactorRepository::apply()`,
  under `SELECT … FOR UPDATE` with Doctrine: the same code presented by two requests at the same time could be accepted
  twice; it is now accepted once.
- `SigningKeys` makes the first key and each rotation under the lock `phil_identity_provider.signing_keys`, reading the
  keys again once it is held: instances starting or rotating at the same time could leave several active or pending
  keys, the extra ones never retired. Every read still settles what an unshared lock store or older keys may have
  left: all but the last activated are retired, and all but the oldest pending removed, the same way on every
  instance.
- The modules `totp` and `oidc` no longer keep their secrets in clear without being told to: a missing key, or a key
  without the KeyManagement component, kept the TOTP secrets and the private signing keys in clear in the database.
- `SecondFactorListener` opens only the routes it names exactly and those of `open_routes`: every route whose name
  began with `_`, `phil_identity_provider_challenge`, `phil_identity_provider_code` or
  `phil_identity_provider_passkey_login` was reachable before the second step of a sign-in.
- A new or reset password closes the other sessions and the tokens issued in them, and `AccountUser::isEqualTo()`
  ends a session signed in before the change: a session stolen before the password changed stayed signed in.
- `Accounts::resetPassword()` sets the password through `AccountRepository::apply()`, only while the account still has
  the hash and the date the link was signed over: two requests presenting the same reset link at the same time could
  both set a password.
- `Accounts::recordFailedSignIn()` counts through `AccountRepository::apply()`: failed sign-ins at the same time could
  each write a stale count, delaying the lock.
- `Sessions` revokes the tokens of a session, then closes it, in one transaction: a failure between the two left the
  session closed and some of its tokens active, with no way to finish.
- Opening the link of `/verify-email` verifies nothing: the scanners of mail services that open links verified the
  address in the person's place.
- `Tokens::bearer()` refuses the tokens of a disabled or removed client: userinfo kept answering a client that was
  disabled.
- `prompt=select_account` is refused with `invalid_request` and no longer offered by the discovery document: the
  provider showed no account chooser and issued the code for the account signed in.

## 0.1.0-alpha - 2026-11-15

### Added

- `PhilIdentityProviderBundle` and its configuration `phil_identity_provider`: one module per method — `accounts`,
  `password`, `passkeys`, `totp`, `recovery_codes`, `login_link`, `sms`, `sessions`, `step_up`, `external`, `oidc` —,
  each with its routes, loaded by the route loader of type `phil_identity_provider`, its tables, mapped for Doctrine
  only when it is on, and its pages; Argon2id prepended for the accounts' passwords.
- Accounts (`Accounts`, `Account`): registration that answers the same for a known address, addresses verified by
  links signed with the `SignatureHasher` of Symfony Security (`SignedLinks`, `AccountLinks`), the name, the address and
  the password changed from the account page, failed sign-ins counted towards a progressive lock (`LockoutPolicy`).
- Passwords checked by `phil/passwords` (`PasswordRules`): 8 to 4096 characters, a minimum score of the strength
  estimator with the account's address and name as first guesses, and, when turned on, absence from Pwned Passwords by
  k-anonymity; reset by a link signed over the password hash and the date of its last change.
- Passkeys (`Passkeys`, `RelyingParty`): the WebAuthn ceremonies with a bounded CBOR reader (`Cbor`), the authenticator
  data, COSE keys of ES256, RS256 and EdDSA, the client data, the counter that must move forward, a list to rename and
  remove from, the last way to sign in kept; the authenticator `PasskeyAuthenticator` and the Stimulus controller
  `passkey`.
- The authenticator application (`SecondFactors`, `Totp`, RFC 6238) and recovery codes (`RecoveryCodes`), each code
  accepted once; the second step of a sign-in (`SecondFactorListener`, `OneTimeCodeAuthenticator`).
- Sign-in links through the `login_link` authenticator of Symfony Security (`LoginLinkController`).
- The verification of a phone number by a code sent through the port `SmsSender`, by the Notifier component's texter
  by default (`PhoneVerification`, `NotifierSmsSender`).
- Step-up on the sudo mode of Symfony Security 8.2: `PHIL_STEP_UP` (`StepUpVoter`), which asks for a re-authentication
  with `requestReAuthentication()`, the proofs of each method stated with `AuthenticationMethodBadge` (RFC 8176), the
  re-authentication entry point and page.
- Devices and sessions (`Sessions`, `AccountSession`, `SessionListener`): a row per signed-in session, closed one by
  one or all at once from another device, closed with the provider's tokens issued in it; a sign-in from a new device
  marked risky and warned by email.
- Federated identities through the port `ExternalProvider` (`ExternalIdentities`, `ExternalAuthenticator`): an identity
  linked to one account, never merged on an address its provider did not verify.
- The OpenID Connect provider (`Authorization`, `Tokens`, `SigningKeys`, `ClientRegistry`): discovery, a key set whose
  keys are published before they sign and kept after they retire, the authorization code flow with PKCE S256
  (`AuthorizationRequestParser`) — required of every client unless a confidential one is registered without, and a
  verifier refused for a code issued without a challenge —, the authorization request kept as the firewall's target
  path through any sign-in, ID tokens and access tokens (RFC 9068), refresh tokens rotated with reuse detection,
  userinfo with the access token in the header or a form body (RFC 6750), revocation (RFC 7009), introspection
  (RFC 7662), RP-initiated logout, consent per client, the issuer in the authorization response (RFC 9207).
- JSON Web Signatures through `web-token/jwt-library` (`Jws`, `PrivateKey`): RS256, ES256 and EdDSA keys as JSON Web
  Keys, tokens bounded before the library reads them, verified with the one algorithm of the key their `kid` names;
  the benchmark `JwsBench`.
- The commands `phil:identity-provider:client:create` (with `--pkce-optional`), `client:list`, `client:disable`,
  `rotate-keys` and `purge`.
- Doctrine and memory stores of every module, tested on SQLite, MySQL 8.4 and PostgreSQL 17.
- The audit trail through `phil/audit-bundle` when it is installed (`AuditBundleRecorder`), the events `IdentityEvent`
  with identifiers only, the secrets sealed by the KeyManagement component when a key is configured
  (`KeyManagementSecretCipher`), the emails through the Mailer component (`MailerAccountMailer`).
- Templates drawn with the recipes of the kit, in English and French, and the fuzz targets of the readers.
- The OpenID Foundation's conformance suite in Docker (`tests/Conformance`): a compose file pinned to a release of the
  suite, the application it plays against, and `run.sh`, which plays the Basic OP and Config OP plans.
- The documentation in `doc/`, one file per topic, indexed by the README, with the threat model and the reviewer guide.
- The Flex recipe under `recipe/0.1`: the bundle registered, its configuration with the optional modules off, its routes.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

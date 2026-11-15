# Changelog

All notable changes to `phil/identity-provider-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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

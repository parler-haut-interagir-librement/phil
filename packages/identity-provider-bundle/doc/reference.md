# Reference

## Public types

| Type | Role |
|---|---|
| `PhilIdentityProviderBundle` | The bundle: its configuration `phil_identity_provider`, its Doctrine mappings and its services. |
| `Application\Accounts` | Registration, addresses, names, passwords, failed sign-ins. |
| `Application\AccountLinks`, `Application\SignedLinks` | The links that verify an address and reset a password. |
| `Application\PasswordRules` | What a new password must be. |
| `Application\Passkeys` | The WebAuthn ceremonies and the list of passkeys. |
| `Application\SecondFactors` | The authenticator application and the recovery codes. |
| `Application\PhoneVerification` | The verification of a phone number. |
| `Application\Sessions` | The signed-in sessions of an account. |
| `Application\ExternalIdentities`, `Application\ExternalIdentity`, `Application\ExternalSignIn` | Federated identities and the accounts they sign in. |
| `Application\Oidc\Authorization`, `Tokens`, `SigningKeys`, `ClientRegistry`, `Claims`, `ProviderSettings` | The OpenID Connect provider. |
| `Application\AccountUser` | The account as Symfony Security sees it. |
| `Application\Modules` | The modules turned on. |
| `Application\Journal`, `Application\Event\IdentityEvent`, `Application\Event\IdentityEventType` | The events, written to the audit trail and dispatched. |
| `Application\Contract\SecretCipher`, `ChallengeStore`, `SmsSender`, `AccountMailer`, `AuditRecorder`, `Transactions` | The ports. |
| `Application\Exception\Refused`, `OAuthError`, `DeliveryFailed` | What the services refuse, with translation keys or OAuth errors. |
| `Domain\Model\*` | The rows of each module. |
| `Domain\Contract\*Repository` | The stores of each module. |
| `Domain\WebAuthn\RelyingParty`, `Cbor`, `AuthenticatorData`, `ClientData`, `CoseKey`, `PublicKey` | WebAuthn. |
| `Application\Jose\Jws`, `Application\Jose\PrivateKey`, `Domain\Jose\SigningAlgorithm` | JSON Web Signatures and the provider's keys, through `web-token/jwt-library`. |
| `Domain\Oidc\AuthorizationRequestParser`, `AuthorizationRequest`, `Pkce`, `QueryString`, `Scopes`, `TokenKind` | The provider's protocol. |
| `Domain\Totp\Totp`, `Domain\Security\RecoveryCodes`, `Domain\Security\LockoutPolicy` | One-time passwords, recovery codes, the lock. |
| `Domain\Encoding\Base64Url`, `Base32`, `Der` | Encodings. |
| `Domain\Exception\*` | The exceptions, all implementing `IdentityProviderException`. |
| `Infrastructure\Federation\Contract\ExternalProvider` | The port of federated providers. |

## Services for the firewall

| Service | Role |
|---|---|
| `phil_identity_provider.user_provider` | The user provider of the accounts. |
| `phil_identity_provider.user_checker` | The user checker. |
| `Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator` | Sign-in with a passkey. |
| `Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator` | The code of the second step or of a re-authentication. |
| `Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator` | The callback of a federated provider. |
| `phil_identity_provider.re_authentication_entry_point` | The re-authentication entry point. |
| `phil_identity_provider.login_limiter` | With `login_limiter.enabled`, the limiter of `login_throttling`: per address, per identifier and address, per identifier. |

## Routes

| Module | Routes |
|---|---|
| `accounts` | `phil_identity_provider_register` `/register`, `_verify_email` `/verify-email`, `_resend_verification` `/account/verification`, `_account` `/account`, `_account_profile` `/account/profile`, `_account_email` `/account/email` |
| `password` | `_forgot_password` `/forgot-password`, `_reset_password` `/reset-password`, `_account_password` `/account/password` |
| `passkeys` | `_passkeys` `/account/passkeys`, `_passkeys_options`, `_passkeys_register`, `_passkeys_rename`, `_passkeys_remove`, `_passkey_login_options` `/passkey/options`, `_passkey_login` `/passkey/login` |
| `totp` | `_totp` `/account/two-factor`, `_totp_confirm`, `_totp_disable`, `_challenge` `/two-factor`, `_code_check` `/two-factor/check` |
| `recovery_codes` | `_recovery_codes` `/account/two-factor/recovery-codes` |
| `login_link` | `_login_link` `/login/link`, `_login_link_check` `/login/link/check` |
| `sms` | `_phone` `/account/phone`, `_phone_start`, `_phone_confirm`, `_phone_remove` |
| `sessions` | `_sessions` `/account/sessions`, `_sessions_close`, `_sessions_close_others`, `_consent_withdraw` with `oidc` |
| `step_up` | `_reauthenticate` `/reauthenticate` |
| `external` | `_external` `/account/identities`, `_external_unlink`, `_external_start` `/login/with/{provider}`, `_external_callback` `/login/with/{provider}/callback` |
| `oidc` | `_oidc_discovery`, `_oidc_jwks`, `_oidc_authorize`, `_oidc_consent`, `_oidc_token`, `_oidc_revoke`, `_oidc_introspect`, `_oidc_userinfo`, `_oidc_end_session` |

Every name starts with `phil_identity_provider`.

## Tables

`phil_identity_account`, `phil_identity_passkey`, `phil_identity_totp`, `phil_identity_recovery_codes`,
`phil_identity_session`, `phil_identity_linked`, `phil_identity_client`, `phil_identity_consent`, `phil_identity_code`,
`phil_identity_token`, `phil_identity_signing_key`.

## Commands

| Command | Role |
|---|---|
| `phil:identity-provider:client:create` | Registers a client and shows its secret once; `--pkce-optional` lets a confidential client go without PKCE. |
| `phil:identity-provider:client:list` | Lists the clients. |
| `phil:identity-provider:client:disable` | Disables a client, or rotates its secret with `--rotate-secret`. |
| `phil:identity-provider:rotate-keys` | Rotates the signing keys; `--now` activates the pending key at once. |
| `phil:identity-provider:purge` | Removes expired codes and tokens and closed or idle sessions. |

## Events

`IdentityEvent` carries an `IdentityEventType` — whose value is the action written to the audit trail, such as
`identity.account.registered`, `identity.sign_in.risky`, `identity.oidc.token_reuse` —, the account's identifier and
identifiers of what happened: a passkey, a session, a client, a provider. Never an address, a name, a code or a secret.

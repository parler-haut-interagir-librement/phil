<!-- phil-dev source-sha256=3d34b9548e7777383ea0070e083a542713bdbeb754607c7db19af17aa11fa710 -->
# Référence

## Types publics

| Type | Rôle |
|---|---|
| `PhilIdentityProviderBundle` | Le bundle : sa configuration `phil_identity_provider`, ses associations Doctrine et ses services. |
| `Application\Accounts` | Inscription, adresses, noms, mots de passe, échecs de connexion. |
| `Application\AccountLinks`, `Application\SignedLinks` | Les liens qui vérifient une adresse et réinitialisent un mot de passe. |
| `Application\PasswordRules` | Ce qu'un nouveau mot de passe doit être. |
| `Application\Passkeys` | Les cérémonies WebAuthn et la liste des clés d'accès. |
| `Application\SecondFactors` | L'application d'authentification et les codes de récupération. |
| `Application\PhoneVerification` | La vérification d'un numéro de téléphone. |
| `Application\Sessions` | Les sessions ouvertes d'un compte. |
| `Application\ExternalIdentities`, `Application\ExternalIdentity`, `Application\ExternalSignIn` | Les identités fédérées et les comptes qu'elles connectent. |
| `Application\Oidc\Authorization`, `Tokens`, `SigningKeys`, `ClientRegistry`, `Claims`, `ProviderSettings` | Le fournisseur OpenID Connect. |
| `Application\AccountUser` | Le compte tel que Symfony Security le voit. |
| `Application\Modules` | Les modules activés. |
| `Application\Journal`, `Application\Event\IdentityEvent`, `Application\Event\IdentityEventType` | Les événements, écrits dans la piste d'audit et répartis. |
| `Application\Contract\SecretCipher`, `ChallengeStore`, `SmsSender`, `AccountMailer`, `AuditRecorder`, `Transactions` | Les ports. |
| `Application\Exception\Refused`, `OAuthError`, `DeliveryFailed` | Ce que les services refusent, avec des clés de traduction ou des erreurs OAuth. |
| `Domain\Model\*` | Les lignes de chaque module. |
| `Domain\Contract\*Repository` | Les magasins de chaque module. |
| `Domain\WebAuthn\RelyingParty`, `Cbor`, `AuthenticatorData`, `ClientData`, `CoseKey`, `PublicKey` | WebAuthn. |
| `Application\Jose\Jws`, `Application\Jose\PrivateKey`, `Domain\Jose\SigningAlgorithm` | Les signatures JSON Web et les clés du fournisseur, par `web-token/jwt-library`. |
| `Domain\Oidc\AuthorizationRequestParser`, `AuthorizationRequest`, `Pkce`, `QueryString`, `Scopes`, `TokenKind` | Le protocole du fournisseur. |
| `Domain\Totp\Totp`, `Domain\Security\RecoveryCodes`, `Domain\Security\LockoutPolicy` | Mots de passe à usage unique, codes de récupération, verrou. |
| `Domain\Encoding\Base64Url`, `Base32`, `Der` | Encodages. |
| `Domain\Exception\*` | Les exceptions, qui implémentent toutes `IdentityProviderException`. |
| `Infrastructure\Federation\Contract\ExternalProvider` | Le port des fournisseurs fédérés. |

## Services pour le pare-feu

| Service | Rôle |
|---|---|
| `phil_identity_provider.user_provider` | Le fournisseur d'utilisateurs des comptes. |
| `phil_identity_provider.user_checker` | Le vérificateur d'utilisateurs. |
| `Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator` | Connexion avec une clé d'accès. |
| `Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator` | Le code de la deuxième étape ou d'une réauthentification. |
| `Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator` | Le rappel d'un fournisseur fédéré. |
| `phil_identity_provider.re_authentication_entry_point` | Le point d'entrée de la réauthentification. |
| `phil_identity_provider.login_limiter` | Avec `login_limiter.enabled`, le limiteur de `login_throttling` : par adresse, par identifiant et adresse, par identifiant. |

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
| `sessions` | `_sessions` `/account/sessions`, `_sessions_close`, `_sessions_close_others`, `_consent_withdraw` avec `oidc` |
| `step_up` | `_reauthenticate` `/reauthenticate` |
| `external` | `_external` `/account/identities`, `_external_unlink`, `_external_start` `/login/with/{provider}`, `_external_callback` `/login/with/{provider}/callback` |
| `oidc` | `_oidc_discovery`, `_oidc_jwks`, `_oidc_authorize`, `_oidc_consent`, `_oidc_token`, `_oidc_revoke`, `_oidc_introspect`, `_oidc_userinfo`, `_oidc_end_session` |

Chaque nom commence par `phil_identity_provider`.

## Tables

`phil_identity_account`, `phil_identity_passkey`, `phil_identity_totp`, `phil_identity_recovery_codes`,
`phil_identity_session`, `phil_identity_linked`, `phil_identity_client`, `phil_identity_consent`, `phil_identity_code`,
`phil_identity_token`, `phil_identity_signing_key`.

## Commandes

| Commande | Rôle |
|---|---|
| `phil:identity-provider:client:create` | Enregistre un client et montre son secret une seule fois ; `--pkce-optional` permet à un client confidentiel de se passer de PKCE. |
| `phil:identity-provider:client:list` | Liste les clients. |
| `phil:identity-provider:client:disable` | Désactive un client, ou renouvelle son secret avec `--rotate-secret`. |
| `phil:identity-provider:rotate-keys` | Fait tourner les clés de signature ; `--now` active tout de suite la clé en attente. |
| `phil:identity-provider:purge` | Retire les codes et les jetons expirés et les sessions fermées ou inactives. |

## Événements

`IdentityEvent` porte un `IdentityEventType` — dont la valeur est l'action écrite dans la piste d'audit, comme
`identity.account.registered`, `identity.sign_in.risky`, `identity.oidc.token_reuse` —, l'identifiant du compte et les
identifiants de ce qui est arrivé : une clé d'accès, une session, un client, un fournisseur. Jamais une adresse, un nom,
un code ou un secret.

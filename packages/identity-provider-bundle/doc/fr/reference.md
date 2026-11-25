<!-- phil-dev source-sha256=da385de03a97fc3e3780dbc5d331413fc8d268ce0147edf2bb0da79343155308 -->
# Référence

## Types publics

| Type | Rôle |
|---|---|
| `PhilIdentityProviderBundle` | Le bundle : sa configuration `phil_identity_provider`, ses associations Doctrine et ses services. |
| `Application\Accounts` | Inscription, adresses, noms, mots de passe, échecs de connexion, états. |
| `Application\AccountAddresses` | Les adresses de récupération et d'avis d'un compte. |
| `Application\EmailCanonicalizer` | La forme canonique d'une adresse, son sous-adressage omis sur demande. |
| `Application\Identifier\UlidAccountIdGenerator`, `UuidV7AccountIdGenerator` | Les identifiants des nouveaux comptes : ULID ou UUIDv7. |
| `Application\AccountLinks`, `Application\SignedLinks` | Les liens qui vérifient une adresse et réinitialisent un mot de passe, signés ou conservés. |
| `Application\PasswordRules` | Ce qu'un nouveau mot de passe doit être. |
| `Application\Passkeys` | Les cérémonies WebAuthn et la liste des clés d'accès. |
| `Application\SecondFactors` | L'application d'authentification et les codes de récupération. |
| `Application\PhoneVerification` | La vérification d'un numéro de téléphone. |
| `Application\Sessions` | Les sessions ouvertes d'un compte. |
| `Application\ExternalIdentities`, `Application\ExternalIdentity`, `Application\ExternalSignIn` | Les identités fédérées et les comptes qu'elles connectent. |
| `Application\Oidc\FileSigningKeys` | Les clés de signature lues dans des fichiers. |
| `Application\Oidc\Authorization`, `Tokens`, `SigningKeys`, `ClientRegistry`, `ClientAssertions`, `Claims`, `UserClaims`, `LogoutTokens`, `Subjects`, `ProviderSettings` | Le fournisseur OpenID Connect. |
| `Application\AccountUser` | Le compte tel que Symfony Security le voit. |
| `Application\Modules` | Les modules activés. |
| `Application\Journal`, `Application\Event\IdentityEvent`, `Application\Event\IdentityEventType` | Les événements, écrits dans la piste d'audit et répartis. |
| `Application\Contract\SecretCipher`, `ChallengeStore`, `SmsSender`, `AccountMailer`, `AuditRecorder`, `Transactions`, `LinkOrigin`, `ClaimsProvider`, `LogoutNotifier`, `AccountIdGenerator`, `AddressCanonicalizer`, `SigningKeySource`, `Signer` | Les ports. |
| `Infrastructure\Routing\RouterLinkOrigin` | Les liens des courriels générés sur `public_origin`, et le démarrage refusé en `prod` sans lui ni `framework.trusted_hosts`. |
| `Infrastructure\Security\RegistrationRateLimiter` | Les limites de l'inscription et le délai entre deux courriels de vérification. |
| `Infrastructure\DependencyInjection\LoginLinkPostOnlyPass` | `check_post_only` activé sur l'authentificateur `login_link` de la route de vérification du bundle. |
| `Infrastructure\Doctrine\BinaryKeysListener` | La collation binaire des clés sur MySQL et MariaDB. |
| `Infrastructure\Messenger\SendLogoutToken`, `SendLogoutTokenHandler`, `MessengerLogoutNotifier` | La déconnexion par canal arrière, par Messenger. |
| `Application\Exception\Refused`, `OAuthError`, `DeliveryFailed` | Ce que les services refusent, avec des clés de traduction ou des erreurs OAuth. |
| `Domain\Model\*` | Les lignes de chaque module. |
| `Domain\Contract\*Repository` | Les magasins de chaque module. |
| `Domain\WebAuthn\RelyingParty`, `Cbor`, `AuthenticatorData`, `ClientData`, `CoseKey`, `PublicKey` | WebAuthn. |
| `Domain\WebAuthn\AttestationStatement`, `AttestationPolicy`, `AttestationType` | L'attestation des nouvelles clés d'accès et ce que le site en accepte. |
| `Domain\Account\AccountState`, `AddressPurpose` | Les états d'un compte et les usages de ses adresses. |
| `Application\Jose\Jws`, `Application\Jose\PrivateKey`, `Application\Jose\LoadedKeys`, `Domain\Jose\SigningAlgorithm` | Les signatures JSON Web, les clés du fournisseur chargées une seule fois, et leur vérification, par `web-token/jwt-library`. |
| `Domain\Oidc\AuthorizationRequestParser`, `AuthorizationRequest`, `Pkce`, `QueryString`, `Scopes`, `TokenKind`, `ClientAuthMethod`, `SubjectType` | Le protocole du fournisseur. |
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
| `phil_identity_provider.registration_limiter` | Avec `registration.rate_limit.enabled`, par défaut, les limites de `/register` et des courriels de vérification. |

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

`phil_identity_account`, `phil_identity_address`, `phil_identity_passkey`, `phil_identity_totp`,
`phil_identity_recovery_codes`, `phil_identity_session`, `phil_identity_linked`, `phil_identity_client`,
`phil_identity_consent`, `phil_identity_code`, `phil_identity_token`, `phil_identity_revoked_family`,
`phil_identity_assertion`, `phil_identity_signing_key`, et `phil_identity_link_token` avec `links.mode: stored`.
Chaque colonne `account_id` contient 36 caractères, l'identifiant de `accounts.id_generator`.

Chaque colonne d'instant est mappée en `datetime_utc_immutable` : écrite et lue en UTC, quel que soit le fuseau
horaire du processus.

## Commandes

| Commande | Rôle |
|---|---|
| `phil:identity-provider:client:create` | Enregistre un client et montre son secret une seule fois ; `--pkce-optional` permet à un client confidentiel de se passer de PKCE ; `--jwks` enregistre ses clés publiques à la place d'un secret ; `--resource` nomme une ressource pour laquelle il peut demander des jetons ; `--backchannel-logout-uri` l'adresse de ses jetons de déconnexion ; `--sector-identifier` l'hôte de ses sujets par paire. |
| `phil:identity-provider:client:keys` | Remplace les clés publiques dont un client signe ses assertions de client. |
| `phil:identity-provider:client:list` | Liste les clients. |
| `phil:identity-provider:client:disable` | Désactive un client, ou renouvelle son secret avec `--rotate-secret`. |
| `phil:identity-provider:rotate-keys` | Fait tourner les clés de signature de la base de données ; `--now` active tout de suite la clé en attente ; avec `oidc.signing_keys.automatic_rotation: false`, seuls `--force` ou `--now` font tourner les clés. |
| `phil:identity-provider:seal-key` | Scelle un fichier de clé privée sous `secrets.kms_key`, pour la source `file` des clés de signature ; avec `secrets.kms_key` réglé. |
| `phil:identity-provider:purge` | Retire les codes, les jetons, les assertions de client, les jetons de lien et les marques de familles révoquées expirés et les sessions fermées ou inactives. |

## Événements

`IdentityEvent` porte un `IdentityEventType` — dont la valeur est l'action écrite dans la piste d'audit, comme
`identity.account.registered`, `identity.sign_in.risky`, `identity.oidc.token_reuse` —, l'identifiant du compte et les
identifiants de ce qui est arrivé : une clé d'accès, une session, un client, un fournisseur. Jamais une adresse, un nom,
un code ou un secret.

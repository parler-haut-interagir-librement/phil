# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Plug a federated provider in | Implement `Infrastructure\Federation\Contract\ExternalProvider` and tag the service `phil_identity_provider.external_provider`: see [Federated providers](external-providers.md) |
| Add claims to userinfo | Implement `Application\Contract\ClaimsProvider`: autoconfigured, or tag the service `phil_identity_provider.claims_provider`; see [Scopes and claims](oidc-provider.md#scopes-and-claims) |
| Give accounts identifiers of your own | Implement `Application\Contract\AccountIdGenerator` and name the service in `accounts.id_generator`: see [Identifiers](accounts.md#identifiers) |
| Compare addresses by the rules of a mail provider | Implement `Application\Contract\AddressCanonicalizer` and name the service in `accounts.canonicalizer`: see [Addresses](accounts.md#addresses) |
| Sign with a hardware security module or a key management service | Implement `Application\Contract\SigningKeySource`, its signers `Application\Contract\Signer`, and name the service in `oidc.signing_keys.service`: see [A hardware security module](oidc-provider.md#a-hardware-security-module) |
| Tell the clients otherwise that a session ended | Alias `Application\Contract\LogoutNotifier` to your own service |
| Send text messages through another gateway | Implement `Application\Contract\SmsSender` and name the service in `sms.sender`; it sends at once, or queues identifiers only, never the code |
| Point the links of the emails to each tenant's host | Alias `Application\Contract\LinkOrigin` to your own service: it generates each link on the host it chooses |
| Send the emails otherwise | Alias `Application\Contract\AccountMailer` to your own service; it sends at once, or queues identifiers only — the account and the kind of link —, never the link (see [Why account emails and SMS never go through Messenger](links-and-sms.md#why-account-emails-and-sms-never-go-through-messenger)) |
| Write the events elsewhere | Implement `Application\Contract\AuditRecorder` and name the service in `audit` |
| Seal the secrets otherwise | Alias `Application\Contract\SecretCipher` to your own service |
| React to what happens to accounts | Listen to `Application\Event\IdentityEvent` on the event dispatcher: its type and identifiers |
| Keep the rows elsewhere | Alias an interface of `Domain\Contract` — `AccountRepository`, `AccountAddressRepository`, `LinkTokenRepository`, `PasskeyRepository`, … — to your own store; `LinkTokenRepository::consume()` must remove the token in one conditional write; `GrantRepository::consumeCode()` and `rotateToken()` must change the row in one conditional write, `revokeFamily()` must write the mark of the family before it revokes the tokens, and `isFamilyRevoked()` must read the mark as written, not from a cache, and `SecondFactorRepository::apply()` and `AccountRepository::apply()` must read the row again and hold it while it changes, so that a code, a reset link or a failed sign-in counts once even with two requests at the same time; alias `Application\Contract\Transactions` to a transaction of your store, so that closing a session and revoking its tokens go together |
| Protect your own actions | Require `PHIL_STEP_UP` with `#[IsGranted]` or `isGranted()` |
| Change a page | Replace its template under `templates/bundles/PhilIdentityProviderBundle/`, or name your layout in `layout` |
| Translate the pages | Add `translations/phil_identity_provider.<locale>.php` (or `.yaml`, `.xlf`) with the keys of the English file |

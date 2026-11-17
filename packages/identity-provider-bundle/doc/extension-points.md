# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Plug a federated provider in | Implement `Infrastructure\Federation\Contract\ExternalProvider` and tag the service `phil_identity_provider.external_provider`: see [Federated providers](external-providers.md) |
| Send text messages through another gateway | Implement `Application\Contract\SmsSender` and name the service in `sms.sender` |
| Send the emails otherwise | Alias `Application\Contract\AccountMailer` to your own service |
| Write the events elsewhere | Implement `Application\Contract\AuditRecorder` and name the service in `audit` |
| Seal the secrets otherwise | Alias `Application\Contract\SecretCipher` to your own service |
| React to what happens to accounts | Listen to `Application\Event\IdentityEvent` on the event dispatcher: its type and identifiers |
| Keep the rows elsewhere | Alias an interface of `Domain\Contract` — `AccountRepository`, `PasskeyRepository`, … — to your own store; `GrantRepository::consumeCode()` and `rotateToken()` must change the row in one conditional write, and `SecondFactorRepository::apply()` and `AccountRepository::apply()` must read the row again and hold it while it changes, so that a code, a reset link or a failed sign-in counts once even with two requests at the same time; alias `Application\Contract\Transactions` to a transaction of your store, so that closing a session and revoking its tokens go together |
| Protect your own actions | Require `PHIL_STEP_UP` with `#[IsGranted]` or `isGranted()` |
| Change a page | Replace its template under `templates/bundles/PhilIdentityProviderBundle/`, or name your layout in `layout` |
| Translate the pages | Add `translations/phil_identity_provider.<locale>.php` (or `.yaml`, `.xlf`) with the keys of the English file |

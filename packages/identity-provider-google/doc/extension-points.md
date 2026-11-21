# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the held accounts elsewhere | Implement `Application\Contract\SubjectHolds` and alias it, or point `risc.holds_pool` at a durable PSR-6 pool |
| Act on a security event | Listen to `Application\Event\SecurityEventReceived` |
| Call Google through another transport | Set `http_client` to a Symfony HTTP client service; phil/safe-http still wraps it |
| Receive the security events of another provider | Build a `SecurityEventReceiver` with its own `TransmitterSettings` and verifier, and route a `RiscController` to it |
| Decide how an identity is linked | Configure phil/identity-provider-bundle's module `external`: `register` and `link_verified_email` |

## The held accounts

```php
namespace Phil\IdentityProviderGoogle\Application\Contract;

interface SubjectHolds
{
    public function hold(string $provider, string $subject): void;

    public function release(string $provider, string $subject): void;

    public function isHeld(string $provider, string $subject): bool;
}
```

`CacheSubjectHolds` keeps them in a PSR-6 pool. An application with its own table aliases its implementation:

```yaml
services:
    App\Identity\DoctrineSubjectHolds: ~
    Phil\IdentityProviderGoogle\Application\Contract\SubjectHolds: '@App\Identity\DoctrineSubjectHolds'
```

The store must be durable: a store that forgets lets a hijacked Google account sign in again.

## The event

`SecurityEventReceived` is dispatched once per event of a security event token, after the receiver closed the sessions
and held or released the subject. It carries identifiers only:

| Property | Holds |
|---|---|
| `provider` | `google`, or the provider of another transmitter |
| `type` | The `SecurityEventType` |
| `accountId` | The identifier of the linked account; null when the subject is linked to none, or the event names no subject |
| `reason` | The `reason` of the event, such as `hijacking` or `bulk-account`; null without one |
| `sessionsClosed` | The sessions closed |

```php
use Phil\IdentityProviderGoogle\Application\Event\SecurityEventReceived;
use Phil\IdentityProviderGoogle\Domain\Risc\SecurityEventType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AskForReview
{
    public function __invoke(SecurityEventReceived $event): void
    {
        if (SecurityEventType::AccountCredentialChangeRequired === $event->type && null !== $event->accountId) {
            // Flag the account for review; a heavy task goes to a Messenger message carrying the identifier.
        }
    }
}
```

## What stays closed

- The flow itself — the state, the nonce, PKCE, the checks of the ID token — is not configurable beyond the options of
  [Configuration](configuration.md): a weaker flow is not offered.
- The rule of the trusted address is fixed: an application that trusts more addresses would let a Google account take
  over an account it does not own.
- No token of Google is kept, and no refresh token is asked for.

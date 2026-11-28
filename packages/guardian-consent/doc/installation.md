# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `sodium` | The confirmation tokens, written in URL-safe Base64 in constant time. |
| `psr/clock` | `^1.0` | Today in the member's jurisdiction, and the instants of requests and proofs. |
| `psr/event-dispatcher` | `^1.0` | The events after a request, a decision, a withdrawal, an erasure and a handover. |
| `symfony/service-contracts` | `^3.8` | `ResetInterface`, implemented by the stores in memory. |
| `symfony/clock` | `^8.2`, suggested | A clock to give the service, and a frozen one in tests. |

```sh
composer require phil/guardian-consent
```

## The services

`GuardianConsent` takes a store of minors, a store of requests, a store of proofs, a notifier, a token hasher and a
clock; then, optionally, the jurisdictions served (Quebec alone by default), an event dispatcher, a guardian link,
the days a request may be answered (7 by default, 1 to 30) and the days an unanswered request is kept after it expires
(30 by default, 0 to 365), a random generator and the transactions of the stores' database (`NoTransactions` by
default, for the stores in memory).

`TokenHasher` takes the key the confirmation links are signed with, 32 bytes at least, read from the secrets vault,
followed by the keys it replaced, if any.

In a Symfony application, with stores and a notifier of its own:

```yaml
# config/services.yaml
services:
    Phil\GuardianConsent\Contract\MinorStore: '@App\Consent\DoctrineMinorStore'
    Phil\GuardianConsent\Contract\RequestStore: '@App\Consent\DoctrineRequestStore'
    Phil\GuardianConsent\Contract\ProofStore: '@App\Consent\DoctrineProofStore'
    Phil\GuardianConsent\Contract\Notifier: '@App\Consent\MailerNotifier'
    Phil\GuardianConsent\Contract\Transactions: '@App\Consent\DbalTransactions'

    Phil\GuardianConsent\TokenHasher:
        arguments: ['%env(GUARDIAN_CONSENT_KEY)%']

    Phil\GuardianConsent\Jurisdictions:
        factory: ['App\Consent\Jurisdictions', 'create']

    Phil\GuardianConsent\GuardianConsent:
        arguments:
            $clock: '@clock'
            $events: '@event_dispatcher'
            $requestLifetimeDays: 7
            $retentionDays: 30
            $transactions: '@Phil\GuardianConsent\Contract\Transactions'

    Phil\GuardianConsent\GuardianConsentRights: ~
```

`GuardianConsent::sweep()` runs once a day, from a scheduler: see [Concepts](concepts.md#the-sweep).

The stores in memory, `InMemoryMinorStore`, `InMemoryRequestStore` and `InMemoryProofStore`, serve tests and
demonstrations.

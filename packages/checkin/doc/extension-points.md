# Extension points

The library has three ports, interfaces of `Phil\Checkin\Contract`. Their adapters belong to the application or to
the packages that extend the core.

## `CredentialResolver`

Finds the credential a code stands for:

```php
interface CredentialResolver
{
    public function resolve(CredentialCode $code): ?Credential;
}
```

A resolver answers null for a code it does not know. `CredentialResolvers` asks several in their order and stops at
the first that knows the code, so that tickets, registrations and membership cards each bring their own. A resolver
can read a table, call a service, or verify a code that carries its credential signed inside it; whatever it does, it
compares codes in constant time when it compares them itself, and never logs them.

```php
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\CredentialStatus;

final readonly class TicketResolver implements CredentialResolver
{
    public function __construct(
        private TicketRepository $tickets,
    ) {
    }

    public function resolve(CredentialCode $code): ?Credential
    {
        $ticket = $this->tickets->findOneByCodeHash(hash('sha256', $code->value));

        return null === $ticket ? null : Credential::of(
            'ticket-'.$ticket->id,
            $ticket->holderName,
            [CheckpointId::of($ticket->door)],
            $ticket->event->doorsOpenAt,
            $ticket->event->endsAt,
            maxEntries: 1,
            status: $ticket->refunded ? CredentialStatus::Revoked : CredentialStatus::Active,
        );
    }
}
```

## `CheckInLedger`

Where the check-ins are kept, shared by every device and process:

```php
interface CheckInLedger
{
    public function attendance(string $credentialId): Attendance;

    /** @param list<string> $credentialIds @return array<string, Attendance> */
    public function attendances(array $credentialIds): array;

    public function find(ScanId $id): ?CheckIn;

    public function append(CheckIn $checkIn): bool;
}
```

- `attendance()` folds the admissions and check-outs of a credential: their count is the version, the admissions are
  the entries, the last of them says whether the holder is inside. `Attendance::restore()` rebuilds it from the
  stored values, and `Attendance::none()` is a credential never scanned.
- `attendances()` answers one entry per identifier asked, for the manifests; it is never called with an empty list.
- `append()` is atomic. It returns false, and records nothing, when the scan identifier is already recorded, or when
  the check-in carries a sequence that is already taken for its credential. A table with a primary key on the scan
  identifier and a unique key on the credential and the sequence does both: refusals carry no sequence, and
  the unique key ignores them.
- `CheckIn::restore()` rebuilds a stored check-in; the scan's code is never stored.

`InMemoryCheckInLedger` is the reference behaviour, for tests. `phil/checkin-bundle` ships a ledger over a Doctrine
connection.

## `CredentialCatalog`

The credentials an application can list, for offline devices:

```php
interface CredentialCatalog
{
    /** @return iterable<CatalogEntry> */
    public function forCheckpoint(CheckpointId $checkpoint): iterable;

    public function find(string $credentialId): ?Credential;
}
```

- `forCheckpoint()` yields a `CatalogEntry` — a code and its credential — per code of the credentials that may be
  scanned at the checkpoint; a generator keeps memory flat. The issuer keeps those that open the checkpoint and stops
  past its limit.
- `find()` gives a credential by its identifier, to decide again a scan made offline; null rejects the scan.

Without a catalog, devices only check in online.

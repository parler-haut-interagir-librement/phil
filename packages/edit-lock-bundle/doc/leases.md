# Leases

## Keys and holders

A lease is on a `LeaseKey`: a resource, named by the application in 1 to 255 bytes without control characters, and
optionally a part, the identifier of a block of its document — 1 to 64 letters, digits, `_` or `-`, as UJO
writes them.

```php
LeaseKey::of('article:42');          // the whole article
LeaseKey::of('article:42', 'aB3x');  // one block of it
```

A lease on the whole resource excludes the leases of other holders on every part, and a lease on a part excludes
another holder's lease on the same part or on the whole resource (`LeaseKey::overlaps()`). A holder of the whole may
write every part (`covers()`).

A `Holder` is an identifier — a user's — and the name the other writers see. Two holders with the same identifier
are the same writer: one person's two tabs share their leases.

## The life of a lease

```php
$lease = $locks->acquire(LeaseKey::of('article:42', 'aB3x'), $holder); // LeaseHeld if someone else holds it
$locks->heartbeat($lease->key(), $holder);                              // LeaseLost if it was lost
$locks->release($lease->key(), $holder);                                // false if it was not held
$locks->leave('article:42', $holder);                                   // every lease of the holder on the resource
```

| Step | What happens |
|---|---|
| Acquire | The policy must let the holder edit the resource. Free, or expired, the key is granted for `seconds`; already held by the holder, it is renewed |
| Heartbeat | The holder's lease is kept for another `seconds` from now; a lease that expired but that nobody took is kept too |
| Expiry | A lease nobody renews stops counting at `expires_at`: the next holder who asks gets it, and presence leaves it out |
| Takeover | A holder the policy allows removes the leases of others on the key and on what overlaps it, and takes the key |
| Release, leave | The holder gives the key, or every key on the resource, back |
| Purge | `purge()` deletes expired rows; call it from a scheduled task, though an expired lease is replaced anyway |

Each step that changes a lease tells the other writers the leases active on the resource. Times are UTC, to the
second; they are compared with `<` and `>`, never with `==`.

## The policy

```php
use Phil\EditLockBundle\Application\EditPolicy;

final readonly class WritersPolicy implements EditPolicy
{
    public function mayEdit(Holder $holder, string $resource): bool
    {
        // the holder may write this resource: they are one of its authors, a moderator…
    }

    public function mayTakeOver(Holder $holder, Lease $lease): bool
    {
        // the holder may take this lease from its holder: they own the resource, they administer the site…
    }
}
```

`mayEdit()` guards acquiring, taking over, and reading presence through the endpoint. It is asked again at each
heartbeat, which releases the lease of a holder no longer allowed, and at each block saved or removed: an editor whose
right is withdrawn while their page is open loses the lease at the next beat and writes nothing more.
`mayTakeOver()` is asked for each lease a takeover would remove. A refusal is `EditRefused`.

## Races

Two requests may ask for the same key at the same moment. Every write of the repository is a compare-and-set: an
insertion fails when the key is taken, a replacement when the version kept is no longer the one read. `EditLocks`
reads again and tries again, three times, then answers `LeaseHeld` without a lease. With Doctrine, the race is decided
by the table's primary key and an `UPDATE … WHERE version = ?`; a lost insertion is caught without closing the entity
manager.

The exclusion between the whole resource and its parts is checked by reading the resource's leases, then writing;
two holders asking at the same instant, one for the whole and one for a part, must not both get theirs. Each claim —
acquiring, taking over, a heartbeat — therefore runs through `LeaseRepository::exclusively()`, while no other claim on
the same resource runs. With Doctrine, the claim is a transaction whose first statement updates the resource's row of
`phil_edit_lease_resource`, inserting it the first time: the database holds every other claim on the resource at that
statement until the first one commits, and the second then reads the lease the first one wrote. Claims on other
resources do not wait, but for SQLite, which lets one writer in at a time, and for MySQL's first claims on resources
never leased, which its gap locks may order. A deadlock, or a wait cut short by `innodb_lock_wait_timeout`,
PostgreSQL's `lock_timeout` or SQLite's busy timeout, undoes the claim; `EditLocks` tries again, as after a lost
race. When the application's own transaction is open on the connection, the claim runs in a savepoint and the row
stays locked until the application commits.

## Presence

`presence('article:42')` gives the active leases of the resource and its parts, the whole first, then by part:
`Lease::holder()`, `key()->part`, `expiresAt()`, `secondsLeftAt($now)`.

# Exceptions

Every exception of the bundle implements `Phil\EditLockBundle\Domain\Exception\EditLockException`.

| Exception | Thrown by | When |
|---|---|---|
| `Domain\Exception\InvalidLease` | `LeaseKey::of()`, `Holder::of()`, `Lease`, `EditLocks`, `DoctrineLeaseRepository` | A resource, a part, a holder or a duration a lease cannot be made of; a stored row that is no lease |
| `Application\Exception\LeaseHeld` | `acquire()`, `takeOver()` | Another holder holds the key or what overlaps it; `$lease` is theirs, `null` after requests kept racing |
| `Application\Exception\LeaseLost` | `heartbeat()`, `BlockSaves` | The holder no longer holds the key, or does not hold the block saved |
| `Application\Exception\EditRefused` | `acquire()`, `takeOver()`, `heartbeat()`, `BlockSaves`, the presence endpoint | The policy refuses to let the holder edit the resource, or take a lease over |
| `Application\Exception\InvalidBlock` | `BlockSaves::save()` | `phil/ujo` refuses the block, or the list or the block before is not one; `getPrevious()` holds the `phil/ujo` exception |

The endpoints answer them with a status: [endpoints](endpoints.md#errors). A hub that cannot be reached throws
nothing: [Mercure](mercure.md#a-hub-that-cannot-be-reached).

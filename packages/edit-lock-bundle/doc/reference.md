# Reference

## PHP

| Class | Role |
|---|---|
| `PhilEditLockBundle` | The bundle: its configuration, the tables mapped for Doctrine, its CSRF token among the stateless ones, the assets mapped for AssetMapper, Mercure turned on with MercureBundle |
| `Domain\ValueObject\LeaseKey` | A resource, and optionally one of its blocks; `overlaps()`, `covers()`, `equals()` |
| `Domain\ValueObject\Holder` | Who holds a lease: an identifier and a name |
| `Domain\Model\Lease` | One holder's right to write a key until a moment; `grant()`, `restore()`, `renewed()`, `passedTo()`, the table `phil_edit_lease` |
| `Domain\Model\LeasedResource` | A resource leased, the row of `phil_edit_lease_resource` each claim on it locks |
| `Domain\Repository\LeaseRepository` | Where leases are kept, by compare-and-set, claims on one resource run one at a time by `exclusively()` |
| `Domain\Exception\EditLockException`, `InvalidLease` | The bundle's exceptions, and a lease that cannot be made |
| `Application\EditLocks` | Acquire, heartbeat, release, leave, take over, holds, presence, purge |
| `Application\EditPolicy`, `DefaultEditPolicy` | Who may edit and take over; the default for the application's own code |
| `Application\BlockSaves` | Blocks checked, written by their holder, kept and told |
| `Application\BlockStore` | Where the application keeps saved blocks |
| `Application\Broadcaster`, `NullBroadcaster` | Telling the other writers; telling nobody |
| `Application\HolderProvider` | Who the current request writes as |
| `Application\Exception\LeaseHeld`, `LeaseLost`, `EditRefused`, `InvalidBlock` | What goes wrong with leases and blocks |
| `Infrastructure\Doctrine\DoctrineLeaseRepository` | The leases in `phil_edit_lease`, by plain queries, each claim in a transaction that locks the resource's row |
| `Infrastructure\Memory\InMemoryLeaseRepository` | The leases in memory, for tests |
| `Infrastructure\Mercure\MercureBroadcaster` | Presence and blocks published to a Mercure hub |
| `Infrastructure\Mercure\TurboStreams` | The Turbo Streams of presence, saved and removed blocks |
| `Infrastructure\Http\EditLockController` | The endpoints |
| `Infrastructure\Http\EditLockRequest` | Whether a request comes from the controller of this site: its header, the browser's `Sec-Fetch-Site` and the stateless CSRF token `phil_edit_lock` unless the method is safe; the name of the token's cookie |
| `Infrastructure\Http\Routes` | The names of the routes, keyed as the controller names them |
| `Infrastructure\Http\ClientConfig` | The controller's configuration for a resource and a holder |
| `Infrastructure\DependencyInjection\EditLockServices` | The bundle's services |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | Stops the container with a message that says what to change when `framework.csrf_protection` is false |

## Files

| File | Role |
|---|---|
| `config/routes.php` | The routes, imported by the application under its prefix |
| `assets/src/collaboration_controller.js` | The Stimulus controller |
| `assets/src/lib/leases.js`, `csrf.js`, `snapshot.js`, `presence.js`, `streams.js`, `i18n.js` | Its modules, described in [JavaScript](javascript.md#modules) |
| `assets/styles/edit-lock.css` | The roster's stylesheet |
| `assets/package.json` | The controller declared for Symfony UX |

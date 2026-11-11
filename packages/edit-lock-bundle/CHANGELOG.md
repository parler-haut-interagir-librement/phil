# Changelog

All notable changes to `phil/edit-lock-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `LeaseKey` and `Holder`: what a lease is on — a resource, or one block of it by its identifier — and who holds it;
  `InvalidLease` for a key, a holder or a duration a lease cannot be made of, and `EditLockException` for every
  exception of the bundle.
- `Lease`: one holder's right to write a key until a moment, granted, renewed, passed on or restored, versioned so
  that a write replaces only the version read, and mapped for Doctrine as the table `phil_edit_lease`;
  `LeasedResource`, the row of `phil_edit_lease_resource` each claim on a resource locks.
- `EditLocks`: acquire, heartbeat, release, leave, take over, holds, presence and purge, the whole resource and its
  parts excluding each other, each claim run while no other claim on its resource runs, a lost race or a claim the
  store gave up tried again three times, the policy asked again at each heartbeat, which releases the lease of a
  holder no longer allowed; `EditPolicy` and `DefaultEditPolicy`; `LeaseHeld`,
  `LeaseLost` and `EditRefused`.
- `BlockSaves`: a block checked by `phil/ujo`, written only by the holder of its lease or of the whole resource
  whom the policy still lets edit it, handed to the application's `BlockStore`, and told to the other writers with its place in the document;
  `InvalidBlock`.
- `Broadcaster` and `NullBroadcaster`; `MercureBroadcaster` and `TurboStreams`: presence and saved and removed blocks
  pushed to one topic per resource as Turbo Streams, private by default, a hub that cannot be reached logged.
- `LeaseRepository`, written by compare-and-set, its claims on one resource run one at a time by `exclusively()`;
  `DoctrineLeaseRepository`, by plain queries that leave the entity manager open when a race is lost, each claim in
  a transaction that locks the resource's row, a deadlock or a wait cut short undone and given up;
  `InMemoryLeaseRepository`, for tests.
- `PhilEditLockBundle`, configured under `phil_edit_lock`: `seconds`, `store`, `connection`, `holder_provider`,
  `policy`, `block_store` and `mercure` (`enabled`, on when MercureBundle is registered, `hub`, `topic_prefix`,
  `private`); the tables mapped for Doctrine; the controller's CSRF token added to the stateless ones of
  FrameworkBundle, and `CsrfProtectionPass`, which stops the container with a message when `framework.csrf_protection`
  is false; the controller and the stylesheet mapped for AssetMapper.
- `HolderProvider`, `EditLockController`, `EditLockRequest` and the routes in `config/routes.php`: acquire,
  heartbeat, release, take over, leave, presence, save and remove, each for the controller's own header, the
  browser's `Sec-Fetch-Site`, Symfony's stateless CSRF check of the token `phil_edit_lock` on each `POST` (the
  presence, a `GET`, carries none), a holder and the application's policy.
- `ClientConfig`: the controller's configuration for one resource and one holder — the endpoints, the hub's
  subscription URL by the protocol version of the hub, the topic, the duration, the takeover offered, the name of the
  CSRF cookie.
- The `collaboration` Stimulus controller: the lease follows the writer from block to block and is kept by
  heartbeats, the held block saved or removed as the editor writes, the blocks of others closed and named, their saves
  applied to the block editor, a roster with takeover, presence asked at each heartbeat without Mercure, every lease
  given up when the page closes, labels in English and French, a new CSRF token with each `POST`. Its pure modules —
  lease requests and their double-submitted token, snapshots of the
  document, presence, Turbo Streams, labels — are tested under Node's test runner.
- The stylesheet `edit-lock.css`, on custom properties that follow PHIL UX or the page's own colours.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

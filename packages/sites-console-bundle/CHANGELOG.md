# Changelog

All notable changes to `phil/sites-console-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `PhilSitesConsoleBundle` and its configuration `phil_sites_console`: the clock, the console — key, store, entity
  manager, HTTP client, time limits, watched packages, expected versions, retention, roles, layout, kit
  subdirectory, next key — and the agent — site, keys, next console key, lock file, reported packages, allowed setting
  keys, applier, runner, health, cache pool, key pool, lock factory, clock skew.
- The signed protocol: `SigningKey` and `VerifyingKey` on Ed25519, `Protocol` with its headers, actions, signed
  request and response messages and nonces, and `Base64Url`.
- The rotation of the console key without cutting any site off: `ConsoleKeys`, the current and next console keys an
  agent accepts; `ConsoleKeyRing` on the port `ConsoleKeyStore`, with `CacheConsoleKeyStore` and
  `InMemoryConsoleKeyStore`, where the configured keys win once they change and a change the store did not keep fails
  the rotation with `StateNotKept`, read back after each write, so that a retired key is never reported retired while
  still trusted; the action `keys` with `KeyRequest`,
  staging a key by the current key and retiring one by the key that remains, once per identifier; and on the console
  `KeyRotation` with its Doctrine and memory stores, `ConsoleSigning`, which switches to the next key once every site
  holds it, and `KeyRotations`, which stops at the first failure, journals each step and resumes, with the fuzz target
  `key-request`.
- The documents of the protocol read with bounds by `Json` and `Fields`: `StatusDocument` with `HealthSummary` and
  `CheckState`, `Settings` and `ConfigurationRequest`, `UpdateRequest`, `Outcome`, with the fuzz target
  `status-document`.
- The agent: `RequestVerifier` — site, date window, nonce, signature by either accepted console key, replay — and
  `AgentEndpoints` — status, pushed settings through `ConfigurationApplier` with an allow-list of keys, updates through
  `UpdateRunner`, console keys, each once per identifier through `phil/idempotency` —, served by `AgentController` and
  the routes of `config/routes/agent.php`, which read the body to one byte past 64 KiB and no further before the
  signature, whatever `Content-Length` says, and answer `503` when a nonce cannot be kept.
- `LockFilePackages`, the versions of `composer.lock`, with the fuzz target `composer-lock`; `MonitoringSiteHealth`,
  the health from `phil/monitoring-bundle`, chosen by `MonitoringPass`, and `UnknownSiteHealth`; `CacheNonceStore`, which checks and
  writes each nonce under a lock of the Symfony Lock component, so that a nonce passes once even on two servers at the
  same time, and fails when the pool does not save it.
- The console: the models `Site`, `Snapshot`, `ConfigurationPush`, `UpdatePlan` and `JournalEntry`, their Doctrine and
  memory stores, `AgentUrl` read by `phil/url`, `Packages` for names, versions and patterns.
- `SiteRegistry`, `Puller`, `Fleet` with `SiteOverview` and `VersionMatrix`, `AlertRules` and the event
  `SiteAlertRaised`, `ConfigurationPushes`, `UpdatePlans` in batches that stop at the first failure, and `Journal`.
- `HttpAgentClient`, which signs each request, follows no redirect, bounds the time and the size of the answer and
  verifies its signature.
- The screens of `ConsoleController` and `config/routes/console.php` — dashboard, site, versions, journal, pushes,
  update plans —, drawn with the recipes `badge`, `button`, `card` and `table` of the kit, in English and French, with
  roles from the user's token and a CSRF token on every action.
- The commands `phil:sites-console:keys`, `keys:rotate`, `site:add`, `site:remove`, `pull`, `push`, `update:plan`,
  `update:run` and `purge`, and the messages `PullSite`, `DeliverConfigurationPush` and `RunUpdatePlan` with their
  handlers.
- The Flex recipe, writing both halves commented out.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.

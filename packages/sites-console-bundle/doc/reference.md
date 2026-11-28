# Reference

## Bundle

| Class | Role |
|---|---|
| `PhilSitesConsoleBundle` | The bundle and its configuration `phil_sites_console`; maps the console's tables for Doctrine. |
| `Infrastructure\DependencyInjection\SitesConsoleServices` | The services, from the processed configuration. |
| `Infrastructure\DependencyInjection\MonitoringPass` | The agent's health from `phil/monitoring-bundle` when its bundle is registered. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | The subdirectory of the kit's recipes, from the `ux_toolkit` configuration. |

## Domain

| Class | Role |
|---|---|
| `Domain\Model\Site` | A site: identifier, name, agent address, public key, groups. |
| `Domain\Model\Snapshot` | What a pull read of a site, or the error that kept it from being read. |
| `Domain\Model\ConfigurationPush` | Settings pushed to sites, and each delivery. |
| `Domain\Model\UpdatePlan` | A package brought to a version on sites in batches; its steps are the report. |
| `Domain\Model\JournalEntry` | One line of the journal. |
| `Domain\Model\KeyRotation` | A rotation of the console key: the previous and next public keys, the phase, each site's steps. |
| `Domain\Contract\SiteRepository`, `SnapshotRepository`, `ConfigurationPushRepository`, `UpdatePlanRepository`, `JournalRepository`, `KeyRotationRepository` | The stores. |
| `Domain\Signature\SigningKey`, `VerifyingKey` | The two halves of an Ed25519 key. |
| `Domain\Signature\ConsoleKeys` | The console keys an agent accepts: the current one, and the next one during a rotation. |
| `Domain\Signature\Protocol` | The headers, actions and signed messages of the protocol, and its nonces. |
| `Domain\Signature\Base64Url` | Base64 in the URL alphabet. |
| `Domain\Document\Json`, `Fields` | The JSON of the protocol, read with bounds. |
| `Domain\ValueObject\AgentUrl` | The address of an agent, read by `phil/url`. |
| `Domain\ValueObject\StatusDocument`, `HealthSummary`, `CheckState` | What `status` answers. |
| `Domain\ValueObject\Settings`, `ConfigurationRequest` | What `configuration` gets. |
| `Domain\ValueObject\UpdateRequest` | What `update` gets. |
| `Domain\ValueObject\KeyRequest` | What `keys` gets: the step's identifier, `stage` or `retire`, and a public key. |
| `Domain\ValueObject\Outcome` | What `configuration`, `update` and `keys` answer, and what a runner returns. |
| `Domain\ValueObject\Alert`, `Delivery`, `UpdateStep` | An alert, the delivery of a push to a site, the step of a plan. |
| `Domain\Alert\AlertRules` | When a site needs attention, and the newest versions across sites. |
| `Domain\Version\Packages` | Package names, versions, their order, patterns. |
| `Domain\Status\HealthState`, `StepOutcome`, `PlanState`, `DeliveryOutcome`, `JournalAction`, `AlertKind`, `KeyOperation`, `RotationPhase`, `RotationStep` | The states. |
| `Domain\Exception\SitesConsoleException` | The interface of every exception of the bundle: `InvalidValue`, `MalformedDocument`, `SignatureRefused`, and in the application `AgentFailure`, `NotFound`, `ActionUnavailable`, `ConfigurationRefused`, `KeyRefused`, `RotationRefused`, `StateNotKept`. |

## Application

| Class | Role |
|---|---|
| `Application\Agent\RequestVerifier` | Checks a request of the console on the agent. |
| `Application\Agent\VerifiedRequest` | A verified request: its nonce and the console key that signed it. |
| `Application\Agent\ConsoleKeyRing` | The console keys the agent accepts, from its configuration and its key store. |
| `Application\Agent\AgentEndpoints` | What the agent does once a request is verified. |
| `Application\Console\SiteRegistry` | Registers, changes, selects and removes sites. |
| `Application\Console\Puller` | Pulls a site and raises its new alerts. |
| `Application\Console\Fleet` | The overview, a site and the version matrix, from the latest snapshots. |
| `Application\Console\ConfigurationPushes` | Creates and delivers pushes. |
| `Application\Console\UpdatePlans` | Plans and runs updates. |
| `Application\Console\Journal` | Writes and reads the journal. |
| `Application\Console\ConsoleSigning` | The key the console signs with: the current one, or the next one once a rotation switched to it. |
| `Application\Console\KeyRotations` | Rotates the console key on every site, stopping at the first failure and resuming. |
| `Application\View\SiteOverview`, `VersionMatrix` | What the screens show. |
| `Application\Contract\AgentClient`, `ConfigurationApplier`, `UpdateRunner`, `SiteHealth`, `InstalledPackages`, `NonceStore`, `ConsoleKeyStore` | The ports. |
| `Application\Message\PullSite`, `DeliverConfigurationPush`, `RunUpdatePlan` | The messages, identifiers only. |
| `Application\Event\SiteAlertRaised` | A new alert of a pulled site. |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Http\HttpAgentClient` | The agents over HTTP, signed both ways. |
| `Infrastructure\Http\AgentController` | The agent's endpoints. |
| `Infrastructure\Http\ConsoleController` | The console's screens and actions. |
| `Infrastructure\Composer\LockFilePackages` | The packages of `composer.lock`. |
| `Infrastructure\Monitoring\MonitoringSiteHealth`, `UnknownSiteHealth` | The health from `phil/monitoring-bundle`, or unknown. |
| `Infrastructure\Cache\CacheNonceStore` | The nonces in a PSR-6 pool. |
| `Infrastructure\Cache\CacheConsoleKeyStore`, `Infrastructure\Memory\InMemoryConsoleKeyStore` | The agent's console keys in a PSR-6 pool, or in memory for tests. |
| `Infrastructure\Doctrine\Doctrine*Repository` | The Doctrine stores. |
| `Infrastructure\Memory\InMemory*Repository` | The memory stores, for tests. |
| `Infrastructure\Messenger\PullSiteHandler`, `DeliverConfigurationPushHandler`, `RunUpdatePlanHandler` | The handlers. |
| `Infrastructure\Twig\SitesConsoleExtension`, `KitComponents` | `phil_sites_console_kit()` and `phil_sites_console_variant()`. |

## Commands

| Command | Does |
|---|---|
| `phil:sites-console:keys` | Prints a new key pair. |
| `phil:sites-console:keys:rotate [--actor]` | Rotates the console key on every site, or prints a next key to configure. |
| `phil:sites-console:site:add <id> <url> <public-key> [--name] [--group]… [--actor]` | Registers or changes a site. |
| `phil:sites-console:site:remove <id> [--actor]` | Removes a site and its snapshots. |
| `phil:sites-console:pull [--site]… [--group]` | Pulls sites and prints what each answered. |
| `phil:sites-console:push [<settings>] [--site]… [--retry] [--actor]` | Pushes settings, or delivers a push again. |
| `phil:sites-console:update:plan <package> <version> [--site]… [--group] [--batch-size] [--actor]` | Plans an update. |
| `phil:sites-console:update:run <plan> [--batches] [--actor]` | Runs a plan and prints its report. |
| `phil:sites-console:purge` | Removes the snapshots older than the retention. |

## Routes

`config/routes/agent.php`: `phil_sites_console_agent_status` (`GET /status`), `phil_sites_console_agent_configuration`
(`POST /configuration`), `phil_sites_console_agent_update` (`POST /update`), `phil_sites_console_agent_keys` (`POST /keys`).

`config/routes/console.php`: `phil_sites_console_dashboard` (`GET /`), `phil_sites_console_pull_all` (`POST /pull`),
`phil_sites_console_versions` (`GET /versions`), `phil_sites_console_journal` (`GET /journal`),
`phil_sites_console_site` (`GET /sites/{id}`), `phil_sites_console_pull` (`POST /sites/{id}/pull`),
`phil_sites_console_pushes` (`GET /pushes`), `phil_sites_console_push` (`GET /pushes/{id}`),
`phil_sites_console_deliver` (`POST /pushes/{id}/deliver`), `phil_sites_console_updates` (`GET /updates`),
`phil_sites_console_update` (`GET /updates/{id}`), `phil_sites_console_run` (`POST /updates/{id}/run`).

## Services

The public services are the application services of the console — `SiteRegistry`, `Fleet`, `Puller`,
`ConfigurationPushes`, `UpdatePlans`, `KeyRotations` —, the agent's `AgentEndpoints`, the controllers, and the
repositories under their `Domain\Contract` interfaces. The keys are `phil_sites_console.console_key`,
`phil_sites_console.console_next_key`, `phil_sites_console.agent_key`, `phil_sites_console.console_public_key` and
`phil_sites_console.console_next_public_key`, the next ones only when they are configured.

## Tables

| Table | Columns |
|---|---|
| `phil_sites_console_site` | `id`, `name`, `agent_url`, `public_key`, `site_groups` (JSON), `created_at` |
| `phil_sites_console_snapshot` | `id` (ULID), `site_id`, `taken_at`, `reachable`, `health`, `checks` (JSON), `packages` (JSON), `php_version`, `symfony_version`, `error`; index on `site_id`, `taken_at` |
| `phil_sites_console_push` | `id` (ULID), `settings` (JSON), `deliveries` (JSON list), `created_at`, `created_by` |
| `phil_sites_console_update_plan` | `id` (ULID), `package`, `version`, `batch_size`, `state`, `steps` (JSON list), `created_at`, `created_by` |
| `phil_sites_console_journal` | `id` (ULID), `occurred_at`, `actor`, `action`, `site_id`, `reference`, `succeeded`, `message`; index on `site_id` |
| `phil_sites_console_key_rotation` | `id` (ULID), `previous_key`, `next_key`, `phase`, `progress` (JSON list), `started_at`, `started_by`, `updated_at`; index on `next_key` |

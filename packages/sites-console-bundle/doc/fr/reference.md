<!-- phil-dev source-sha256=284a01f31b26da1467ab88c59ef09934bd8f253084bff10cbc0d4e550cc61e3b -->
# Référence

## Bundle

| Classe | Rôle |
|---|---|
| `PhilSitesConsoleBundle` | Le bundle et sa configuration `phil_sites_console` ; associe les tables de la console à Doctrine. |
| `Infrastructure\DependencyInjection\SitesConsoleServices` | Les services, à partir de la configuration traitée. |
| `Infrastructure\DependencyInjection\MonitoringPass` | La santé de l'agent tirée de `phil/monitoring-bundle` quand son bundle est enregistré. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Le sous-répertoire des recettes du kit, tiré de la configuration `ux_toolkit`. |

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Model\Site` | Un site : identifiant, nom, adresse de l'agent, clé publique, groupes. |
| `Domain\Model\Snapshot` | Ce qu'une interrogation a lu d'un site, ou l'erreur qui a empêché de le lire. |
| `Domain\Model\ConfigurationPush` | Des réglages poussés vers des sites, et chaque livraison. |
| `Domain\Model\UpdatePlan` | Un paquet amené à une version sur des sites, par lots ; ses étapes forment le rapport. |
| `Domain\Model\JournalEntry` | Une ligne du journal. |
| `Domain\Contract\SiteRepository`, `SnapshotRepository`, `ConfigurationPushRepository`, `UpdatePlanRepository`, `JournalRepository`, `KeyRotationRepository` | Les dépôts. |
| `Domain\Model\KeyRotation` | Une rotation de la clé de la console : les clés publiques précédente et suivante, la phase, les étapes de chaque site. |
| `Domain\Signature\SigningKey`, `VerifyingKey` | Les deux moitiés d'une clé Ed25519. |
| `Domain\Signature\ConsoleKeys` | Les clés de la console qu'un agent accepte : la courante, et la suivante pendant une rotation. |
| `Domain\Signature\Protocol` | Les en-têtes, les actions et les messages signés du protocole, et ses nonces. |
| `Domain\Signature\Base64Url` | Du base64 dans l'alphabet des URL. |
| `Domain\Document\Json`, `Fields` | Le JSON du protocole, lu avec des bornes. |
| `Domain\ValueObject\AgentUrl` | L'adresse d'un agent, lue par `phil/url`. |
| `Domain\ValueObject\StatusDocument`, `HealthSummary`, `CheckState` | Ce que `status` répond. |
| `Domain\ValueObject\Settings`, `ConfigurationRequest` | Ce que `configuration` reçoit. |
| `Domain\ValueObject\UpdateRequest` | Ce que `update` reçoit. |
| `Domain\ValueObject\KeyRequest` | Ce que `keys` reçoit : l'identifiant de l'étape, `stage` ou `retire`, et une clé publique. |
| `Domain\ValueObject\Outcome` | Ce que `configuration`, `update` et `keys` répondent, et ce qu'un exécuteur retourne. |
| `Domain\ValueObject\Alert`, `Delivery`, `UpdateStep` | Une alerte, la livraison d'une poussée à un site, l'étape d'un plan. |
| `Domain\Alert\AlertRules` | Quand un site demande de l'attention, et les versions les plus récentes d'un site à l'autre. |
| `Domain\Version\Packages` | Les noms de paquets, les versions, leur ordre, les motifs. |
| `Domain\Status\HealthState`, `StepOutcome`, `PlanState`, `DeliveryOutcome`, `JournalAction`, `AlertKind`, `KeyOperation`, `RotationPhase`, `RotationStep` | Les états. |
| `Domain\Exception\SitesConsoleException` | L'interface de chaque exception du bundle : `InvalidValue`, `MalformedDocument`, `SignatureRefused`, et dans l'application `AgentFailure`, `NotFound`, `ActionUnavailable`, `ConfigurationRefused`, `KeyRefused`, `RotationRefused`, `StateNotKept`. |

## Application

| Classe | Rôle |
|---|---|
| `Application\Agent\RequestVerifier` | Vérifie une requête de la console sur l'agent. |
| `Application\Agent\VerifiedRequest` | Une requête vérifiée : son nonce et la clé de la console qui l'a signée. |
| `Application\Agent\ConsoleKeyRing` | Les clés de la console que l'agent accepte, tirées de sa configuration et de son magasin de clés. |
| `Application\Agent\AgentEndpoints` | Ce que l'agent fait une fois une requête vérifiée. |
| `Application\Console\SiteRegistry` | Enregistre, modifie, sélectionne et retire des sites. |
| `Application\Console\Puller` | Interroge un site et lève ses nouvelles alertes. |
| `Application\Console\Fleet` | La vue d'ensemble, un site et la matrice des versions, à partir des derniers instantanés. |
| `Application\Console\ConfigurationPushes` | Crée et livre des poussées. |
| `Application\Console\UpdatePlans` | Planifie et exécute des mises à jour. |
| `Application\Console\Journal` | Écrit et lit le journal. |
| `Application\Console\ConsoleSigning` | La clé avec laquelle la console signe : la courante, ou la suivante une fois qu'une rotation y a basculé. |
| `Application\Console\KeyRotations` | Fait la rotation de la clé de la console sur chaque site, en s'arrêtant au premier échec et en reprenant. |
| `Application\View\SiteOverview`, `VersionMatrix` | Ce que les écrans montrent. |
| `Application\Contract\AgentClient`, `ConfigurationApplier`, `UpdateRunner`, `SiteHealth`, `InstalledPackages`, `NonceStore`, `ConsoleKeyStore` | Les ports. |
| `Application\Message\PullSite`, `DeliverConfigurationPush`, `RunUpdatePlan` | Les messages, des identifiants seulement. |
| `Application\Event\SiteAlertRaised` | Une nouvelle alerte d'un site interrogé. |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Http\HttpAgentClient` | Les agents par HTTP, signés dans les deux sens. |
| `Infrastructure\Http\AgentController` | Les points de terminaison de l'agent. |
| `Infrastructure\Http\ConsoleController` | Les écrans et les actions de la console. |
| `Infrastructure\Composer\LockFilePackages` | Les paquets de `composer.lock`. |
| `Infrastructure\Monitoring\MonitoringSiteHealth`, `UnknownSiteHealth` | La santé tirée de `phil/monitoring-bundle`, ou inconnue. |
| `Infrastructure\Cache\CacheNonceStore` | Les nonces dans un pool PSR-6. |
| `Infrastructure\Cache\CacheConsoleKeyStore`, `Infrastructure\Memory\InMemoryConsoleKeyStore` | Les clés de la console de l'agent dans un pool PSR-6, ou en mémoire pour les tests. |
| `Infrastructure\Doctrine\Doctrine*Repository` | Les dépôts Doctrine. |
| `Infrastructure\Memory\InMemory*Repository` | Les dépôts en mémoire, pour les tests. |
| `Infrastructure\Messenger\PullSiteHandler`, `DeliverConfigurationPushHandler`, `RunUpdatePlanHandler` | Les handlers. |
| `Infrastructure\Twig\SitesConsoleExtension`, `KitComponents` | `phil_sites_console_kit()` et `phil_sites_console_variant()`. |

## Commandes

| Commande | Effet |
|---|---|
| `phil:sites-console:keys` | Affiche une nouvelle paire de clés. |
| `phil:sites-console:keys:rotate [--actor]` | Fait la rotation de la clé de la console sur chaque site, ou affiche une clé suivante à configurer. |
| `phil:sites-console:site:add <id> <url> <public-key> [--name] [--group]… [--actor]` | Enregistre ou modifie un site. |
| `phil:sites-console:site:remove <id> [--actor]` | Retire un site et ses instantanés. |
| `phil:sites-console:pull [--site]… [--group]` | Interroge des sites et affiche ce que chacun a répondu. |
| `phil:sites-console:push [<settings>] [--site]… [--retry] [--actor]` | Pousse des réglages, ou livre une poussée de nouveau. |
| `phil:sites-console:update:plan <package> <version> [--site]… [--group] [--batch-size] [--actor]` | Planifie une mise à jour. |
| `phil:sites-console:update:run <plan> [--batches] [--actor]` | Exécute un plan et affiche son rapport. |
| `phil:sites-console:purge` | Retire les instantanés plus vieux que la durée de conservation. |

## Routes

`config/routes/agent.php` : `phil_sites_console_agent_status` (`GET /status`), `phil_sites_console_agent_configuration`
(`POST /configuration`), `phil_sites_console_agent_update` (`POST /update`), `phil_sites_console_agent_keys` (`POST /keys`).

`config/routes/console.php` : `phil_sites_console_dashboard` (`GET /`), `phil_sites_console_pull_all` (`POST /pull`),
`phil_sites_console_versions` (`GET /versions`), `phil_sites_console_journal` (`GET /journal`),
`phil_sites_console_site` (`GET /sites/{id}`), `phil_sites_console_pull` (`POST /sites/{id}/pull`),
`phil_sites_console_pushes` (`GET /pushes`), `phil_sites_console_push` (`GET /pushes/{id}`),
`phil_sites_console_deliver` (`POST /pushes/{id}/deliver`), `phil_sites_console_updates` (`GET /updates`),
`phil_sites_console_update` (`GET /updates/{id}`), `phil_sites_console_run` (`POST /updates/{id}/run`).

## Services

Les services publics sont les services d'application de la console — `SiteRegistry`, `Fleet`, `Puller`,
`ConfigurationPushes`, `UpdatePlans`, `KeyRotations` —, les `AgentEndpoints` de l'agent, les contrôleurs, et les dépôts
sous leurs interfaces `Domain\Contract`. Les clés sont `phil_sites_console.console_key`,
`phil_sites_console.console_next_key`, `phil_sites_console.agent_key`, `phil_sites_console.console_public_key` et
`phil_sites_console.console_next_public_key`, les suivantes seulement quand elles sont configurées.

## Tables

| Table | Colonnes |
|---|---|
| `phil_sites_console_site` | `id`, `name`, `agent_url`, `public_key`, `site_groups` (JSON), `created_at` |
| `phil_sites_console_snapshot` | `id` (ULID), `site_id`, `taken_at`, `reachable`, `health`, `checks` (JSON), `packages` (JSON), `php_version`, `symfony_version`, `error` ; index sur `site_id`, `taken_at` |
| `phil_sites_console_push` | `id` (ULID), `settings` (JSON), `deliveries` (liste JSON), `created_at`, `created_by` |
| `phil_sites_console_update_plan` | `id` (ULID), `package`, `version`, `batch_size`, `state`, `steps` (liste JSON), `created_at`, `created_by` |
| `phil_sites_console_journal` | `id` (ULID), `occurred_at`, `actor`, `action`, `site_id`, `reference`, `succeeded`, `message` ; index sur `site_id` |
| `phil_sites_console_key_rotation` | `id` (ULID), `previous_key`, `next_key`, `phase`, `progress` (JSON list), `started_at`, `started_by`, `updated_at` ; index sur `next_key` |

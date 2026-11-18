# Reference

Every class of the bundle, with its role. The types of the library — `IncidentMode`, `Preset`, `Activation`,
`Contract\EstablishedMembers` and the others — are in its own reference.

| Class | Role |
|---|---|
| `PhilIncidentModeBundle` | The bundle and its configuration `phil_incident_mode` |
| `Attribute\SuspendedDuring` | Refuses a controller while a restriction it names applies |
| `Application\Checkpoint` | Whether a restriction applies to the member of the request, the suspensions, `guard()` |
| `Application\Suspension` | A restriction that applies, and the moment it stops applying |
| `Application\Exception\SuspendedByIncident` | The refusal: an HTTP error with `Retry-After` |
| `Application\Contract\CurrentMember` | The member of the request, by a stable identifier |
| `Application\DurationInput` | A duration as a person writes it, and back |
| `Infrastructure\Security\SecurityCurrentMember` | The identifier of Symfony Security's user |
| `Infrastructure\Http\CheckpointListener` | Checks the attribute and the route default; renders a refusal |
| `Infrastructure\Http\SuspensionPage` | The refusal page, the status page, the notice fragment |
| `Infrastructure\Http\IncidentAdminController` | The administration page and its forms |
| `Infrastructure\Http\AdminRequest` | The stateless CSRF check of the administration |
| `Infrastructure\Form\SuspendedDuringTypeExtension` | The option `suspended_during` of every form |
| `Infrastructure\Translation\IncidentWording` | What is suspended and until when, in the visitor's language and the configured time zone |
| `Infrastructure\Twig\IncidentModeExtension` | The Twig functions `phil_incident_*` |
| `Infrastructure\Twig\KitComponents` | The names of the recipes of the kit |
| `Infrastructure\Console\StartIncidentCommand` | `phil:incident:start` |
| `Infrastructure\Console\ExtendIncidentCommand` | `phil:incident:extend` |
| `Infrastructure\Console\LiftIncidentCommand` | `phil:incident:lift`, and the sweep with `--expired` |
| `Infrastructure\Console\ListIncidentsCommand` | `phil:incident:list`, the journal and the presets |
| `Infrastructure\Console\Moments` | The moments of the commands, in UTC |
| `Infrastructure\Scheduler\IncidentSchedule` | The recurring sweep of Symfony Scheduler |
| `Infrastructure\Audit\AuditIncidents` | The incidents written to the trail of phil/audit-bundle |
| `Infrastructure\Privacy\IncidentPrivacy` | The incidents of a member exported and erased through phil/privacy-bundle |
| `Infrastructure\DependencyInjection\IncidentModeServices` | The services, from the configuration |
| `Infrastructure\DependencyInjection\IncidentModePass` | The lifting without a bus, the words without a translator, the subdirectory of the kit |

## Services

| Service | Class |
|---|---|
| `Phil\IncidentMode\IncidentMode` | The library's, with the configured presets, the store, the `clock` and the `event_dispatcher` |
| `Phil\IncidentMode\Contract\ActivationStore` | Alias of `Phil\IncidentMode\Dbal\DbalActivationStore` on the configured connection |
| `Phil\IncidentMode\Contract\EstablishedMembers` | Alias of `established_members`, or of `NoEstablishedMembers` |
| `Phil\IncidentMode\Contract\LiftScheduler` | Alias of `MessengerLiftScheduler`, or of `NoLiftScheduler` without a bus |
| `Phil\IncidentModeBundle\Application\Contract\CurrentMember` | Alias of `current_member`, or of `SecurityCurrentMember` |
| `Phil\IncidentModeBundle\Application\Checkpoint` | The checkpoints |

## Routes

| Route | Path in its file |
|---|---|
| `phil_incident_mode_admin`, `_admin_start`, `_admin_extend`, `_admin_lift` | `config/routes/admin.php` |
| `phil_incident_mode_status`, `phil_incident_mode_notice` | `config/routes/status.php` |

## Templates

| Template | What it is |
|---|---|
| `@PhilIncidentMode/layout.html.twig` | The default layout |
| `@PhilIncidentMode/admin/incidents.html.twig` | The administration page |
| `@PhilIncidentMode/suspended.html.twig` | The refusal page |
| `@PhilIncidentMode/status.html.twig` | The status page |
| `@PhilIncidentMode/_notice.html.twig` | The notice of what is suspended |
| `@PhilIncidentMode/_banner.html.twig` | The banner of a site's layout |

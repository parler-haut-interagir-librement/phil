# Incident mode

The incident mode is `phil/incident-mode-bundle`'s: its table, its presets, its console commands, its administration
page, its automatic lifting and the checkpoints that refuse a sign-up, an invitation or a form while an incident
suspends them. The moderation does not plug the library in itself; when that bundle is registered, the queue offers
its page and proposes an incident when reports surge.

```sh
composer require phil/incident-mode-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\ModerationBundle\PhilModerationBundle::class => ['all' => true],
    Phil\IncidentModeBundle\PhilIncidentModeBundle::class => ['all' => true],
];
```

The presets, the established members, the role of the administration and the lifting are configured under
`phil_incident_mode`, as the documentation of `phil/incident-mode-bundle` shows; its routes are imported as it shows
too:

```yaml
# config/routes/phil_incident_mode.yaml
phil_incident_mode_admin:
    resource: '@PhilIncidentModeBundle/config/routes/admin.php'
    prefix: /admin/incidents
```

## In the queue

| When | The queue shows |
|---|---|
| The member holds the role of `phil_incident_mode.admin.role` | A link to the page of the incidents, `phil_incident_mode_admin`, beside the queue |
| That role, `surge.reports` reports within `surge.within` seconds, no incident in force | An alert that reports are surging, with a link to that page |
| Another moderator | Neither: the page is not theirs |
| The administration of the incident mode off (`admin: false`), or the bundle not registered | Neither |

`Infrastructure\IncidentMode\IncidentProposal` answers both questions from the `IncidentMode` of the bundle, the
report surge of the moderation and the role, which the bundle publishes as the container parameter
`phil_incident_mode.admin.role` while its administration is on. It keeps nothing between requests.

## In the pages of the site

The checkpoints are the bundle's, and so are their functions: a site refuses a sign-up, an invitation or a form while
an incident suspends it, and shows a notice, with them.

```php
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;

#[SuspendedDuring('signups', methods: ['POST'])]
public function register(Request $request): Response
```

```yaml
# a route refused while invitations are suspended
invite:
    path: /invite
    controller: App\Controller\InvitationController
    defaults: { _suspended_during: invitations }
```

```php
// a form invalid while new posts are suspended, with the error that says until when
$resolver->setDefault('suspended_during', ['new-posts']);
```

```twig
{% if phil_incident_suspended('signups') %}
    <p>Sign-ups are suspended for now.</p>
{% endif %}
```

A refused request answers 503, or the status the bundle's configuration chooses, with `Retry-After`. Established
members are spared from the restrictions `phil_incident_mode.spare_established` lists, through the port
`Phil\IncidentMode\Contract\EstablishedMembers`, which `phil_incident_mode.established_members` names.

## Personal data

The incidents a member started or lifted answer the access and erasure requests of `phil/privacy-bundle` through the
incident mode's own exporter and eraser; the moderation's records leave them out ([Personal data](privacy.md)).

## Upgrading from 0.2

The bundle no longer wires `phil/incident-mode` itself:

| In 0.2 | From 0.3 |
|---|---|
| `phil_moderation.incident.role` | `phil_incident_mode.admin.role` |
| `phil_moderation.incident.presets`, durations in minutes | `phil_incident_mode.presets`, by name, durations written `90m`, `2h` or `1d` |
| `phil_moderation.incident.without` | `phil_incident_mode.without_presets` |
| `phil_moderation.incident.spare_established` | `phil_incident_mode.spare_established`, with `established_members` naming the service of `EstablishedMembers` |
| The pages `phil_moderation_incidents`, `phil_moderation_incident_start`, `phil_moderation_incident_lift` | The page `phil_incident_mode_admin` of the bundle, which also extends an incident |
| `ModerationAdmin::incidentsMenuItem()` | A menu item to the route `phil_incident_mode_admin` |
| `phil_moderation_suspended('signups')` | `phil_incident_suspended('signups')` |
| `CurrentMemberRestrictions::appliesNow()` | `Phil\IncidentModeBundle\Application\Checkpoint::applies()`, or `guard()`, which refuses |
| The incidents in the moderation's export as `incidents` | The export of the incident mode |

The table `phil_incident_activation` keeps its name and its rows: the bundle's store reads them on the connection of
`phil_incident_mode.connection`, `default` unless configured. Remove the `incident` key from `phil_moderation`, which
the configuration now refuses, register the bundle, and import its routes.

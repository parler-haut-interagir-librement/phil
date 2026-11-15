# Incident mode

When `phil/incident-mode` 0.2 or later is installed, the bundle wires it; there is nothing to declare.

```sh
composer require phil/incident-mode
```

| Service | What it is |
|---|---|
| `Phil\IncidentMode\Presets` | The built-in presets, with `incident.presets` added and `incident.without` left out |
| `Phil\IncidentMode\Contract\ActivationStore` | The DBAL store, on the connection of the bundle; its table joins the schema the ORM generates |
| `Phil\IncidentMode\Contract\LiftScheduler` | The delayed message `LiftIncident` when Messenger has a bus; nothing otherwise |
| `Phil\IncidentMode\IncidentMode` | The incident mode, public, its events on the event dispatcher |
| `LiftIncidentHandler`, `LiftExpiredIncidentsHandler` | The handlers of the lifting, when Messenger has a bus |
| `Phil\IncidentMode\Contract\EstablishedMembers` | `Phil\IncidentMode\NoEstablishedMembers` until the application aliases its own |
| `Phil\IncidentMode\IncidentRestrictions` | Whether a restriction applies to a member, with `incident.spare_established` |
| `Infrastructure\IncidentMode\CurrentMemberRestrictions` | The same question for the member `CurrentMember` reads |

Route `Phil\IncidentMode\Messenger\LiftIncident` to a transport that supports delays, and add the recurring
`LiftExpiredIncidents` to a schedule, as the documentation of `phil/incident-mode` shows.

## In the back office

The page of the incident mode is for the security attribute `incident.role`. It shows the incidents in force with
their end and a button that lifts each, the form that starts one from a preset — **the duration is required**, the
preset proposes it and bounds it —, and the journal of the incidents. The moderator is the actor of what they start or
lift, by their stable identifier.

**The queue proposes the incident mode** to that role when `surge.reports` reports came within `surge.within` seconds
and no incident is in force.

## In the pages of the site

```twig
{% if phil_moderation_suspended('signups') %}
    <p>Sign-ups are suspended for now.</p>
{% endif %}
```

In PHP, the application asks `Phil\IncidentMode\IncidentRestrictions::appliesTo('new-posts', $member)`, with the
member's stable identifier or `null` for a visitor — or `CurrentMemberRestrictions::appliesNow('new-posts')` for the
member of the current request — before it lets someone post, and `IncidentMode::isSuspended('signups')` before it lets
someone sign up.

## Established members

An incident may leave established members free from some of its restrictions: a forum that suspends new posts after a
wave of spam keeps its regulars posting.

```yaml
phil_moderation:
    incident:
        spare_established: [new-posts, new-comments]
```

The application says who is established with a service of its own that implements the port
`Phil\IncidentMode\Contract\EstablishedMembers` of `phil/incident-mode` — the members of a trust level, or of an age
and an activity —, aliased to the port; by default nobody is, so every incident restricts everyone. The port receives
the member's stable identifier, the string `ModerationMember::moderationIdentifier()` returns:

```php
use Phil\IncidentMode\Contract\EstablishedMembers;

final readonly class RegularMembers implements EstablishedMembers
{
    public function __construct(private MemberDirectory $members)
    {
    }

    public function isEstablished(string $member): bool
    {
        return $this->members->postsOf($member) >= 50;
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\IncidentMode\Contract\EstablishedMembers: '@App\Moderation\RegularMembers'
```

| The restriction | Applies to |
|---|---|
| Not suspended | Nobody |
| Suspended, not in `spare_established` | Everyone |
| Suspended and listed | Everyone but the established members |
| Suspended and listed, while `read-only` is in force | Everyone, unless `read-only` is listed too |

A visitor is never spared. The names are those of `Phil\IncidentMode\Restriction`, or the application's own.

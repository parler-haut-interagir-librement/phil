# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Say who is an established member | Implement `Phil\IncidentMode\Contract\EstablishedMembers` and name it in `established_members`, or point the alias of the interface at it |
| Name the member of the request | Implement `Application\Contract\CurrentMember` and name it in `current_member`, or point the alias of the interface at it |
| Add, replace or leave out presets | `presets`, `without_presets` and `built_in_presets` |
| Suspend something of the application's | Name a restriction in a preset, then check it with `#[SuspendedDuring]`, `_suspended_during`, `Checkpoint` or `suspended_during` |
| React to an incident | Listen to `Phil\IncidentMode\Event\IncidentStarted`, `IncidentExtended` and `IncidentLifted` on Symfony's event dispatcher |
| Keep the incidents elsewhere | Implement `Phil\IncidentMode\Contract\ActivationStore` and point the alias of the interface at it |
| Lift at the end another way | Implement `Phil\IncidentMode\Contract\LiftScheduler` and point the alias of the interface at it |
| Start, extend or lift from code | `Phil\IncidentMode\IncidentMode`, autowired |
| Change the words | The messages of the domain `phil_incident_mode`, in `translations/` of the application |
| Change the pages | `layout`, and the templates under `@PhilIncidentMode/` overridden in `templates/bundles/PhilIncidentModeBundle/` |
| Show a notice of the application's own | The Twig functions `phil_incident_suspensions()` and `phil_incident_sentence()` |
| Choose the moment | The `clock` service: the bundle never reads the time itself |

## Who is established

```php
namespace App\Trust;

use Phil\IncidentMode\Contract\EstablishedMembers as Port;

final readonly class EstablishedMembers implements Port
{
    public function __construct(private TrustLevels $levels)
    {
    }

    public function isEstablished(string $member): bool
    {
        return $this->levels->of($member) >= TrustLevels::REGULAR;
    }
}
```

```yaml
phil_incident_mode:
    established_members: App\Trust\EstablishedMembers
    spare_established: [new-posts, new-comments]
```

It is asked only when the answer depends on it: for a member, never for a visitor, and only about a restriction an
incident suspends and the configuration spares.

## Listening to the incidents

```php
use Phil\IncidentMode\Event\IncidentStarted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class TellTheTeam
{
    public function __invoke(IncidentStarted $event): void
    {
        // Post to the team's channel, clear a cache…
    }
}
```

Each event is sent once, even when two processes lift the same incident at once.

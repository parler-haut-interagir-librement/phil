# Extension points

The bundle is in its `0.x` releases: its services, routes and templates may change in a minor release, said in the
changelog. They are meant to be stable from `1.0`.

| To | Do |
|---|---|
| Give the person signed in and their roles | Implement `Application\Contract\CurrentPerson`, and name the service in `phil_shifts.person`. |
| Allocate the places another way | Implement `Phil\Shifts\Allocation\InterestAllocator`, and name the service in `phil_shifts.allocator`. |
| React to a change | Listen to the events of phil/shifts on the event dispatcher. |
| Allocate in a worker | Route `Application\Message\AllocateInterest` to a Messenger transport. |
| Give the birth dates of the volunteers | Implement `Application\Contract\BirthDates`, and name the service in `phil_shifts.minors.birth_dates`. |
| Store elsewhere | Implement `Domain\Repository\RosterRepository`, `SwapRepository`, `GuardianRepository` and `AbsenceRepository`, and alias them. |
| Change a page | Override a template under `templates/bundles/PhilShiftsBundle/`. |
| Change a text | Override a key of the domain `phil_shifts` in the application's translations. |
| Use the pages' services | Inject `Application\Rosters`, `Application\SwapBoard`, `Application\Minors` and `Application\Absences`. |

## The person signed in

```php
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;

final readonly class MemberPerson implements CurrentPerson
{
    public function __construct(private Members $members, private Security $security) {}

    public function current(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    public function roles(): array
    {
        // The trainings of the member, read from the application: FIRST_AID, CASH…
        return $this->members->trainingsOf($this->current());
    }
}
```

```yaml
phil_shifts:
    person: App\Shifts\MemberPerson
```

## The storage

A storage of one's own implements the repositories and keeps their promises: a write over the revision read that
returns false when another came first, the places searchable by volunteer, one guardian per volunteer and one absence per volunteer and shift, the lists
bounded. `Rosters` and
`SwapBoard` take the repositories by their interfaces:

```yaml
services:
    Phil\ShiftsBundle\Domain\Repository\RosterRepository: '@App\Shifts\RedisRosterRepository'
```

## The templates

Each page renders a template of `@PhilShifts`, with the variables its comment lists: `board.html.twig`,
`shift.html.twig`, `mine.html.twig`, `swaps.html.twig`, `manage/index.html.twig`, `manage/roster.html.twig`,
`manage/form.html.twig`, and the partials `_shift.html.twig`, `_actions.html.twig` and `_refused.html.twig`. An
application wraps them in its layout by overriding them.

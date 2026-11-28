# Configuration

```yaml
phil_shifts:
    connection: default
    person: null
    allocator: null
    pages: true
    time_zone: UTC
    rest_minutes: 0
    roles:
        volunteer: null
        coordinator: ROLE_SHIFTS_COORDINATOR
    minors:
        birth_dates: null
        age_of_majority: 18
    absences:
        threshold: null
        window_days: 365
```

| Option | Default | What it does |
|---|---|---|
| `connection` | `default` | The Doctrine connection of the tables. |
| `person` | `null` | The service that gives the person signed in and their roles, a `CurrentPerson`; the user of Symfony Security when null. |
| `allocator` | `null` | The service that allocates the places among the interested, an `InterestAllocator`; the `FairAllocator` when null. |
| `pages` | `true` | Whether the bundle registers its pages and components, with Twig. |
| `time_zone` | `UTC` | The time zone the pages show the shifts in and the form reads them in, an identifier of the IANA database such as `America/Toronto`. |
| `rest_minutes` | `0` | The minutes a volunteer rests between two fixed shifts they hold, 0 to 1,440. |
| `roles.volunteer` | `null` | The role a person needs to volunteer; any person signed in when null. |
| `roles.coordinator` | `ROLE_SHIFTS_COORDINATOR` | The role of the coordinators, who plan shifts, assign volunteers, allocate and approve swaps. |
| `minors.birth_dates` | `null` | The service that gives the birth dates of the volunteers, a `BirthDates`; nobody is a minor when null. |
| `minors.age_of_majority` | `18` | The age from which a volunteer joins a shift without the consent of a guardian, 1 to 30. |
| `absences.threshold` | `null` | The unannounced absences from which a volunteer no longer joins a shift by themselves, 1 to 100; never when null. |
| `absences.window_days` | `365` | The days over which the absences count, 1 to 3,650. |

[Minors and absences](minors-and-absences.md) describes both rules.

The roles are checked through Symfony's access decision manager, so that the role hierarchy of the application counts:

```yaml
security:
    role_hierarchy:
        ROLE_ORGANIZER: [ROLE_SHIFTS_COORDINATOR]
```

The role a shift requires — first aid, cash — is one of the roles of the person signed in: with Symfony Security, the
roles of their token. A `CurrentPerson` of the application may give others, read from a training record.

## What the bundle registers

| Service | Registered |
|---|---|
| The repositories, the transaction, `Conflicts`, `FairAllocator`, `Minors`, `Absences`, `Rosters`, `SwapBoard`, `ShiftsVoter` | always |
| The schema listener | with the ORM |
| `ShiftType` | with Symfony Forms |
| The controllers, the CSRF guard, the person signed in | with `pages` and TwigBundle |
| The components `PhilShifts:ShiftCard` and `PhilShifts:MyShifts` | with `pages`, TwigBundle and Symfony UX Twig Component |
| The handler of `AllocateInterest` | with Messenger |
| `phil:shifts:import` | with Console |
| The exporter and the eraser | with phil/privacy-bundle |

Every service keeps no state between requests, so that the bundle runs in a worker.

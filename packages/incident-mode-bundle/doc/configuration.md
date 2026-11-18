# Configuration

Every option of `phil_incident_mode`, with its default:

```yaml
# config/packages/phil_incident_mode.yaml
phil_incident_mode:
    connection: default
    built_in_presets: true
    without_presets: []
    presets: {}
    spare_established: []
    established_members: null
    current_member: null
    layout: '@PhilIncidentMode/layout.html.twig'
    kit_subdirectory: null
    timezone: null
    checkpoints:
        status: 503
    admin:
        enabled: true
        role: ROLE_ADMIN
        page_size: 20
    lifting:
        messenger: true
        bus: messenger.default_bus
        schedule:
            enabled: true
            name: incident_mode
            frequency: '5 minutes'
    audit:
        enabled: true
        segment: incidents
    privacy: true
```

| Option | What it does |
|---|---|
| `connection` | The DBAL connection of the table `phil_incident_activation`, by its name in the DoctrineBundle configuration. |
| `built_in_presets` | Whether the five built-in presets are offered before the application's; when `false`, only `presets` are. |
| `without_presets` | The built-in presets left out, by name. |
| `presets` | The application's presets, by name, after the built-in ones; a preset of a built-in name replaces it in its place. |
| `spare_established` | The restrictions an incident leaves established members free from; none when empty. |
| `established_members` | The service that implements `Phil\IncidentMode\Contract\EstablishedMembers`; `NoEstablishedMembers`, nobody, when `null`. |
| `current_member` | The service that implements `Application\Contract\CurrentMember`; the identifier of Symfony Security's user when `null`. |
| `layout` | The Twig template the pages extend, with the blocks `title` and `main`. |
| `kit_subdirectory` | Where the recipes of the kit are, under the components directory; the subdirectory of `ux_toolkit` when `null`. |
| `timezone` | The time zone the ends are written in for the visitors, and named; the default time zone of PHP when `null`. |
| `checkpoints.status` | The status of a refusal: 503, 429, 423 or 403, always with `Retry-After`. |
| `admin` | The administration page; `admin: false` leaves it out. `role` may start, extend and lift an incident; `page_size` incidents of the journal on a page, from 1 to 200. |
| `lifting.messenger` | Whether each incident sends its delayed lifting message on `lifting.bus`, when that bus exists. |
| `lifting.schedule` | The recurring sweep, a schedule of Symfony Scheduler named `name`, run every `frequency` as `RecurringMessage::every()` reads it; `enabled: false` leaves it out. |
| `audit` | The incidents written to the segment `segment` of the trail of phil/audit-bundle, when it is registered; `audit: false` leaves them out. |
| `privacy` | Whether the incidents a member started or lifted answer the requests of phil/privacy-bundle, when it is registered. |

## Presets

A preset is a label, the restrictions it suspends — at least one, built-in names or the application's own —, the
duration an incident proposes and the longest it may last, extensions included:

```yaml
phil_incident_mode:
    without_presets: [suspend-new-messages]
    presets:
        suspend-signups: { label: 'Close the sign-ups', restrictions: [signups], proposed: 1h, longest: 1d }
        suspend-listings: { label: 'Suspend new listings', restrictions: [new-listings, uploads], proposed: 4h, longest: 2d }
```

A duration is written as the console reads it: a number of minutes (`90`), or numbers with their unit — `m`, `min`,
`minutes`; `h`, `hour`, `hours`; `d`, `day`, `days` — added up (`1d 12h`). It is from one minute to ninety days. The
name of a preset and of a restriction is a lowercase letter, then up to 63 lowercase letters, digits and hyphens. A
preset the library refuses — a name, a restriction, a proposed duration longer than the longest — fails the build of
the container with the reason.

The built-in presets have their labels in English and French; the label of the application's own is shown as written.

## The table

The library names its table `phil_incident_activation`; the bundle chooses its connection, not its name. Two
applications that share a database keep their incidents apart by connections to different schemas or databases.

## Established members

```yaml
phil_incident_mode:
    spare_established: [new-posts, new-comments]
    established_members: App\Trust\EstablishedMembers
```

`read-only` covers every restriction: sparing `new-posts` alone leaves an established member restricted while a
read-only incident is in force; listing `read-only` as well lets them through. A visitor is never spared.

# phil/virtual-classroom-bundle

**Live classes in a Symfony application: rooms on BigBlueButton or Jitsi, a join link written for each participant's
role, the attendance — who arrived and left, when — and the recordings read from the servers' signed notifications,
and, with phil/courses-bundle, a live class as an activity of a course, completed by the share of the session
attended.**

phil/virtual-classroom-bundle wires phil/virtual-classroom into an application. The **providers** — BigBlueButton
servers, Jitsi servers of the application's own, JaaS accounts, or a `VirtualClassroom` service of the application —
are named in the configuration. `Classrooms` **schedules** a room on one of them, owned by what the application says;
the room **opens** on its server when its first participant follows the **join link** of the bundle, which sends them,
by their role, to the link the server trusts. A moderator is whoever has a configurable Symfony role.

The servers call back the **webhook** of the bundle at addresses it signs, one per room and channel, or one per
account: the end of a room, participants arriving and leaving, a recording ready, the attendance of a session.
`phil:virtual-classroom:sync`, from a scheduler, asks the servers that can say who is in the open rooms and what they
recorded. The **stays** of the participants are kept, an arrival reported twice counted once, and `Classrooms`
reports what each participant attended of a session, in seconds and in whole percents.

With phil/courses-bundle, the activity type **`virtual_classroom`** schedules the room of an activity at its first
launch, and completes the activity for each enrolled learner whose attendance reaches the threshold once the room has
ended. With phil/privacy-bundle, the stays of a person are in their access and erasure requests.

It is a Symfony bundle on phil/virtual-classroom, Doctrine DBAL, HttpKernel and Routing, with Symfony's HTTP client for BigBlueButton; the
console, Messenger, Security, phil/courses-bundle and phil/privacy-bundle are optional. The rooms, the stays and the
recordings live in three tables on SQLite, MySQL, MariaDB or PostgreSQL, their identifiers compared byte for byte.
Its services keep no state between requests and serve a long-running worker.

## Status

| Part | State |
|---|---|
| `Room`, `RoomStatus`, the events and the exceptions | Implemented and tested |
| `Classrooms`: schedule, join, end, sync, notifications, attendance, recordings | Implemented and tested |
| `Providers`, `WebhookSigner`, `AttendeeReport` | Implemented and tested |
| `DbalClassroomStore`, `ClassroomTables`, `ClassroomSchemaListener`, `InMemoryClassroomStore` | Implemented and tested |
| `JoinController`, `WebhookController`, `RoutedCallbackUrls`, `SecurityCurrentParticipant` | Implemented and tested |
| `VirtualClassroomActivity`, `CoursesCompletion`: the activity type of phil/courses-bundle | Implemented and tested |
| `ClassroomPrivacy`, the commands `sync` and `webhook-url`, `SyncRoom` and its handler, the Flex recipe | Implemented and tested |

The signed learning analytics callback of BigBlueButton — the attendance posted with its bearer token — is verified
against simulated BigBlueButton responses only: it has not yet been checked against a real BigBlueButton 3.x server.

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes, the tables. |
| [Configuration](doc/configuration.md) | Every option, with an example for each provider. |
| [Rooms](doc/rooms.md) | Scheduling, joining, ending, syncing; the owner of a room. |
| [Webhooks](doc/webhooks.md) | The addresses, their proof, what each provider sends, the answers. |
| [Attendance and recordings](doc/attendance.md) | The stays, the report of a session, the recordings. |
| [Courses](doc/courses.md) | The activity type `virtual_classroom` and its completion. |
| [Privacy](doc/privacy.md) | The access and erasure requests, and the retention. |
| [Console and Messenger](doc/console.md) | `phil:virtual-classroom:sync`, `phil:virtual-classroom:webhook-url`, `SyncRoom`. |
| [Security](doc/security.md) | What the bundle protects against, and what is left to the application. |
| [Extension points](doc/extension-points.md) | The services to replace, the events, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_virtual_classroom.yaml
phil_virtual_classroom:
    providers:
        main: { type: bigbluebutton, url: '%env(BBB_URL)%', secret: '%env(BBB_SECRET)%' }

# config/routes/phil_virtual_classroom.yaml
phil_virtual_classroom:
    resource: '@PhilVirtualClassroomBundle/config/routes.php'
    prefix: /virtual-classroom
```

```php
$room = $classrooms->schedule('Algebra, week 3', record: true, owner: 'workshop:42');
// Send the participants to /virtual-classroom/{room}/join.
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later and Doctrine DBAL 4.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

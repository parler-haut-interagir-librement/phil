# phil/virtual-classroom

**Live online classes through one port: open a room on a conferencing server, write the link each participant joins
by — signed for a moderator or an attendee —, end it, know who attended and for how long, and find its recordings;
BigBlueButton and Jitsi first.**

phil/virtual-classroom is a plain PHP library. An application talks to a **`VirtualClassroom`**: it opens a room from
a `RoomSpec` — its identifier, its title, whether it records, the longest it runs, the addresses the server calls back —,
writes the **join link** of a `Participant` for a `Role`, ends the room, asks who is in it now, lists its
**recordings**, and reads the **notifications** the server sends — participants joining and leaving, the room ended, a
recording ready — as `RoomEvent`s. `AttendanceSheet` turns the stays of a participant into the seconds and the share
of the session they attended, overlaps counted once.

Two adapters ship. **`BigBlueButton`** calls its server's API over Symfony's HTTP client contracts, every call signed
by the checksum of the secret it shares with the server, each call bounded in time and in size; it reads the end of a
room, the recording ready notification signed with HS256 and the attendance its learning analytics callback posts.
**`Jitsi`** writes join links that carry a JSON Web Token: HS256 for a server of the application's own, RS256 for JaaS,
8x8's hosted Jitsi; it reads the notifications JaaS signs with HMAC-SHA-256. Jitsi has no API to end a room or to list
who is in it: those calls throw `Unsupported`, and `capabilities()` says so beforehand.

Every failure is an exception of the library — `ProviderUnavailable`, `ProviderRefused`, `InvalidResponse`,
`InvalidWebhook`, `Unsupported`, `InvalidValue` — whose message names no participant and carries no address of a call.
Participants travel by the stable identifier of their account, never by their e-mail address. The adapters keep no
state between calls and serve a long-running worker.

## Status

| Part | State |
|---|---|
| `VirtualClassroom`, `RoomEvent`, `RoomId`, `ParticipantId`, `Participant`, `Role`, `RoomSpec`, `OpenedRoom`, `Capabilities`, `Presences`, `RoomState`, `Recording`, `Webhook` | Implemented and tested |
| The events `ParticipantJoined`, `ParticipantLeft`, `RoomEnded`, `RecordingReady` and the exceptions | Implemented and tested |
| `AttendanceSheet`, `Interval`: the seconds and the share of a session attended | Implemented and tested |
| `HttpBudget`: the time and the size a call may take | Implemented and tested |
| `BigBlueButton`, `ChecksumAlgorithm`: rooms, join links, end, presences, recordings, notifications | Implemented and tested |
| `Jitsi`: join links for a server of the application's own or JaaS, notifications | Implemented and tested |
| Zoom and Microsoft Teams adapters | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Rooms and join links](doc/rooms.md) | The port, a room's life, the roles, the participants. |
| [BigBlueButton](doc/bigbluebutton.md) | The adapter: checksums, callbacks, presences, recordings, attendance. |
| [Jitsi](doc/jitsi.md) | The adapter: tokens for a server of the application's own and JaaS, notifications, limits. |
| [Attendance](doc/attendance.md) | Stays, events and the share of a session attended. |
| [Errors and budgets](doc/errors.md) | The exceptions, the time and size bounds, what the logs may keep. |
| [Security](doc/security.md) | Secrets, signatures, personal data, and what is left to the application. |
| [Extension points](doc/extension-points.md) | Writing another adapter, and when the API is stable. |
| [Testing](doc/testing.md) | The tests, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Phil\Url\Url;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;

$server = new BigBlueButton($httpClient, Url::fromString('https://bbb.example.com/bigbluebutton/api/'), $secret, $clock);

$room = $server->open(new RoomSpec(RoomId::generate(), 'Algebra, week 3', record: true, maxMinutes: 90));
$link = $server->joinUrl($room, new Participant(ParticipantId::fromString('user-7'), 'Ada Lovelace'), Role::Attendee);
```

## Requirements

PHP 8.5 or later with the `dom`, `libxml`, `mbstring`, `openssl` and `sodium` extensions; an implementation of the
Symfony HTTP client contracts, `symfony/http-client`, for BigBlueButton.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

# Changelog

All notable changes to `phil/virtual-classroom-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- The development requirements accept `phil/courses-bundle` 0.3 and `phil/privacy-bundle` 0.3, the minor versions of
  their main branches and no earlier one, so that the package installs on its own beside those bundles.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `symfony/service-contracts` 3.8), and of each
  development requirement (`doctrine/orm` 3.8, `doctrine/doctrine-bundle` 3.4).

### Security

- The Flex recipe wrote into `.env` an example value of `BBB_SECRET`. The recipe writes the variable empty, with a
  comment on where to find the secret of the server, and the service of a BigBlueButton or self-hosted Jitsi server
  refuses a secret that begins with `change-me` or `changeme` when it is built
  (`Infrastructure\DependencyInjection\ServerSecret`).

## 0.1.0-alpha - 2026-11-20

### Added

- `Domain\Model\Room` — identifier, provider, title, recording, longest run, owner, status, session key, and when it
  was scheduled, opened and ended — and `Domain\Status\RoomStatus` (scheduled, open, ended); the exceptions
  `UnknownRoom`, `RoomClosed`, `UnknownProvider` and `InvalidRoom`, all `ClassroomException`; the events
  `ClassroomOpened`, `ClassroomEnded`, `ParticipantArrived`, `ParticipantDeparted`, `RecordingAvailable` and
  `AttendanceUpdated`, identifiers only.
- `Application\Classrooms`: `schedule()` on a provider, the default one by default, with an owner; `joinUrl()`, the
  room opened on its server at the first join with its callbacks; `end()`, on the server when it can, the open stays
  closed; `sync()`, the arrivals and departures noted from the server's presences, a room the server no longer knows
  ended, the recordings of a recorded room listed; `receive()`, the events of a notification applied, those of another
  room than its address refused, those of unknown rooms or of another provider ignored, a stay reported after the end
  closed with the room; `attendance()`, an `AttendeeReport` per participant from the room's opening — or first arrival
  — to its end; `recordings()`.
- `Application\Providers`, the servers of the configuration built when first asked for; `Application\WebhookSigner`,
  the HMAC-SHA-256 proof of each address of the webhook, per provider, room and channel; the ports
  `Application\Contract\ClassroomStore`, `CurrentParticipant` and `CallbackUrls`.
- `Infrastructure\Dbal\DbalClassroomStore` and `ClassroomTables`: three tables under a configurable prefix, the
  identifiers in `utf8mb4_bin` on MySQL and MariaDB and in the `C` collation on PostgreSQL, the moments as Unix
  timestamps, an arrival within a known stay beginning none; `ClassroomSchemaListener` for the schema the ORM
  generates; `InMemoryClassroomStore`, emptied between requests.
- `Infrastructure\Http\JoinController`: a redirection 303 to the participant's link, never cached, without referrer;
  403, 404, 410 and 503 with `Retry-After`. `WebhookController`: the proof checked, 1 MiB at most, 401 for a notification
  the provider refuses, 204 otherwise. The routes in `config/routes.php`.
- `Infrastructure\Routing\RoutedCallbackUrls`, the absolute addresses of the end and of the signed events of a room,
  none on `localhost` unless allowed; `Infrastructure\Security\SecurityCurrentParticipant`, the authenticated user by
  their identifier, a moderator by a configurable role, an e-mail address naming nobody.
- `Infrastructure\Courses\VirtualClassroomActivity`, the activity type `virtual_classroom` of phil/courses-bundle,
  tagged `phil_courses.activity_type` — settings `provider`, `record`, `duration`, `threshold`; the room of the
  activity scheduled at its first launch and opened in a window of its own —, and `CoursesCompletion`, the activity
  completed for each enrolled learner whose share of an ended session reaches its threshold, 75 % by default.
- `Infrastructure\Privacy\ClassroomPrivacy`, the stays of a person in the access and erasure requests of
  phil/privacy-bundle.
- The commands `phil:virtual-classroom:sync` and `phil:virtual-classroom:webhook-url`; the message `SyncRoom`,
  identifiers only, and its handler.
- The configuration: providers of type `bigbluebutton`, `jitsi`, `jaas` or `service`, the store, the connection, the
  table prefix, the clock, the HTTP client, the webhook secret, the moderator role, the participant service, the
  courses and the privacy modules.
- The benchmark `AttendanceBench`: a notification of 600 events, the report of 300 participants.
- The Flex recipe in `recipe/0.1`: the bundle, its configuration and its routes under `/virtual-classroom`.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.

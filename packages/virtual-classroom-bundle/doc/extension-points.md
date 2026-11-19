# Extension points

The bundle is in its `0.x` releases: the ports, the events and the services below may change in a minor release, said
in the changelog. They are meant to be stable from `1.0`.

## `CurrentParticipant`

Who joins in the request — a `Participant`: the stable identifier of the account and the name the room shows —, or
null; and the `Role` they take in a room. `SecurityCurrentParticipant` reads the authenticated user of Symfony
Security; name another service in `current_participant`.

## `ClassroomStore`

Where the rooms, the stays and the recordings are kept: `DbalClassroomStore` by default, `InMemoryClassroomStore` for
tests, or a service named in `store`. An implementation keeps the promises of `StoreTestCase`, the shared test of the
stores: one open stay per participant, an arrival within a known stay beginning none, a departure closing the latest
open stay, a recording once per identifier and format.

## `CallbackUrls`

The addresses of the end and of the signed events of a room. `RoutedCallbackUrls` generates them from the route of the
webhook; an application behind a proxy or on another host replaces it by aliasing the port to its own service.

## Providers

A provider of type `service` is any service implementing `Phil\VirtualClassroom\Contract\VirtualClassroom`: another
conferencing server, or a fake one in tests.

## Events

Dispatched through the PSR-14 dispatcher, after the store has kept what they say, with identifiers only:
`ClassroomOpened`, `ClassroomEnded`, `ParticipantArrived`, `ParticipantDeparted`, `RecordingAvailable` and
`AttendanceUpdated`. A listener reads the rest from `Classrooms`: the report of a session, the recordings.

## Services

Public: `Classrooms`, `Providers`, `ClassroomStore`, `CurrentParticipant`, the controllers `JoinController` and
`WebhookController`, and, when their bundles are enabled, `VirtualClassroomActivity`, `CoursesCompletion` and
`ClassroomPrivacy`.

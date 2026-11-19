# Rooms

`Phil\VirtualClassroomBundle\Application\Classrooms` is the public service of the rooms.

## Scheduling

```php
$room = $classrooms->schedule(
    'Algebra, week 3',        // 2 to 64 characters
    provider: 'main',         // the default provider when null
    record: true,
    maxMinutes: 90,           // 1 to 1440, or the server's limit
    owner: 'workshop:42',     // what the room belongs to
    id: null,                 // a RoomId, drawn when null
);
```

A scheduled room is kept, and no server is called. Its **owner** is what the application says it belongs to, 1 to
190 letters, digits, `_`, `.`, `:`, `/` or `-`: `ofOwner()` finds the latest room of an owner. The activity type of
phil/courses-bundle owns its rooms as `phil_courses:<activity>`.

## Joining

The participants follow the join link of the bundle, the route `phil_virtual_classroom_join`:
`/virtual-classroom/{room}/join`. `JoinController` names the participant of the request through `CurrentParticipant`,
asks their role, and answers with a redirection 303 to the link of the server, `Cache-Control: no-store, private` and
`Referrer-Policy: no-referrer`.

| Answer | When |
|---|---|
| 303 | The room opened, or was open; the link of the participant's role. |
| 403 | Nobody of the request may join. |
| 404 | No room has this identifier. |
| 410 | The room has ended. |
| 503, `Retry-After: 30` | The server cannot be reached, refuses the room, or answers what cannot be read. |

`Classrooms::joinUrl()` writes the link itself: it opens the room on its server — every time, which a running room
answers again with the key of its session —, with the addresses of its end and of its signed events, notes it open at
the first join, and dispatches `ClassroomOpened` once.

`SecurityCurrentParticipant`, the default, names the authenticated user by their user identifier — also the name the
room shows — and makes them a moderator when `isGranted(moderator_role, $room)`. A user identifier that is an e-mail
address names nobody: an application whose users sign in by e-mail sets `current_participant` to a service of its own,
returning the stable identifier of the account and its display name.

## Ending

`end()` ends the room on its server when the server can and the room was opened, then notes it ended, closes the stays
still open at that moment, and dispatches `ClassroomEnded` and `AttendanceUpdated`. An ended room lets nobody in
again; ending it twice does nothing. A room also ends when its server says so — by notification, or when `sync()`
finds it gone.

## Syncing

`sync()` asks the server of an open room who is in it, when the server can say: a participant seen for the first time
arrives at that moment, one no longer seen leaves, a room the server no longer knows ends. For a recorded room, it
lists the recordings and keeps the new ones. `phil:virtual-classroom:sync` syncs the open rooms, for the servers that
send no notifications or to make up for one lost.

# Rooms and join links

## The port

`Phil\VirtualClassroom\Contract\VirtualClassroom` is a conferencing server. Every adapter implements it and says in
`capabilities()` what it can do beyond opening a room and writing join links:

| Capability | Call | BigBlueButton | Jitsi |
|---|---|---|---|
| — | `open(RoomSpec): OpenedRoom` | `create` | no call |
| — | `joinUrl(OpenedRoom, Participant, Role): string` | a signed `join` link | a link with a token |
| `end` | `end(RoomId)` | `end` | `Unsupported` |
| `presences` | `presences(RoomId): Presences` | `getMeetingInfo` | `Unsupported` |
| `recordings` | `recordings(RoomId): list<Recording>` | `getRecordings` | `Unsupported` |
| `webhooks` | `receive(Webhook): list<RoomEvent>` | end, recording, attendance | with a webhook secret |

A call to what the adapter cannot do throws `Unsupported`; ask `capabilities()` first.

## A room's life

A room is named by a `RoomId`: 2 to 64 lowercase letters, digits, `-` or `_`, starting with a letter or a digit.
`RoomId::generate()` draws 32 hexadecimal digits from 16 random bytes. The identifier is the meeting identifier of
BigBlueButton and the room name of Jitsi, which compares names without case: a lowercase identifier names the same
room on both. Where anyone may enter a Jitsi room by its name, a drawn identifier is the one to use.

`RoomSpec` describes the room to open:

| Field | Meaning |
|---|---|
| `id` | The `RoomId`. |
| `title` | What the participants see, 2 to 64 characters without control characters, trimmed. |
| `record` | Whether the session may be recorded. |
| `maxMinutes` | 1 to 1440, or null for the server's own limit. |
| `endedUrl` | The `phil/url` address the server calls when the room ends. |
| `eventsUrl` | The address of the signed notifications: a recording ready, the attendance of a session. |

`open()` opens the room, or finds it running: calling it again changes nothing. It gives back an `OpenedRoom` with the
key of the session when the server has one — the creation time of BigBlueButton, which a join link repeats so that a
link of an earlier session of the same room is refused. Open the room before writing each batch of links, at the first
join for instance.

`end()` ends the room for everyone in it; a room already gone is not an error. `presences()` gives the `RoomState` —
running, waiting for its first participant, or gone — and the participants in it. `recordings()` lists what the server
recorded.

## Participants and roles

A `Participant` is a `ParticipantId` — the stable identifier of their account, 1 to 190 letters, digits, `.`, `_`,
`:` or `-`, never an e-mail address — and the name the room shows, 1 to 128 characters without control characters. The
identifier travels to the server in the join link and comes back in its notifications; the name goes to the server
only.

A `Role` is `Moderator` — who runs the room: mutes, shares, records, ends — or `Attendee`. The application decides it;
the link carries it, signed.

## Notifications

`receive(Webhook)` reads a notification as it reached the application: its method, its headers, its query, its body
and its **channel** — `Webhook::ENDED` for the address of the end of a room, `Webhook::EVENTS` for the signed events,
`Webhook::ACCOUNT` for the one address of an account. The adapter checks what its provider signs, and gives back the
`RoomEvent`s in the order they happened: `ParticipantJoined`, `ParticipantLeft`, `RoomEnded` and `RecordingReady`. A
notification of something else gives an empty list; a refused one throws `InvalidWebhook`.

What the application checks itself: that the address the notification came to is one it gave the server — its proof,
which phil/virtual-classroom-bundle signs —, and that the room named is one of its rooms on that server.

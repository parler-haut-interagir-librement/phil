# Reference

## `Phil\VirtualClassroom`

| Type | Role |
|---|---|
| `Contract\VirtualClassroom` | The port of a conferencing server. |
| `Contract\RoomEvent` | What a notification says happened to a room, and when. |
| `RoomId` | A room's identifier; `fromString()`, `generate()`, `equals()`. |
| `ParticipantId` | A participant's stable identifier; `fromString()`, `tryFrom()`, `equals()`. |
| `Participant` | A participant and the name the room shows. |
| `Role` | `Moderator` or `Attendee`. |
| `RoomSpec` | What a room is opened with. |
| `OpenedRoom` | A room the server knows, with the key of its session. |
| `Capabilities` | What a provider can do: `end`, `presences`, `recordings`, `webhooks`. |
| `Presences`, `RoomState` | Who is in a room, and whether it runs, waits or is gone. |
| `Recording` | A recording and one of its formats. |
| `Webhook` | A notification as it reached the application; `ENDED`, `EVENTS`, `ACCOUNT`. |
| `Timestamps` | Internal: a moment as a server writes it. |

## `Phil\VirtualClassroom\Event`

| Type | Role |
|---|---|
| `ParticipantJoined` | A participant entered the room. |
| `ParticipantLeft` | A participant left the room. |
| `RoomEnded` | The room ended. |
| `RecordingReady` | A recording is ready, described when the notification carries its address. |

## `Phil\VirtualClassroom\Attendance`

| Type | Role |
|---|---|
| `Interval` | One stay of a participant. |
| `AttendanceSheet` | `seconds()` and `percent()` of a session attended. |

## `Phil\VirtualClassroom\BigBlueButton`

| Type | Role |
|---|---|
| `BigBlueButton` | The adapter; `signed()` writes the address of a call with its checksum. |
| `ChecksumAlgorithm` | `Sha256`, `Sha384`, `Sha512`. |
| `XmlAnswer` | Internal: an answer of the API. |

## `Phil\VirtualClassroom\Jitsi`

| Type | Role |
|---|---|
| `Jitsi` | The adapter; `selfHosted()` and `jaas()`. |

## `Phil\VirtualClassroom\Http` and `Token`

| Type | Role |
|---|---|
| `Http\HttpBudget` | The time and size bounds of a call. |
| `Http\BoundedHttp` | Internal: a GET within a budget. |
| `Token\JsonWebToken` | Internal: signing and checking compact JSON Web Tokens. |

## `Phil\VirtualClassroom\Exception`

`VirtualClassroomException`, `InvalidValue`, `ProviderUnavailable`, `ProviderRefused`, `InvalidResponse`,
`InvalidWebhook`, `Unsupported`; see [Errors and budgets](errors.md).

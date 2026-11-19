# Errors and budgets

## The exceptions

Every exception implements `Exception\VirtualClassroomException`.

| Exception | When |
|---|---|
| `InvalidValue` | A value out of its bounds: an identifier, a title, a duration, a secret, a key. |
| `ProviderUnavailable` | The server did not answer in time, could not be reached, or answered with a server error: retry later. |
| `ProviderRefused` | The server answered with a client error or a redirect, or refused the call; the message names its stable key. |
| `InvalidResponse` | The answer cannot be read: not the XML of a response, no return code, larger than the budget. |
| `InvalidWebhook` | A notification refused: its signature, its age, its form, a room that is not an identifier. |
| `Unsupported` | What the provider cannot do. |

No message names a participant, holds a display name, or carries the address of a call — whose checksum derives from
the secret: a call is named by its label, `create`, `getMeetingInfo`. A log may keep the messages as they are.

## The budget of a call

`Http\HttpBudget` bounds every call of an adapter that calls its server:

| Bound | Default | Limits |
|---|---|---|
| `timeout` | 5 seconds without a byte | more than 0 |
| `maxDuration` | 10 seconds in all | the timeout or more, 120 at most |
| `maxBytes` | 1 MiB of answer | 1 KiB to 16 MiB |

A call follows no redirect. Its answer is read chunk by chunk and cut past `maxBytes`, never buffered whole. A timeout
or a transport failure is a `ProviderUnavailable`.

## The bounds of a notification

| Adapter | Body | Content |
|---|---|---|
| BigBlueButton | 1 MiB | 1000 attendees, 200 stays each, JSON 32 deep |
| Jitsi | 256 KiB | JSON 16 deep |

A JSON Web Token is 16 KiB at most, its parts 16 deep.

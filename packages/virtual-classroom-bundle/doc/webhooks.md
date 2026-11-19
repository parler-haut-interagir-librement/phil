# Webhooks

The servers call the application back at addresses of the bundle, each carrying a **proof**: the HMAC-SHA-256 of its
provider, its room and its channel under `webhook_secret`, 32 characters of base64url.

| Route | Path | Methods | Use |
|---|---|---|---|
| `phil_virtual_classroom_room_webhook` | `/webhook/{provider}/{room}/{channel}/{token}` | GET, POST | The addresses a room is opened with: `ended` and `events`. |
| `phil_virtual_classroom_webhook` | `/webhook/{provider}/{token}` | POST | The one address of an account, JaaS for instance. |

Each channel of each room has its own address. BigBlueButton shows the addresses of its `meta_` callbacks to the
participants: the address of the signed events, which they may see, cannot end the room nor reach another room, and
what comes to it must carry the server's own signature. `phil:virtual-classroom:webhook-url <provider>` writes the
address of an account, to set in the console of the provider.

## What happens

`WebhookController` checks the proof — a wrong one is a 404, as an address that does not exist —, refuses a body over
1 MiB with a 413, then hands the notification to the provider, which checks its own signature and reads its events.
`Classrooms::receive()` applies them:

- the events of a room other than the address's are refused, as a forged notification;
- the events of a room the application does not know, or of a room on another provider, are ignored;
- `ParticipantJoined` begins a stay, `ParticipantLeft` ends one, `RoomEnded` ends the room, `RecordingReady` keeps the
  recording — listed from the server when the notification does not describe it.

| Answer | When |
|---|---|
| 204 | Applied, or nothing to apply. |
| 401 | The provider refused the notification: its signature, its age, its form. BigBlueButton then stops retrying. |
| 404 | A wrong proof, an unknown provider, a provider that reads no notifications, a room that is not an identifier. |
| 413 | A body over 1 MiB. |

A notification delivered twice changes nothing: an arrival within a known stay begins none, a departure without an
open stay ends none, a known recording is not new.

## By provider

| Provider | Channel | What comes |
|---|---|---|
| BigBlueButton | `ended` | The end of the room, by GET. |
| BigBlueButton | `events` | A recording ready, signed with HS256; the attendance of the session, with its bearer token. |
| Jitsi, JaaS | `account` | Participants joining and leaving, the room destroyed, a recording uploaded, signed in `X-Jaas-Signature`. |

The signed learning analytics callback of BigBlueButton — the attendance posted with its bearer token — is verified
against simulated BigBlueButton responses only: it has not yet been checked against a real BigBlueButton 3.x server.

A Jitsi server of the application's own sends notifications only through a module that posts the JaaS events signed
the same way; set its address to the account address of its provider.

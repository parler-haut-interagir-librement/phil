# Jitsi

`Phil\VirtualClassroom\Jitsi\Jitsi` writes the links of a Jitsi Meet server: a room exists once someone enters it, by
a link that carries a JSON Web Token. The adapter calls no server.

## A server of the application's own

```php
Jitsi::selfHosted(
    Url::fromString('https://meet.example.com'),
    'my-app',             // the application identifier its prosody expects
    $appSecret,           // its application secret, 16 bytes or more
    $clock,
    tokenTtl: 10_800,     // how long a link lets its participant in, 60 seconds to a day
    webhookSecret: null,  // the secret of the notifications, when a module sends them
    audience: 'jitsi',    // the `aud` claim
    subject: null,        // the `sub` claim, the host of the server by default
);
```

The token is signed with HS256 and holds `aud`, `iss` — the application identifier —, `sub`, `room`, `iat`, `nbf`, `exp`
and `context.user`: `id`, the participant's identifier, `name`, `moderator` as a boolean and `affiliation`, `owner`
for a moderator and `member` for an attendee, for the token modules that read it. The link is
`https://meet.example.com/<room>?jwt=<token>`.

## JaaS

```php
Jitsi::jaas('vpaas-magic-cookie-…', 'vpaas-magic-cookie-…/abc123', $privateKeyPem, $clock, webhookSecret: $secret);
```

The token is signed with RS256 under the private key of the API key, its identifier in the `kid` header. It holds
`aud` `jitsi`, `iss` `chat`, `sub` the application identifier, `room`, the moments, `context.user` with `moderator` as
`"true"` or `"false"`, and `context.features`: `recording` for a moderator of a recorded room, livestreaming,
transcription and outbound calls off. The link is `https://8x8.vc/<application>/<room>?jwt=<token>`; another server
may be given.

## Notifications

With a webhook secret, `receive()` reads the notifications as JaaS posts them, signed in `X-Jaas-Signature`:
`t=<moment>,v1=<signature>`, the base64 HMAC-SHA-256 of the moment, a dot and the body. The moment is within five
minutes of the clock; any `v1` may match; the body is 256 KiB at most.

| `eventType` | Event |
|---|---|
| `PARTICIPANT_JOINED` | `ParticipantJoined`, the participant by `data.id`, the identifier of their token |
| `PARTICIPANT_LEFT` | `ParticipantLeft` |
| `ROOM_DESTROYED` | `RoomEnded` |
| `RECORDING_UPLOADED` | `RecordingReady` with a `download` recording, its link valid for a day |

The room is read from `fqn`, `<application>/<room>`, and must be of the application for JaaS. A participant in a
breakout room, a participant without identifier, or another event gives no event.

## Limits

- Jitsi has no API to end a room, to say who is in it or to list its recordings: `end()`, `presences()` and
  `recordings()` throw `Unsupported`. A room ends when its last participant leaves; a link stops letting anyone in when
  its token expires.
- A server of the application's own sends no notifications by itself. A prosody module that posts the same events,
  signed the same way, makes them readable; without one, no attendance is known.

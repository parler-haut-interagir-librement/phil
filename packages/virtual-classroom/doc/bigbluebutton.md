# BigBlueButton

`Phil\VirtualClassroom\BigBlueButton\BigBlueButton` reaches a BigBlueButton server through its API.

```php
new BigBlueButton(
    $httpClient,                                                  // Symfony's HttpClientInterface
    Url::fromString('https://bbb.example.com/bigbluebutton/api/'),
    $secret,                                                      // the shared secret, 16 bytes or more
    $clock,                                                       // a PSR-20 clock
    ChecksumAlgorithm::Sha256,                                    // Sha256, Sha384 or Sha512
    new HttpBudget(timeout: 5.0, maxDuration: 10.0, maxBytes: 1_048_576),
    analytics: true,
    signedAnalytics: true,
);
```

The address is https. The secret is never sent: each call carries a **checksum**, the hash of the call's name, its
query string and the secret, among those of SHA-2 the server accepts since 2.5 — its `supportedChecksumAlgorithms`.
SHA-1, the only one of older servers, is not offered.

## Opening and joining

`open()` calls `create` with the name, the meeting identifier, `record`, `duration` when given, and the callbacks:
`meetingEndedURL` from `endedUrl`, `meta_bbb-recording-ready-url` from `eventsUrl` when the room records, and
`meta_analytics-callback-url` from `eventsUrl` when `analytics` is on. A room already running answers with its
creation time; one running with other settings is refused with `idNotUnique`, and its creation time is read from
`getMeetingInfo`.

`joinUrl()` writes the `join` link: `fullName`, `meetingID`, `role` — `MODERATOR` or `VIEWER` —, `userID`, and
`createTime` from the `OpenedRoom`, so that the server refuses a link of an earlier session. The server is not called.

## Ending, presences, recordings

`end()` calls `end`; `notFound` is a room already gone. BigBlueButton answers that it sent the request: the room ends
a moment later.

`presences()` calls `getMeetingInfo`: `running` gives the state, `notFound` a room gone, and each `attendee` with a
`userID` that is a `ParticipantId` is listed once. A participant who joined without one — by a link the application
did not write — is not.

`recordings()` calls `getRecordings` for the room: one `Recording` per playback format — `presentation`, `video`,
`podcast` —, with its address, whether it is published, and the start and end of the recorded session. A format whose
type or address cannot be read is left out.

## Notifications

| Channel | Request | Event |
|---|---|---|
| `ended` | GET with `meetingID`, sent to `meetingEndedURL` | `RoomEnded`, now |
| `events` | POST with `signed_parameters`, a token signed with HS256 under the shared secret | `RecordingReady`, without details |
| `events` | POST of JSON from the learning analytics callback | `ParticipantJoined` and `ParticipantLeft` for each stay |

The end of a room is a plain GET: the proof is the address, which the application signs and BigBlueButton does not
show the participants. The `meta_` addresses are visible to the participants of the room, so what comes to `eventsUrl`
must carry the server's signature: the recording token always, the attendance its bearer token — a JSON Web Token
signed under the shared secret, its expiry checked — unless `signedAnalytics` is turned off for a server that sends
none. With `analytics` off, the attendance is ignored.

The attendance reads `meeting_id`, then each attendee's `ext_user_id` — the `userID` of the join link — with its
`joins` and `leaves`: moments in seconds or milliseconds, as numbers, digits, RFC 3339 dates or objects with a
`timestamp`. At most 1000 attendees and 200 stays each are read; a notification of 1 MiB at most.

## Limits

- The attendance comes at the end of the session, after the learning analytics are written; until then
  `presences()`, asked regularly, notes who arrives and leaves at the moment it is asked.
- A recording ready names the recording only: `recordings()` describes it.
- The signed learning analytics callback — the attendance posted with its bearer token — is verified against simulated
  BigBlueButton responses only: it has not yet been checked against a real BigBlueButton 3.x server.

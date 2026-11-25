# Changelog

All notable changes to `phil/virtual-classroom` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`symfony/http-client-contracts` 3.8).

## 0.1.0-alpha - 2026-11-20

### Added

- `Contract\VirtualClassroom`, the port of a conferencing server: `capabilities()`, `open()` — idempotent for a
  running room —, `joinUrl()` — no call to the server —, `end()`, `presences()`, `recordings()` and `receive()`.
- `RoomId` — 2 to 64 lowercase letters, digits, `-` or `_`, or 32 hexadecimal digits drawn —, `ParticipantId` —
  1 to 190 letters, digits, `.`, `_`, `:` or `-`, never an e-mail address —, `Participant` with the name the room
  shows, `Role` (moderator, attendee), `RoomSpec` — title of 2 to 64 characters, recording, 1 to 1440 minutes, the
  addresses of the end and of the signed events —, `OpenedRoom` with the key of its session, `Capabilities`,
  `Presences` and `RoomState`, `Recording`, and `Webhook`, a notification as it reached the application with its
  channel.
- `Contract\RoomEvent` and the events `ParticipantJoined`, `ParticipantLeft`, `RoomEnded` and `RecordingReady`, in
  UTC.
- The exceptions `InvalidValue`, `ProviderUnavailable`, `ProviderRefused`, `InvalidResponse`, `InvalidWebhook` and
  `Unsupported`, all `VirtualClassroomException`, their messages free of participants and of call addresses.
- `Attendance\AttendanceSheet` and `Attendance\Interval`: the seconds of a session attended, the stays cut to the
  session and their overlaps counted once, and the share in whole percents rounded down.
- `Http\HttpBudget`: the seconds of silence, the seconds in all and the largest answer of a call; a call follows no
  redirect and is cut past its ceiling.
- `BigBlueButton\BigBlueButton` and `ChecksumAlgorithm` (SHA-256, SHA-384, SHA-512): `create` with
  `meetingEndedURL`, the recording ready and learning analytics callbacks, a room already running with other settings
  found by `getMeetingInfo`; join links signed for `MODERATOR` or `VIEWER` and tied to the creation time; `end`, a room
  gone not being an error; `getMeetingInfo` for who is in a room; `getRecordings`, one `Recording` per playback
  format; the end of a room, the recording ready token checked with HS256, and the attendance of the learning analytics
  callback read with its bearer token, at most 1000 attendees of 200 stays each. The answers are read as XML without a
  document type, nothing loaded from the network.
- `Jitsi\Jitsi`: `selfHosted()` — HS256 tokens with the application identifier and secret, `aud` and `sub`
  configurable, moderator by `moderator` and `affiliation` — and `jaas()` — RS256 tokens under the private key of an
  API key named in `kid`, recording allowed to a moderator of a recorded room —; links valid 60 seconds to a day; the
  notifications signed in `X-Jaas-Signature`, within five minutes: participants joining and leaving, breakout rooms
  left out, the room destroyed, a recording uploaded with its download link valid for a day. `end()`, `presences()` and
  `recordings()` throw `Unsupported`.
- The fuzz target `responses` — the answers of BigBlueButton, its attendance, the notifications of Jitsi — and the
  benchmark `ClassroomBench`.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.

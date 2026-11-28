# Versions and acceptance

## Publishing

`CodeOfConduct::publish()` adds the next version of the code of a scope: number 1 first, then each one after the
other. A version holds a title of 1 to 200 characters on one line and a text of 1 to 100,000 characters, trimmed; it
is never changed once published. Its `hash` is the SHA-256 of its title and text: the proof of what a member was
shown.

A version is `ChangeKind::Major` — a change of substance — or `ChangeKind::Minor` — a correction, a clarification.
The first version is major whatever the call says.

Who may publish is the application's decision: the service does not check it. The `CodeVersionPublished` event names
the scope and the number; a listener tells the members of a major change.

## Accepting

`accept()` records that a member accepted the version they were shown, with its hash and the time. Only the version
in force is accepted: a page left open while a new version was published is refused with `ActionRefused`, and the
application shows the new one. Every acceptance is kept.

## The status of a member

`status()` answers with an `AcceptanceStatus`:

| Status | When | `mayTakePart()` |
|---|---|---|
| `NoCode` | The scope has no code | Yes |
| `NotAccepted` | The member never accepted the code | No |
| `Outdated` | The member accepted a version older than the latest major one | No |
| `Accepted` | The member accepted the latest major version, or a later one | Yes |

A member who accepted version 2 still stands as `Accepted` after a minor version 3, and as `Outdated` after a major
version 4.

The application asks before a member takes part — writes, registers, posts — and lets them read whatever their
status: the code holds back taking part, never reading.

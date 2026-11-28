# Access rules

Every path to the information goes through `SafetyInfoVault`. Each method takes the identifier of a person the
application has already authenticated: the vault decides what that person may do, never who they are. An identifier
holds 1 to 255 bytes without a control character; use a stable identifier, never an email address.

## The paths

| Path | Who | When | Journaled | Event |
|---|---|---|---|---|
| `submit()` | The participant | Until the window of the event closes | No | `SafetyInfoSubmitted` |
| `readOwn()` | The participant | Any time the record exists | No | None |
| `copy()` | The participant, at their request | Until the window of the event copied to closes | No | `SafetyInfoSubmitted` |
| `erase()` | The participant | Any time | No | `SafetyInfoErased` |
| `readingsOf()` | The participant | Any time | No | None |
| `read()` | `Role::Responsible` for the event | While its window is open | Yes, before opening | `SafetyInfoRead` |
| `breakGlass()` | `Role::Staff` or `Role::Responsible` | While its window is open, with a reason of 10 to 500 characters | Yes, with the reason, before opening | `SafetyInfoRead` with `AccessReason::BreakGlass` |
| `purge()` | A scheduled task | Any time | No | None |

The application calls `readOwn()`, `copy()`, `erase()` and `readingsOf()` only with the identifier of the person
signed in: these paths trust it to be the participant.

## The access window

`Contract\AccessWindows` gives each event an `AccessWindow`: from `opensAt`, included, to `closesAt`, excluded. An
event without a window takes no submission and no reading. A record is kept until the window closes, then
`purge()` deletes it; a participant may erase it before.

## What a refusal says

`read()` and `breakGlass()` refuse with `Exception\AccessDenied`, whose message is the same whatever the reason: a
person without the role, a window not open, an event without a window. A refused reading writes nothing to the
journal and dispatches nothing.

A responsible person who may read learns when a participant sent nothing: the method returns `null`, and nothing is
journaled since nothing was read.

## The journal

The vault writes an `AccessRecord` to `Contract\AccessJournal` before it opens the information. A journal that throws
stops the reading: no plaintext leaves the vault without its trace. The journal is kept apart from the information and
outlives its purge, for the time the application's retention calendar gives it. The participant reads it through
`readingsOf()`, the most recent first, 1 to 500 records at a time.

## Breaking the glass

A member of the team who meets an emergency reads the information with `breakGlass()` and a reason. The reason goes
to the journal, never to the event; the `SafetyInfoRead` event carries `AccessReason::BreakGlass`, and a listener
tells the responsible people of the event, who read the reason in the journal.

## Events

Every event carries identifiers only — the event, the participant, the reader, the reason of the access — never a
field of the information. A listener reads what it needs from the application.

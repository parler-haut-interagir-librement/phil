# Events and messages

## The events

`CommunityNotes` dispatches an event after each action, through the PSR-14 dispatcher it is given, when there is one.
Each event carries the identifier of the note, and nothing else: not the author, not the rater, not the verdict.

| Event | Dispatched when |
|---|---|
| `NoteWritten` | A note was written. |
| `NoteRated` | A note was rated, or rated again. |
| `NoteStatusChanged` | A decision changed the status of a note: published, unpublished, decided not helpful. |
| `NoteWithdrawn` | Its author withdrew a note. |
| `NoteErased` | A note was erased with the data of its author. |

All are in `Phil\CommunityNotes\Event`. A listener that needs more reads the note with `find()`; one that tells the
author their note is published listens to `NoteStatusChanged` and reads the status.

## Deciding again in the background

The message `Phil\CommunityNotes\Message\ReconsiderNote` names a note to decide again; `ReconsiderNoteHandler` decides
it under the rule of the `CommunityNotes` it is given, and skips a note erased in the meantime. With Symfony
Messenger, the handler is registered on the bus, and a command or a listener sends one message per note after the rule
changed or a member was erased:

```php
$bus->dispatch(new ReconsiderNote($noteId));
```

The message is short and holds no personal data, so that a queue keeps nothing more than the identifier.

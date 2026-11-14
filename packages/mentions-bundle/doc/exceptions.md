# Exceptions

Every exception implements `Phil\MentionsBundle\Domain\Exception\MentionsException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidMention` | `InvalidArgumentException` | A handle, an offset, a pattern, a member, a limit or an identifier cannot be used |
| `MentionsRefused` | `RuntimeException` | A text is longer than `limits.text_length`, or mentions more members than `limits.per_text` |

`MentionsRefused` comes from the application's call to `Mentions` or `MentionRecorder`, before anything is stored: the
application shows it to the writer and keeps their text.

`NotifyMentionedMember` lets the notifications bundle's `InvalidNotification` through for a subject or an actor that
is not one of its identifiers.

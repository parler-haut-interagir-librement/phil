# Privacy

## What the library keeps

| Data | Kept by | Personal |
|---|---|---|
| A note: its subject, author, text, time, status and decision | `NoteRepository` | The author, and the text they wrote |
| A rating: its note, rater, verdict and time | `RatingRepository` | The rater, and what they thought of a note |
| A decision: the pairs of raters who made it | With the note | The raters named in each pair |

No score of a rater is computed or kept. The events and the message name a note only, never a member.

## Showing a decision

A rating says what a member thinks of a note; the pairs of a decision name raters. A page for the readers shows
`toPublicArray()`, which leaves the raters out; `toArray()`, with them, is for the moderators.

## Access and erasure

`exportMember($member)` gives what the library keeps of a member, as plain values: the notes they wrote — identifier,
subject, text, status, time — and the ratings they gave — note, verdict, time. It is empty when they wrote and rated
nothing.

`eraseMember($member)` erases it:

- the notes they wrote are removed, with the ratings of those notes, and `NoteErased` is dispatched for each;
- the ratings they gave are removed;
- the notes they rated are decided again, so that no decision kept names them any more; a withdrawn note keeps its
  status and gets a new decision.

Removing a member's notes changes the history of the other raters, which other decisions read: an application that
wants them all up to date sends `ReconsiderNote` for the notes concerned, or for every note, in the background.

An application with an access and erasure process calls both from its own handlers of those requests.

# Notes and ratings

## Writing a note

```php
$note = $notes->write(ResourceReference::of('post', '42'), $member, 'The photo was taken in 2019, not this week.');
```

The subject is any resource of the application, named as a reference of `phil/resource`. The qualification is asked
first: a member it refuses gets `NoteRefused`. The text is a UTF-8 text of 1 to 2000 characters, not only spaces;
anything else throws `InvalidNote`. A new note needs ratings: its status is `NeedsMoreRatings` and it has no decision.

## Rating a note

```php
$note = $notes->rate($note->id, $rater, Verdict::Helpful);   // or Verdict::NotHelpful
```

A rater has one rating per note: rating again replaces the earlier one, and the time of the new one counts. After each
rating, the [cross-agreement rule](cross-agreement-rule.md) decides the note again, and the note is kept with its new
status and the decision that gave it. `NoteRefused` stops a rating when the note is withdrawn, when the rater wrote it,
or when the qualification refuses them.

## Withdrawing a note

Only its author withdraws a note. A withdrawn note keeps its text and its last decision, is neither shown nor rated any
more, and stays withdrawn when it is decided again.

## What is shown, and to whom

| Status | Shown to |
|---|---|
| `NeedsMoreRatings` | The raters: `notesOf($subject)` lists every note of a resource. |
| `Helpful` | The readers of the resource: `shownOf($subject)` lists these only, the oldest first. |
| `NotHelpful` | The raters. |
| `Withdrawn` | Nobody but the author and the moderators the application lets see it. |

A note moves between `NeedsMoreRatings`, `Helpful` and `NotHelpful` as ratings come; only `isShown()` notes reach the
readers. A page that tells a reader why a note is shown uses `$note->decision?->toPublicArray()`, which leaves the
raters' names out; `toArray()`, with the names, is for the moderators.

## Deciding again

`reconsider($note)` decides a note again from its ratings: after the rule changed, after the history of its raters
changed, or for a note decided under another rule. The message `ReconsiderNote` does it in the background: see
[Events and messages](events.md).

## Reading notes

`get($id)` throws `NoteNotFound` for a note the store does not have; `find($id)` gives `null`.

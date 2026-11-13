# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Say who may write and rate | Implement `Contract\Qualification`, or use `Security\AuthorizationQualification` with roles or voters: see [Qualification](qualification.md) |
| Keep notes | Implement `Contract\NoteRepository` |
| Keep ratings and give the history of two raters | Implement `Contract\RatingRepository`, which extends `Contract\RatingHistory` |
| Change the numbers of the rule | Pass a `CrossAgreementRule` of your own to `CommunityNotes` |
| Act after a note is written, rated, decided, withdrawn or erased | Listen to the events: see [Events and messages](events.md) |

## The stores

`NoteRepository` gets, lists by subject and by author — the oldest first —, saves and removes notes. A store in a
database keeps the decision as JSON, from `Decision::toArray()`, and rebuilds the note with `Note::restore()`.

`RatingRepository` keeps one rating per rater and note, the last replacing the earlier one; lists the ratings of a note
and of a rater; removes the ratings of a rater and of a note; and answers `between($first, $second, $exceptNote)`: the
notes both raters rated, the one being decided left out, and those they gave the same verdict. A database answers it in
one query from an index on the rater and the note; the rule asks it for up to `maxRaters × (maxRaters − 1) ÷ 2` pairs
per decision.

The stores keep no state between requests other than what they store, so that the services stay safe in a worker.

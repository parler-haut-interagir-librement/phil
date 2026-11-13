# phil/community-notes

**Community notes written by qualified members and published by an explainable cross-agreement rule.**

phil/community-notes lets the members of a community add context under a post, a page or a message: a short note
that says what the readers should know — the photo is from another year, the figure comes from a source that says
otherwise. Qualified members write the notes and rate the notes of others, helpful or not. A note is not published by
a majority: it is published when raters who usually disagree find it helpful together.

That is the bridging idea, in a form a small community can check by hand. Two raters usually disagree when, on the
other notes they both rated, they gave the same verdict on less than half of them. A note is decided helpful when it
has enough ratings, when most of them say helpful, and when enough pairs of raters who usually disagree said so. Every
decision is kept with the note and explains itself: the reason, the ratings of each verdict, the rule's numbers, and
the pairs of raters who made it, with the notes they shared and those they agreed on.

The library asks the application who may write and rate — by a role through Symfony Security, by seniority, by a
record it keeps — and where notes and ratings live. It exports and erases a member's notes and ratings on request,
dispatches events that name the note and nothing else, and offers a message to decide notes again in the background.

It is a plain PHP library on `phil/resource`, `symfony/uid` and the PSR clock and event dispatcher. Its services keep
no state between calls and are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Notes: writing, rating, withdrawing, the notes shown under a resource | Implemented and tested |
| The cross-agreement rule, its decision and its explanation | Implemented, tested and benchmarked |
| The qualification of writers and raters, through Symfony Security or the application | Implemented and tested |
| A member's data exported and erased | Implemented and tested |
| Events naming the note, and the message `ReconsiderNote` | Implemented and tested |
| Stores in memory | Implemented and tested |
| Stores in a database, configuration, pages | Not part of the library: the application, or a bundle on it, provides them |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the services to build. |
| [Notes and ratings](doc/notes-and-ratings.md) | Writing, rating and withdrawing notes, what is shown and to whom. |
| [The cross-agreement rule](doc/cross-agreement-rule.md) | How a note is decided, its numbers, the explanation, the cost. |
| [Qualification](doc/qualification.md) | Who may write and rate: Symfony Security, or a qualification of the application. |
| [Privacy](doc/privacy.md) | What the library keeps of a member, the export and the erasure. |
| [Events and messages](doc/events.md) | The events dispatched, and deciding notes again in the background. |
| [Extension points](doc/extension-points.md) | The qualification, the stores, the rule, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests, and how to test code that uses notes. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/community-notes
```

```php
use Phil\CommunityNotes\CommunityNotes;
use Phil\CommunityNotes\Verdict;
use Phil\Resource\ResourceReference;

$note = $notes->write(ResourceReference::of('post', '42'), $member, 'The photo was taken in 2019, not this week.');

$note = $notes->rate($note->id, $rater, Verdict::Helpful);

$note->status;                        // NoteStatus::NeedsMoreRatings until raters who usually disagree agree
$note->decision?->toPublicArray();    // why, without the raters' names

$notes->shownOf(ResourceReference::of('post', '42'));   // the helpful notes, shown to the readers
```

## Requirements

PHP 8.5 or later with `mbstring`. `symfony/security-core` 8.2 to ask Symfony Security who may write and rate.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

# phil/proposal-review

**One jury evaluation engine for calls for papers, course applications and contests: dated phases, weighted criteria,
blind review with conflicts of interest, batch decisions, and a pairwise voting mode scored by Bradley–Terry.**

phil/proposal-review runs the evaluation of proposals by a jury. A campaign has a dated schedule — submission, review,
an optional author response, deliberation — that opens and closes each action on the clock; weighted criteria, each on
its own scale; and a policy: blind or not, how many reviews a proposal needs and may have, mean or median, when a
reviewer sees the others' reviews. Reviewers are assigned by hand or balanced automatically; a reviewer never sees nor
scores a proposal they wrote or declared a conflict with. "Save and next" sends each reviewer to the least reviewed
proposal they may review. Standings rank the proposals; the jury records decisions during deliberation, then applies
them all at once.

Beside scores, a campaign may collect votes by pairs: a voter is shown two proposals and picks one. The pairs are drawn
to balance exposure, never repeat for a voter and skip conflicts; the votes go to an append-only log, from which the
Bradley–Terry model computes each proposal's strength, the same every time for the same log.

It is for any PHP application that asks a jury to choose: the talks of a conference, the applicants to a course or a
grant, the entries of a costume, writing or game contest. Proposals, reviewers and authors are the application's
stable identifiers; their content, comments and screens stay in the application. It is a plain PHP library on
`psr/clock` and `psr/event-dispatcher`, with stores behind ports, adapters in memory and on Doctrine DBAL; every instant
is kept in UTC.

## Status

| Part | State |
|---|---|
| Campaigns, dated schedules, weighted criteria, review policies | Implemented and tested |
| Proposals, manual and balanced assignment, conflicts of interest by proposal or by author | Implemented and tested |
| Reviews within the scale, one per reviewer, replaced when scored again; "save and next" | Implemented and tested |
| Blind review, visibility of the others' reviews, author response window | Implemented and tested |
| Standings by mean or median, sorted by `\SortDirection` | Implemented and tested |
| Decisions recorded during deliberation, applied together | Implemented and tested |
| Pairwise voting: balanced pairs, append-only log, Bradley–Terry scores | Implemented and tested |
| Events for phase changes, reviews, decisions and votes | Implemented and tested |
| Stores in memory | Implemented and tested |
| Doctrine DBAL stores: tables from their own schema, identifiers compared byte for byte, votes numbered under concurrency | Implemented and tested |
| Reviewer bidding, discussion threads, comments | Not planned here: comments belong to the application or a comments package |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, declaring the services in Symfony. |
| [Usage](doc/usage.md) | A campaign from submission to decisions, step by step. |
| [Pairwise voting](doc/pairwise-voting.md) | Drawing pairs, casting votes, reading the scores. |
| [Algorithms](doc/algorithms.md) | Scores and aggregation, balanced assignment, "save and next", the pair draw, Bradley–Terry, and their guarantees. |
| [Storage](doc/storage.md) | The four ports, the adapters in memory and on Doctrine DBAL, and what an adapter must keep. |
| [Events](doc/events.md) | What the engine dispatches, and when. |
| [Security](doc/security.md) | Phases as permissions, conflicts, blindness, identifiers, bounds. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests and benchmark, and how to test code that uses it. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/proposal-review
```

```php
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Memory\InMemoryCampaignStore;
use Phil\ProposalReview\Memory\InMemoryProposalStore;
use Phil\ProposalReview\Memory\InMemoryReviewStore;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\ReviewBoard;
use Phil\ProposalReview\Schedule;
use Phil\ProposalReview\Score;
use Symfony\Component\Clock\NativeClock;

$campaigns = new InMemoryCampaignStore();
$campaigns->save(new Campaign(
    'cfp-2027',
    new Schedule([
        new PhaseWindow(Phase::Submission, new DateTimeImmutable('2027-01-04'), new DateTimeImmutable('2027-02-01')),
        new PhaseWindow(Phase::Review, new DateTimeImmutable('2027-02-01'), new DateTimeImmutable('2027-02-22')),
        new PhaseWindow(Phase::Deliberation, new DateTimeImmutable('2027-02-22'), new DateTimeImmutable('2027-03-01')),
    ]),
    [new Criterion('content', weight: 2), new Criterion('clarity')],
));

$board = new ReviewBoard($campaigns, new InMemoryProposalStore(), new InMemoryReviewStore(), new NativeClock());

$board->submit('cfp-2027', 'talk-81', authors: ['u-1042']);        // during submission
$board->assignBalanced('cfp-2027', ['r-1', 'r-2', 'r-3', 'r-4'], 3);
$board->review('cfp-2027', 'talk-81', 'r-2', [new Score('content', 4), new Score('clarity', 5)]); // during review
$next = $board->nextFor('r-2', 'cfp-2027');                         // "save and next"
$standings = $board->standings('cfp-2027');                         // best first
```

## Requirements

PHP 8.5 or later, `psr/clock`, `psr/event-dispatcher` and `symfony/polyfill-php86` for `\SortDirection` before PHP 8.6.
A clock such as `symfony/clock`, and an event dispatcher to listen to the events. `doctrine/dbal` 4.5 for the database
stores.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

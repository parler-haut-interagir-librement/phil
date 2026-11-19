# Usage

`ReviewBoard` is the engine. Every method names the campaign it acts on; every action checks the campaign's phase on
the clock first, and refuses with `Refused` when the phase does not allow it.

## The campaign

```php
use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Schedule;
use Phil\ProposalReview\Visibility;

$campaign = new Campaign(
    id: 'costume-contest-2027',
    schedule: new Schedule([
        new PhaseWindow(Phase::Submission, new DateTimeImmutable('2027-05-01 09:00', $zone), new DateTimeImmutable('2027-05-20 17:00', $zone)),
        new PhaseWindow(Phase::Review, new DateTimeImmutable('2027-05-20 17:00', $zone), new DateTimeImmutable('2027-05-21 12:00', $zone)),
        new PhaseWindow(Phase::AuthorResponse, new DateTimeImmutable('2027-05-21 12:00', $zone), new DateTimeImmutable('2027-05-21 14:00', $zone)),
        new PhaseWindow(Phase::Deliberation, new DateTimeImmutable('2027-05-21 14:00', $zone), new DateTimeImmutable('2027-05-21 18:00', $zone)),
    ]),
    criteria: [
        new Criterion('craftsmanship', weight: 3, min: 0, max: 10),
        new Criterion('fidelity', weight: 2, min: 0, max: 10),
        new Criterion('presence', weight: 1, min: 1, max: 5),
    ],
    policy: new ReviewPolicy(
        blind: true,
        minReviews: 3,
        maxReviews: 5,
        aggregation: Aggregation::Median,
        visibility: Visibility::AfterOwnReview,
        assignedOnly: true,
    ),
);

$campaigns->save($campaign);
```

- The schedule's windows come in the order of the phases, each starting where the one before ends. Review and
  deliberation are required; submission and author response are optional. Before the first window the campaign is
  `NotStarted`, after the last one `Closed`. Instants are converted to UTC.
- Each criterion has its own scale and a relative weight: 3 counts three times as much as 1.
- The policy sets how the campaign reviews; its defaults are a blind review, three reviews a proposal, no maximum, the
  mean, the others' reviews shown once one's own is in, and reviewers limited to what they are assigned.

## Phases and what they open

| Phase | Open |
|---|---|
| `NotStarted` | Declaring conflicts, assigning reviewers. |
| `Submission` | `submit()`; conflicts, assignments. |
| `Review` | `review()`, `nextFor()`, pairwise votes; conflicts, assignments. |
| `AuthorResponse` | `mayRespond()` is true for the authors; conflicts, assignments. |
| `Deliberation` | `decide()`, `applyDecisions()`. |
| `Closed` | `applyDecisions()` for decisions still pending. |

Reading — `standings()`, `reviewsVisibleTo()`, `authorsVisibleTo()` — is open in every phase. `phaseOf()` gives the
phase now; `advance()`, called from a scheduled task, records it and dispatches `PhaseChanged` when it changed:

```php
// every few minutes, from a cron job or a Scheduler task
$board->advance('costume-contest-2027');
```

## Proposals

```php
$board->submit('costume-contest-2027', 'entry-17', authors: ['u-201', 'u-202']);
```

The proposal is the application's identifier with its authors' identifiers; its content stays in the application.
Submitting again during submission replaces the authors. A campaign whose proposals come from elsewhere, without a
submission window, saves them straight into the `ProposalStore`.

## Reviewers and conflicts

```php
use Phil\ProposalReview\ConflictOfInterest;

$board->declareConflict(ConflictOfInterest::withAuthor('costume-contest-2027', 'r-7', 'u-201'));
$board->declareConflict(ConflictOfInterest::withProposal('costume-contest-2027', 'r-3', 'entry-22'));

$board->assign('costume-contest-2027', 'entry-17', 'r-4');
$made = $board->assignBalanced('costume-contest-2027', ['r-1', 'r-2', 'r-3', 'r-4', 'r-5', 'r-7'], perProposal: 3);
```

A reviewer is always in conflict with their own proposals; a declared conflict covers one proposal, or every proposal
of one author. A reviewer in conflict is never assigned, never reviews, sees no review and no author of the proposal.
Conflicts and assignments are open until deliberation. [Algorithms](algorithms.md) says how the balanced assignment
chooses.

## Reviewing

```php
use Phil\ProposalReview\Score;

if ($board->mayReview('r-4', 'costume-contest-2027', 'entry-17')) {
    $board->review('costume-contest-2027', 'entry-17', 'r-4', [
        new Score('craftsmanship', 8),
        new Score('fidelity', 9),
        new Score('presence', 4),
    ]);
}

$next = $board->nextFor('r-4', 'costume-contest-2027'); // the least reviewed proposal r-4 may still review, or null
```

A review scores every criterion of the campaign once, within its scale, or is refused with `InvalidInput`. Scoring the
same proposal again replaces the reviewer's review. `mayReview()` is false for a reviewer in conflict, for one not
assigned when the policy limits reviewers to their assignments, and for a new reviewer once the proposal has the most
reviews the policy allows.

What a reviewer sees:

```php
$reviews = $board->reviewsVisibleTo('r-4', 'costume-contest-2027', 'entry-17');
$authors = $board->authorsVisibleTo('r-4', 'costume-contest-2027', 'entry-17');
```

`reviewsVisibleTo()` follows the policy's `Visibility`: always, after one's own review, or never. `authorsVisibleTo()`
gives nothing in a blind campaign until the proposal's decision is applied. Hiding names inside the proposal's content
is the application's part.

## Author response

During the author response window, `mayRespond($author, $campaign, $proposal)` is true for the proposal's authors. The
response itself — a text, a file — belongs to the application, which shows it to the jury.

## Standings and decisions

```php
use Phil\ProposalReview\Decision;

foreach ($board->standings('costume-contest-2027') as $standing) {
    // $standing->proposal, ->reviews, ->score (0 to 1, null before the first review),
    // ->enoughReviews, ->decision (pending or applied, or null)
}

$board->decide('costume-contest-2027', 'entry-17', Decision::Accepted, 'u-chair');
$board->decide('costume-contest-2027', 'entry-22', Decision::Waitlisted, 'u-chair');

$applied = $board->applyDecisions('costume-contest-2027'); // ['entry-17', 'entry-22']
```

`standings()` sorts the best score first; pass `\SortDirection::Ascending` for the lowest first. Proposals without a
score come last either way, and equal scores go by identifier.

Decisions are recorded during deliberation and stay pending — a decision recorded again replaces the pending one —
until `applyDecisions()` applies every pending decision of the campaign at once and dispatches `DecisionsApplied`,
the moment to tell the authors. An applied decision is final.

# Pairwise voting

Some juries find it easier to compare than to score: which of these two costumes, these two game pitches, these two
logos is better? `PairwiseVoting` collects such votes for a campaign and turns them into scores.

```php
use Phil\ProposalReview\Memory\InMemoryVoteLog;
use Phil\ProposalReview\Pairwise\BradleyTerry;
use Phil\ProposalReview\Pairwise\PairwiseVoting;

$voting = new PairwiseVoting($board, $proposalStore, new InMemoryVoteLog(), $clock, new BradleyTerry(), $events);
```

It works on the campaign's proposals and conflicts, through the same `ReviewBoard`, and needs a `VoteLog` (see
[Storage](storage.md)).

## Showing a pair

```php
$pair = $voting->nextPair('v-31', 'logo-contest');

if (null === $pair) {
    // v-31 has judged every pair they may judge
}

// show $pair->left and $pair->right side by side
```

The pair favours the proposals shown least so far, never repeats a pair the voter already judged, and leaves out every
proposal the voter is in conflict with. [Algorithms](algorithms.md#the-pair-draw) gives the exact rule.

## Casting a vote

```php
$vote = $voting->vote('v-31', 'logo-contest', winner: 'logo-4', loser: 'logo-9');
```

Votes are cast during the review phase. A voter in conflict with either proposal is refused. The vote is appended to
the log with its number, its voter and its instant in UTC, and `PairwiseVoteCast` is dispatched. A log is append-only:
a vote is never changed or removed by the engine.

The engine does not decide who may vote, nor how often: the application checks that the voter is a member of the jury,
or of the public for a public vote, and limits the rate.

## Reading the scores

```php
foreach ($voting->scores('logo-contest') as $score) {
    // $score->proposal, ->strength (log scale, 0 for the reference), ->wins, ->losses, ->comparisons()
    // $score->rating(): the strength on the chess rating scale, 1 500 for the reference
}

$chance = $first->chanceAgainst($second); // the model's chance that $first is preferred to $second
```

The scores are computed from the whole log each time, the strongest first; pass `\SortDirection::Ascending` for the
weakest first. The same log always gives the same scores, so anyone holding the log can compute them again and check
the ranking. A proposal without votes has strength 0, like the model's reference.

## Tuning the model

```php
new BradleyTerry(prior: 1.0, iterations: 500, tolerance: 1e-9);
```

- The prior counts virtual games against the reference: a larger prior pulls proposals with few votes harder toward
  the middle, a smaller one lets a few votes speak louder. 1 suits juries of a few people; a public vote with
  thousands of votes can take less.
- The iteration stops when no strength moves by more than the tolerance, or after the iterations allowed.

Scores and pairwise votes may live in the same campaign: a contest may score the finalists after pairwise votes
selected them, or show both rankings side by side.

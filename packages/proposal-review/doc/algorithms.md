# Algorithms

## Scores and aggregation

A review gives each criterion a score on that criterion's scale. `Scoring::review()` brings each score to 0–1 —
`(score − min) / (max − min)` — so that a criterion out of 10 does not outweigh one out of 5, then takes the weighted
average: the review's score, from 0 to 1. A proposal's score aggregates its reviews' scores:

- `Aggregation::Mean`, the average: every review counts as much.
- `Aggregation::Median`, the middle value, or the average of the two middle values: a single harsh or generous
  reviewer moves it little.

A proposal without reviews has no score, and `enoughReviews` says whether it has the policy's minimum. `Scoring` is a
pure class: an application, or a peer review package, may score and aggregate with it without the board.

## Balanced assignment

`assignBalanced($campaign, $reviewers, $perProposal)` gives each proposal up to `$perProposal` reviewers, counting the
ones it already has:

1. The proposals are taken the least assigned first, then by identifier.
2. For each, the candidates are the reviewers given, not yet assigned to it and not in conflict with it, ordered by
   the number of proposals they are assigned in the campaign, then by identifier.
3. The first candidates are assigned, and their load grows at once, before the next proposal.

The result depends only on the proposals, the reviewers and the assignments already made: running it twice changes
nothing the second time. It spreads the load greedily, always to the least loaded reviewer who may take the proposal,
but does not search for the perfectly even split when conflicts make one reviewer the only choice for several
proposals. It costs O(P · R log R) for P proposals and R reviewers, plus the conflict checks.

## Save and next

`nextFor($reviewer, $campaign)` returns, among the proposals the reviewer may review and has not reviewed, the one
with the fewest reviews, and between equals the first identifier. Reviewers who move on with it bring every proposal to
the minimum before any proposal gets many reviews.

## The pair draw

`PairwiseVoting::nextPair($voter, $campaign)`:

1. The eligible proposals are those the voter is not in conflict with.
2. Each one's exposure is the number of votes, by anyone, it took part in.
3. They are ordered by exposure, then by the SHA-256 of the campaign, the voter, the number of votes the voter cast,
   and the proposal — a tiebreak that differs from voter to voter and from vote to vote, so that two voters do not see
   the same pair first, yet can be computed again from the log —, then by identifier.
4. The first proposal in that order is paired with the first one after it in the same order that the voter has not
   compared it with; if the voter compared it with every other, the next proposal is tried. No pair left: null.

Every voter sees each pair at most once, and the proposals seen least are shown first, so that exposure stays even
across the campaign. The cost is O(V + P log P) to read the log and order the proposals, and O(P²) in the worst case,
when the voter has judged almost every pair.

## Pairwise scores

The model gives each proposal i a strength p(i), and the chance that i is preferred to j is p(i) / (p(i) + p(j)).
`BradleyTerry::fit()` finds the strengths that make the logged votes most likely, with a prior of `a` wins and `a`
losses for each proposal against a virtual reference of strength 1. The prior keeps an unbeaten proposal at a finite
strength, pulls the proposals with few votes toward the reference, and fixes the scale, which the votes alone leave
free.

Each iteration has two steps, both of which can only make the votes more likely:

1. The MM update of Hunter (2004), which goes back to Zermelo, for every proposal together from the previous strengths:
   p(i) ← (W(i) + a) / ( Σ_j n(i, j) / (p(i) + p(j)) + 2a / (p(i) + 1) ), where W(i) counts the wins of i and
   n(i, j) the votes between i and j.
2. A common factor c, multiplying every strength, chosen as the root of Σ (1 − c·p(i)) / (1 + c·p(i)), found by sixty
   steps of bisection on log c. The votes do not see the scale; without this step the iteration would crawl along it
   for thousands of steps.

The iteration stops when no log strength moves by more than the tolerance, or after the iterations allowed (500 by
default, at most 10 000). With the prior the likelihood has a single maximum, which the iteration reaches: for two
hundred proposals and twenty thousand votes, within 10⁻⁴ in forty iterations and to the last digit in eighty.

Guarantees:

- Reproducible: the update reads only the counts of wins and of votes per pair, never the order of the log, and
  updates every proposal from the same previous step. The same log gives the same scores.
- Votes about proposals no longer in the campaign are left out.
- A proposal without votes keeps strength 1, a log strength of 0.

Each iteration costs O(P + D) for P proposals and D distinct pairs voted, plus 60 · P for the scale. Measured on a
laptop with PHP 8.5: about a third of a second for two hundred proposals and twenty thousand votes.

# Reference

Every public type of the package, with its role. The classes under `Internal\`, and `Dbal\Tables`, are not part of the
public API.

## The campaign

| Type | Role |
|---|---|
| `Campaign` | An evaluation: its schedule, criteria, policy and the last phase recorded; `criterion()`, `withObservedPhase()`; `Campaign::MAX_CRITERIA`. |
| `Schedule` | The dated windows in phase order, each starting where the one before ends; `phaseAt()`, `has()`. |
| `PhaseWindow` | A phase with its start, included, and end, excluded, in UTC; `contains()`. |
| `Phase` | `NotStarted`, `Submission`, `Review`, `AuthorResponse`, `Deliberation`, `Closed`; `position()`. |
| `Criterion` | What is scored, with its weight and scale; `normalize()`; `Criterion::MAX_WEIGHT`, `Criterion::MAX_SCALE`. |
| `ReviewPolicy` | Blindness, minimum and maximum reviews, aggregation, visibility, assigned reviewers only; `ReviewPolicy::MAX_REVIEWS`. |
| `Aggregation` | `Mean` or `Median`. |
| `Visibility` | `Never`, `AfterOwnReview` or `Always`. |

## Proposals, reviews and decisions

| Type | Role |
|---|---|
| `Proposal` | A proposal's identifier and its authors' identifiers; `isAuthoredBy()`; `Proposal::MAX_AUTHORS`. |
| `ConflictOfInterest` | A reviewer kept from one proposal, or from every proposal of one author; `withProposal()`, `withAuthor()`, `concerns()`. |
| `Score` | A criterion and the score given on it. |
| `Review` | A reviewer's scores for a proposal and the instant, in UTC; `scoreFor()`. |
| `Decision` | `Accepted`, `Waitlisted` or `Rejected`. |
| `DecisionRecord` | A decision, who recorded it and when, and when it was applied; `isPending()`, `appliedAt()`. |
| `Standing` | A proposal's reviews, score, whether it has enough reviews, and its decision. |
| `Scoring` | The arithmetic of scores: `check()`, `review()`, `aggregate()`. |

## The services

| Type | Role |
|---|---|
| `ReviewBoard` | The engine: `campaign()`, `phaseOf()`, `advance()`, `submit()`, `proposal()`, `declareConflict()`, `isInConflict()`, `assign()`, `assignBalanced()`, `mayReview()`, `review()`, `nextFor()`, `reviewsVisibleTo()`, `authorsVisibleTo()`, `mayRespond()`, `standings()`, `decide()`, `applyDecisions()`. |
| `Pairwise\PairwiseVoting` | Pairwise votes: `nextPair()`, `vote()`, `scores()`. |
| `Pairwise\BradleyTerry` | The Bradley–Terry fit of a vote log: `fit()`; `BradleyTerry::MAX_ITERATIONS`. |
| `Pairwise\Matchup` | Two proposals to show side by side. |
| `Pairwise\Vote` | One vote of the log: number, voter, winner, loser, instant in UTC. |
| `Pairwise\PairwiseScore` | A proposal's strength, wins and losses; `comparisons()`, `chanceAgainst()`, `rating()`. |

## The stores

| Type | Role |
|---|---|
| `Contract\CampaignStore` | Where campaigns are kept. |
| `Contract\ProposalStore` | Where proposals, assignments and conflicts are kept. |
| `Contract\ReviewStore` | Where reviews and decisions are kept. |
| `Contract\VoteLog` | The append-only, numbered log of pairwise votes. |
| `Memory\InMemoryCampaignStore`, `Memory\InMemoryProposalStore`, `Memory\InMemoryReviewStore`, `Memory\InMemoryVoteLog` | The four ports in memory. |
| `Dbal\DbalCampaignStore`, `Dbal\DbalProposalStore`, `Dbal\DbalReviewStore`, `Dbal\DbalVoteLog` | The four ports on Doctrine DBAL, with `configureSchema()`; `DbalVoteLog::MAX_ATTEMPTS`. |

## The events

| Type | Role |
|---|---|
| `Event\PhaseChanged` | A campaign entered a new phase: campaign, from, to. |
| `Event\ReviewSubmitted` | A proposal was scored: campaign, proposal, reviewer. |
| `Event\DecisionRecorded` | A pending decision was recorded: campaign, proposal. |
| `Event\DecisionsApplied` | Pending decisions were applied: campaign, proposals. |
| `Event\PairwiseVoteCast` | A vote was appended: campaign, number. |

## The exceptions

| Type | Role |
|---|---|
| `Exception\ProposalReviewException` | Every exception of the package, to catch them together. |
| `Exception\InvalidInput` | A value out of bounds: an identifier, a date, a weight, a score, a setting. |
| `Exception\Refused` | An action the campaign does not allow now or to this person. |
| `Exception\NotFound` | A campaign or proposal the stores do not have. |

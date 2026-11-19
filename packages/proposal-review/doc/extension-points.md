# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep campaigns, proposals, reviews and votes in a database | Use the stores under `Dbal\` on your DBAL connection |
| Name the tables | Pass a prefix to each DBAL store, and build the tables from their `configureSchema()` |
| Keep campaigns, proposals, reviews and votes in your storage | Implement `Contract\CampaignStore`, `Contract\ProposalStore`, `Contract\ReviewStore` and `Contract\VoteLog`, as [Storage](storage.md) describes |
| React to phases, reviews, decisions and votes | Pass a PSR-14 event dispatcher and listen to the events of `Event\` |
| Choose how a campaign reviews | Pass a `ReviewPolicy`: blindness, minimum and maximum reviews, `Aggregation`, `Visibility`, assigned reviewers only |
| Weigh the criteria | Give each `Criterion` its weight and scale |
| Tune the pairwise scores | Pass a `Pairwise\BradleyTerry` with another prior, iteration count or tolerance to `PairwiseVoting` |
| Score elsewhere — a peer review, a form | Call `Scoring::check()`, `Scoring::review()` and `Scoring::aggregate()`, which need neither the board nor a store |
| Fit Bradley–Terry to other comparisons | Call `BradleyTerry::fit()` with a list of identifiers and a list of `Pairwise\Vote` |
| Control time in tests | Pass a PSR-20 clock such as Symfony's `MockClock` |

## A base for peer review

The engine separates what is generic — dated phases, weighted criteria on their own scales, aggregation, conflicts,
batch decisions — from what is a jury's: the board's methods. A peer review, where the authors review each other's work
in phases, reuses the value objects (`Schedule`, `PhaseWindow`, `Criterion`, `Score`, `ConflictOfInterest`), `Scoring`
and the store ports, and adds its own service for the parts a jury does not have, such as reviewing the reviews.

The classes under `Internal\`, and `Dbal\Tables`, are not extension points: they may change in any version.

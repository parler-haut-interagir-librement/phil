<!-- phil-dev source-sha256=8e80dda2d97c1df8c63e566b81a4b87e98204b5e4c460f104773fd600286331b -->
# Référence

Chaque type public du paquet, avec son rôle. Les classes sous `Internal\`, et `Dbal\Tables`, ne font pas partie de l'API publique.

## La campagne

| Type | Rôle |
|---|---|
| `Campaign` | Une évaluation : son calendrier, ses critères, sa politique et la dernière phase enregistrée ; `criterion()`, `withObservedPhase()` ; `Campaign::MAX_CRITERIA`. |
| `Schedule` | Les fenêtres datées dans l'ordre des phases, chacune commençant où la précédente finit ; `phaseAt()`, `has()`. |
| `PhaseWindow` | Une phase avec son début, inclus, et sa fin, exclue, en UTC ; `contains()`. |
| `Phase` | `NotStarted`, `Submission`, `Review`, `AuthorResponse`, `Deliberation`, `Closed` ; `position()`. |
| `Criterion` | Ce qui est noté, avec son poids et son échelle ; `normalize()` ; `Criterion::MAX_WEIGHT`, `Criterion::MAX_SCALE`. |
| `ReviewPolicy` | L'aveugle, le minimum et le maximum d'évaluations, l'agrégation, la visibilité, les évaluateurs assignés seulement ; `ReviewPolicy::MAX_REVIEWS`. |
| `Aggregation` | `Mean` ou `Median`. |
| `Visibility` | `Never`, `AfterOwnReview` ou `Always`. |

## Propositions, évaluations et décisions

| Type | Rôle |
|---|---|
| `Proposal` | L'identifiant d'une proposition et ceux de ses auteurs ; `isAuthoredBy()` ; `Proposal::MAX_AUTHORS`. |
| `ConflictOfInterest` | Un évaluateur écarté d'une proposition, ou de toutes les propositions d'un auteur ; `withProposal()`, `withAuthor()`, `concerns()`. |
| `Score` | Un critère et la note donnée sur lui. |
| `Review` | Les notes d'un évaluateur pour une proposition et l'instant, en UTC ; `scoreFor()`. |
| `Decision` | `Accepted`, `Waitlisted` ou `Rejected`. |
| `DecisionRecord` | Une décision, qui l'a consignée et quand, et quand elle a été appliquée ; `isPending()`, `appliedAt()`. |
| `Standing` | Les évaluations d'une proposition, sa note, si elle a assez d'évaluations, et sa décision. |
| `Scoring` | L'arithmétique des notes : `check()`, `review()`, `aggregate()`. |

## Les services

| Type | Rôle |
|---|---|
| `ReviewBoard` | Le moteur : `campaign()`, `phaseOf()`, `advance()`, `submit()`, `proposal()`, `declareConflict()`, `isInConflict()`, `assign()`, `assignBalanced()`, `mayReview()`, `review()`, `nextFor()`, `reviewsVisibleTo()`, `authorsVisibleTo()`, `mayRespond()`, `standings()`, `decide()`, `applyDecisions()`. |
| `Pairwise\PairwiseVoting` | Les votes par paires : `nextPair()`, `vote()`, `scores()`. |
| `Pairwise\BradleyTerry` | L'ajustement de Bradley–Terry d'un journal de votes : `fit()` ; `BradleyTerry::MAX_ITERATIONS`. |
| `Pairwise\Matchup` | Deux propositions à montrer côte à côte. |
| `Pairwise\Vote` | Un vote du journal : numéro, votant, gagnante, perdante, instant en UTC. |
| `Pairwise\PairwiseScore` | La force, les victoires et les défaites d'une proposition ; `comparisons()`, `chanceAgainst()`, `rating()`. |

## Les magasins

| Type | Rôle |
|---|---|
| `Contract\CampaignStore` | Où les campagnes sont conservées. |
| `Contract\ProposalStore` | Où les propositions, les assignations et les conflits sont conservés. |
| `Contract\ReviewStore` | Où les évaluations et les décisions sont conservées. |
| `Contract\VoteLog` | Le journal numéroté, en ajout seul, des votes par paires. |
| `Memory\InMemoryCampaignStore`, `Memory\InMemoryProposalStore`, `Memory\InMemoryReviewStore`, `Memory\InMemoryVoteLog` | Les quatre ports en mémoire. |
| `Dbal\DbalCampaignStore`, `Dbal\DbalProposalStore`, `Dbal\DbalReviewStore`, `Dbal\DbalVoteLog` | Les quatre ports sur Doctrine DBAL, avec `configureSchema()` ; `DbalVoteLog::MAX_ATTEMPTS`. |

## Les événements

| Type | Rôle |
|---|---|
| `Event\PhaseChanged` | Une campagne est entrée dans une nouvelle phase : campagne, de, vers. |
| `Event\ReviewSubmitted` | Une proposition a été notée : campagne, proposition, évaluateur. |
| `Event\DecisionRecorded` | Une décision en attente a été consignée : campagne, proposition. |
| `Event\DecisionsApplied` | Des décisions en attente ont été appliquées : campagne, propositions. |
| `Event\PairwiseVoteCast` | Un vote a été ajouté : campagne, numéro. |

## Les exceptions

| Type | Rôle |
|---|---|
| `Exception\ProposalReviewException` | Toutes les exceptions du paquet, pour les attraper ensemble. |
| `Exception\InvalidInput` | Une valeur hors bornes : un identifiant, une date, un poids, une note, un réglage. |
| `Exception\Refused` | Une action que la campagne ne permet pas maintenant ou à cette personne. |
| `Exception\NotFound` | Une campagne ou une proposition que les magasins n'ont pas. |

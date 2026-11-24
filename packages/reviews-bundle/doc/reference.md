# Reference

Every public type of the package, with its role.

## Domain

| Type | Role |
|---|---|
| `Domain\Model\Review` | A review: content, compensation, verified mark, origin, state, criterion, response, contest, votes. |
| `Domain\ValueObject\SubjectRef` | A subject: a declared type and the application's identifier. |
| `Domain\ValueObject\MemberId` | A member, by a stable identifier that is not an email address. |
| `Domain\ValueObject\ReviewId` | A review's identifier, a UUID v7. |
| `Domain\ValueObject\ReviewContent` | The overall score, the scores of the criteria, title, text and superlatives. |
| `Domain\ValueObject\Compensation` | Whether the author received something in exchange, and what. |
| `Domain\ValueObject\Response` | The public response of the merchant or the organiser. |
| `Domain\ValueObject\Contest` | The contest of a withdrawal, its evidence and its outcome. |
| `Domain\ValueObject\Participation` | A member's purchase of or attendance at a subject, and when. |
| `Domain\ValueObject\BayesianPrior` | The fictitious reviews of the weighted average, and the minimum to show an average. |
| `Domain\ValueObject\RatingSummary` | The ratings of a subject. |
| `Domain\ValueObject\CriterionRating` | The average of one criterion. |
| `Domain\Status\ReviewStatus` | `Published` or `Withdrawn`. |
| `Domain\Status\Origin` | `Site` or `Transferred`. |
| `Domain\Status\Verification` | `Purchase` or `Attendance`. |
| `Domain\Status\ContestOutcome` | `Upheld` or `Rejected`. |
| `Domain\Status\ReviewOrder` | `Helpful`, `Newest`, `Highest` or `Lowest`. |
| `Domain\Contract\ReviewRepository` | Where the reviews and their votes are kept. |
| `Domain\Contract\RatingRepository` | Where the ratings of each subject are kept. |
| `Domain\Texts` | The checks of texts, codes and identifiers. |
| `Domain\Exception\ReviewsException` | What every failure of the bundle is. |
| `Domain\Exception\InvalidReview` | A value refused. |
| `Domain\Exception\ReviewRefused` | An action the rules refuse; its message can be shown. |
| `Domain\Exception\DuplicateReview` | A second review the store refused. |

## Application

| Type | Role |
|---|---|
| `Application\Reviews` | The entry point: write, change, vote, respond, withdraw, hold, publish, contest. |
| `Application\Ratings` | The ratings: read, computed again, suspended, ranked. |
| `Application\SubjectType`, `Application\SubjectTypes` | The declared types and their settings. |
| `Application\RemovalCriteria` | The published criteria of withdrawal. |
| `Application\ErasureMode` | `Anonymise` or `Remove`. |
| `Application\NoParticipations` | The participations of an application that gives none. |
| `Application\IdentifierDirectory` | The names of an application that gives none. |
| `Application\Contract\*` | The ports ([Extension points](extension-points.md)). |
| `Application\Event\*` | The events ([Extension points](extension-points.md#events)). |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Doctrine\DbalReviewRepository`, `DbalRatingRepository` | The DBAL stores. |
| `Infrastructure\Doctrine\ReviewTables` | The tables, added to a schema. |
| `Infrastructure\Doctrine\SchemaListener` | Adds the tables to the schema the ORM generates. |
| `Infrastructure\Doctrine\Columns` | The values of a row narrowed to what the columns hold. |
| `Infrastructure\Http\ReviewsController` | The pages and their forms. |
| `Infrastructure\Http\ReviewsRequest` | The stateless CSRF check of the forms. |
| `Infrastructure\Http\SpamGuard` | The review form held to `phil/spam-protection-bundle`. |
| `Infrastructure\Http\RateLimitRoutes` | The rules of `phil/rate-limit-bundle` given to the forms. |
| `Infrastructure\Moderation\ReviewTarget` | The target `review` of `phil/moderation-bundle`. |
| `Infrastructure\Moderation\ModerationDecisionListener` | A decision kept within the published criteria. |
| `Infrastructure\Privacy\ReviewPrivacy` | The module `reviews` of `phil/privacy-bundle`. |
| `Infrastructure\StructuredData\AggregateRatings` | The schema.org `AggregateRating` of a subject. |
| `Infrastructure\Security\SecurityCurrentReviewer` | The member signed in through Symfony Security. |
| `Infrastructure\Security\RoleResponders` | Those Symfony Security lets respond. |
| `Infrastructure\Twig\ReviewsExtension`, `KitComponents` | The Twig functions, and the names of the recipes of the kit. |
| `Infrastructure\Console\RecomputeCommand` | `phil:reviews:recompute`. |
| `Infrastructure\DependencyInjection\ReviewsServices`, `KitSubdirectoryPass` | The services of the bundle. |
| `PhilReviewsBundle` | The bundle and its configuration `phil_reviews`. |

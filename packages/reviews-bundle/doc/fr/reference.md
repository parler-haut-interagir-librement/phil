<!-- phil-dev source-sha256=9e096c82d28a258727e9f7bad2c49b3933bad08539a8fe1943e5bf5e8b25b426 -->
# Référence

Chaque type public du paquet, avec son rôle.

## Domaine

| Type | Rôle |
|---|---|
| `Domain\Model\Review` | Un avis : contenu, compensation, mention vérifiée, origine, état, critère, réponse, contestation, votes. |
| `Domain\ValueObject\SubjectRef` | Un sujet : un type déclaré et l'identifiant de l'application. |
| `Domain\ValueObject\MemberId` | Un membre, par un identifiant stable qui n'est pas une adresse courriel. |
| `Domain\ValueObject\ReviewId` | L'identifiant d'un avis, un UUID v7. |
| `Domain\ValueObject\ReviewContent` | La note globale, les notes des critères, le titre, le texte et les superlatifs. |
| `Domain\ValueObject\Compensation` | Si l'auteur a reçu quelque chose en échange, et quoi. |
| `Domain\ValueObject\Response` | La réponse publique du marchand ou de l'organisateur. |
| `Domain\ValueObject\Contest` | La contestation d'un retrait, ses preuves et son issue. |
| `Domain\ValueObject\Participation` | L'achat d'un sujet ou la présence d'un membre à un sujet, et quand. |
| `Domain\ValueObject\BayesianPrior` | Les avis fictifs de la moyenne pondérée, et le minimum pour afficher une moyenne. |
| `Domain\ValueObject\RatingSummary` | Les notes d'un sujet. |
| `Domain\ValueObject\CriterionRating` | La moyenne d'un critère. |
| `Domain\Status\ReviewStatus` | `Published` ou `Withdrawn`. |
| `Domain\Status\Origin` | `Site` ou `Transferred`. |
| `Domain\Status\Verification` | `Purchase` ou `Attendance`. |
| `Domain\Status\ContestOutcome` | `Upheld` ou `Rejected`. |
| `Domain\Status\ReviewOrder` | `Helpful`, `Newest`, `Highest` ou `Lowest`. |
| `Domain\Contract\ReviewRepository` | Où les avis et leurs votes sont conservés. |
| `Domain\Contract\RatingRepository` | Où les notes de chaque sujet sont conservées. |
| `Domain\Texts` | Les vérifications des textes, des codes et des identifiants. |
| `Domain\Exception\ReviewsException` | Ce que chaque échec du bundle est. |
| `Domain\Exception\InvalidReview` | Une valeur refusée. |
| `Domain\Exception\ReviewRefused` | Une action que les règles refusent ; son message peut être affiché. |
| `Domain\Exception\DuplicateReview` | Un second avis que le dépôt a refusé. |

## Application

| Type | Rôle |
|---|---|
| `Application\Reviews` | Le point d'entrée : écrire, modifier, voter, répondre, retirer, retenir, publier, contester. |
| `Application\Ratings` | Les notes : lues, recalculées, suspendues, classées. |
| `Application\SubjectType`, `Application\SubjectTypes` | Les types déclarés et leurs réglages. |
| `Application\RemovalCriteria` | Les critères publiés de retrait. |
| `Application\ErasureMode` | `Anonymise` ou `Remove`. |
| `Application\NoParticipations` | Les participations d'une application qui n'en donne aucune. |
| `Application\IdentifierDirectory` | Les noms d'une application qui n'en donne aucun. |
| `Application\Contract\*` | Les ports ([Points d'extension](extension-points.md)). |
| `Application\Event\*` | Les événements ([Points d'extension](extension-points.md#les-événements)). |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Doctrine\DbalReviewRepository`, `DbalRatingRepository` | Les dépôts DBAL. |
| `Infrastructure\Doctrine\ReviewTables` | Les tables, ajoutées à un schéma. |
| `Infrastructure\Doctrine\SchemaListener` | Ajoute les tables au schéma que l'ORM génère. |
| `Infrastructure\Doctrine\Columns` | Les valeurs d'une ligne réduites à ce que contiennent les colonnes. |
| `Infrastructure\Http\ReviewsController` | Les pages et leurs formulaires. |
| `Infrastructure\Http\ReviewsRequest` | La vérification CSRF sans état des formulaires. |
| `Infrastructure\Http\SpamGuard` | Le formulaire d'avis soumis à `phil/spam-protection-bundle`. |
| `Infrastructure\Http\RateLimitRoutes` | Les règles de `phil/rate-limit-bundle` données aux formulaires. |
| `Infrastructure\Moderation\ReviewTarget` | La cible `review` de `phil/moderation-bundle`. |
| `Infrastructure\Moderation\ModerationDecisionListener` | Une décision maintenue dans les critères publiés. |
| `Infrastructure\Privacy\ReviewPrivacy` | Le module `reviews` de `phil/privacy-bundle`. |
| `Infrastructure\StructuredData\AggregateRatings` | Le `AggregateRating` de schema.org d'un sujet. |
| `Infrastructure\Security\SecurityCurrentReviewer` | Le membre connecté par Symfony Security. |
| `Infrastructure\Security\RoleResponders` | Ceux que Symfony Security laisse répondre. |
| `Infrastructure\Twig\ReviewsExtension`, `KitComponents` | Les fonctions Twig, et les noms des recettes du kit. |
| `Infrastructure\Console\RecomputeCommand` | `phil:reviews:recompute`. |
| `Infrastructure\DependencyInjection\ReviewsServices`, `KitSubdirectoryPass` | Les services du bundle. |
| `PhilReviewsBundle` | Le bundle et sa configuration `phil_reviews`. |

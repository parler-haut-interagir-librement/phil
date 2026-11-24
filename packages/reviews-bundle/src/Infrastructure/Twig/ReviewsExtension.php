<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Twig;

use Phil\ReviewsBundle\Application\Contract\ReviewerDirectory;
use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Application\SubjectType;
use Phil\ReviewsBundle\Application\SubjectTypes;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\StructuredData\AggregateRatings;
use Phil\StructuredData\Node;
use Twig\Attribute\AsTwigFunction;

/**
 * The functions of the templates: `phil_reviews_kit()`, the name under which a recipe of the kit is installed;
 * `phil_reviews_name()`, the name a review shows of its author; `phil_reviews_summary()` and `phil_reviews_type()`, the
 * ratings of a subject and the settings of its type, for the summary on the application's own pages; and
 * `phil_reviews_aggregate_rating()`, the schema.org node of the ratings with phil/structured-data installed.
 */
final readonly class ReviewsExtension
{
    public function __construct(
        private KitComponents $kit,
        private ReviewerDirectory $directory,
        private Ratings $ratings,
        private SubjectTypes $types,
        private ?AggregateRatings $aggregateRatings = null,
    ) {
    }

    /**
     * `{% component (phil_reviews_kit('Rating')) %}`.
     */
    #[AsTwigFunction(name: 'phil_reviews_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'phil_reviews_name')]
    public function name(?MemberId $member): ?string
    {
        return $member instanceof MemberId ? $this->directory->displayName($member) : null;
    }

    /**
     * The ratings of the subject, or `null` when its type is not declared or its identifier is malformed.
     */
    #[AsTwigFunction(name: 'phil_reviews_summary')]
    public function summary(string $type, string $id): ?RatingSummary
    {
        try {
            $subject = SubjectRef::of($type, $id);
            $this->types->get($type);
        } catch (InvalidReview) {
            return null;
        }

        return $this->ratings->summary($subject);
    }

    /**
     * The settings of a declared type: its scale, criteria, superlatives and prior.
     */
    #[AsTwigFunction(name: 'phil_reviews_type')]
    public function type(string $type): ?SubjectType
    {
        return $this->types->has($type) ? $this->types->get($type) : null;
    }

    /**
     * The `AggregateRating` of the subject, or `null` when there is none to give.
     */
    #[AsTwigFunction(name: 'phil_reviews_aggregate_rating')]
    public function aggregateRating(string $type, string $id): ?Node
    {
        try {
            return $this->aggregateRatings?->of(SubjectRef::of($type, $id));
        } catch (InvalidReview) {
            return null;
        }
    }
}

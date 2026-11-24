<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\StructuredData;

use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Application\SubjectTypes;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\StructuredData\Node;

/**
 * The schema.org `AggregateRating` of a subject, through phil/structured-data, for the application to set as the
 * `aggregateRating` of the node of its product, game or place. There is none without a published review, below the
 * minimum of the type, or when a published review was not collected on the site.
 */
final readonly class AggregateRatings
{
    public function __construct(
        private Ratings $ratings,
        private SubjectTypes $types,
    ) {
    }

    public function of(SubjectRef $subject): ?Node
    {
        try {
            return $this->node($this->ratings->summary($subject));
        } catch (InvalidReview) {
            return null;
        }
    }

    /**
     * @throws InvalidReview when the type of the subject is not declared
     */
    public function node(RatingSummary $summary): ?Node
    {
        $type = $this->types->get($summary->subject->type);
        $mean = $summary->mean();

        if (null === $mean || !$summary->allFromSite() || !$type->prior->shows($summary->count)) {
            return null;
        }

        return Node::of('AggregateRating', [
            'ratingValue' => round($mean, 2),
            'bestRating' => $type->scale,
            'worstRating' => 1,
            'ratingCount' => $summary->count,
        ]);
    }
}

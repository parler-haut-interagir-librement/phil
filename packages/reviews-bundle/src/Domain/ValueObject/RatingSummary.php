<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

/**
 * The ratings of a subject, computed from its published reviews and kept beside them: how many, the sum of their
 * overall scores, their weighted average under the prior of the type, the average of each criterion, how often each
 * superlative was given, how many were not collected on the site, and whether new reviews are suspended.
 */
final readonly class RatingSummary
{
    /**
     * @param array<string, CriterionRating> $criteria     by code
     * @param array<string, int>             $superlatives how often each label was given, by label
     */
    public function __construct(
        public SubjectRef $subject,
        public int $count = 0,
        public int $sum = 0,
        public ?float $weighted = null,
        public array $criteria = [],
        public array $superlatives = [],
        public int $foreign = 0,
        public bool $suspended = false,
    ) {
    }

    public static function empty(SubjectRef $subject, bool $suspended = false): self
    {
        return new self($subject, suspended: $suspended);
    }

    /**
     * The plain average of the overall scores, or `null` without a review.
     */
    public function mean(): ?float
    {
        return 0 === $this->count ? null : $this->sum / $this->count;
    }

    /**
     * Whether every published review was collected on the site.
     */
    public function allFromSite(): bool
    {
        return 0 === $this->foreign;
    }

    /**
     * The labels given most often, at most `$limit`, in decreasing order of how often; a tie keeps the order of the
     * labels as the type lists them.
     *
     * @param list<string> $order the labels of the type, in their order
     *
     * @return array<string, int>
     */
    public function topSuperlatives(array $order, int $limit): array
    {
        $given = array_filter($this->superlatives, static fn (int $times): bool => $times > 0);
        $rank = array_flip($order);
        uksort($given, static fn (string $a, string $b): int => [$given[$b], $rank[$a] ?? \PHP_INT_MAX, $a] <=> [$given[$a], $rank[$b] ?? \PHP_INT_MAX, $b]);

        return \array_slice($given, 0, max(0, $limit), true);
    }

    public function withSuspended(bool $suspended): self
    {
        return new self($this->subject, $this->count, $this->sum, $this->weighted, $this->criteria, $this->superlatives, $this->foreign, $suspended);
    }
}

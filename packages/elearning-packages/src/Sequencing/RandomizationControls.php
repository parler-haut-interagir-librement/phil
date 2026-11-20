<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The selection and the reordering of the children of an activity, `<imsss:randomizationControls>`: how many of them
 * an attempt offers and when they are drawn, and whether and when their order is shuffled.
 */
final readonly class RandomizationControls
{
    public function __construct(
        public RandomizationTiming $selectionTiming = RandomizationTiming::Never,
        public ?int $selectCount = null,
        public RandomizationTiming $randomizationTiming = RandomizationTiming::Never,
        public bool $reorderChildren = false,
    ) {
    }

    public function isActive(): bool
    {
        return (RandomizationTiming::Never !== $this->selectionTiming && null !== $this->selectCount)
            || (RandomizationTiming::Never !== $this->randomizationTiming && $this->reorderChildren);
    }

    /**
     * Whether a new attempt on the activity draws its children again.
     */
    public function drawsOnEachAttempt(): bool
    {
        return RandomizationTiming::OnEachNewAttempt === $this->selectionTiming || RandomizationTiming::OnEachNewAttempt === $this->randomizationTiming;
    }
}

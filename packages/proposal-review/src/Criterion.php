<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Internal\Identifier;

/**
 * What a review scores — originality, clarity, fit with the theme, craftsmanship —, on its own scale, with its weight
 * in the total. Weights are relative: 2 counts twice as much as 1.
 */
final readonly class Criterion
{
    public const int MAX_WEIGHT = 1_000;

    public const int MAX_SCALE = 1_000;

    public string $id;

    /**
     * @throws InvalidInput
     */
    public function __construct(
        string $id,
        public int $weight = 1,
        public int $min = 0,
        public int $max = 5,
    ) {
        $this->id = Identifier::check($id, 'criterion');

        if ($weight < 1 || $weight > self::MAX_WEIGHT) {
            throw InvalidInput::because(\sprintf('The weight of criterion "%s" is 1 to %d.', $id, self::MAX_WEIGHT));
        }

        if ($min < -self::MAX_SCALE || $max > self::MAX_SCALE || $max <= $min) {
            throw InvalidInput::because(\sprintf('The scale of criterion "%s" runs upward within -%2$d to %2$d.', $id, self::MAX_SCALE));
        }
    }

    /**
     * A score brought to 0 for the bottom of the scale and 1 for its top.
     */
    public function normalize(int $value): float
    {
        return ($value - $this->min) / ($this->max - $this->min);
    }
}

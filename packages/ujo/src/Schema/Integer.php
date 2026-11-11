<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * A whole number, within bounds when the field has them.
 */
final readonly class Integer implements Field
{
    public function __construct(
        private ?int $min = null,
        private ?int $max = null,
    ) {
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): int
    {
        if (!\is_int($value)) {
            throw InvalidDocument::at($path, 'a whole number is expected');
        }

        if (null !== $this->min && $value < $this->min) {
            throw InvalidDocument::at($path, \sprintf('%d is below the minimum %d', $value, $this->min));
        }

        if (null !== $this->max && $value > $this->max) {
            throw InvalidDocument::at($path, \sprintf('%d is above the maximum %d', $value, $this->max));
        }

        return $value;
    }
}

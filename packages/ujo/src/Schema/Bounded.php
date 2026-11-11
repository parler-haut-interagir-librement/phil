<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Contract\Sized;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Validation;

/**
 * A field whose size — the characters of a text, the items of a list — must be within bounds for publication. A draft
 * may be shorter or longer: the bounds are checked only at `Validation::Publication`, after the field itself.
 */
final readonly class Bounded implements Sized
{
    public function __construct(
        private Sized $field,
        private ?int $min = null,
        private ?int $max = null,
    ) {
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): mixed
    {
        $normalized = $this->field->normalize($value, $path, $context);

        if (Validation::Publication !== $context->level()) {
            return $normalized;
        }

        $size = $this->field->size($normalized);

        if (null !== $this->min && $size < $this->min) {
            throw InvalidDocument::at($path, \sprintf('at least %d %s are required to publish, %d are given', $this->min, $this->field->unit(), $size));
        }

        if (null !== $this->max && $size > $this->max) {
            throw InvalidDocument::at($path, \sprintf('at most %d %s are allowed to publish, %d are given', $this->max, $this->field->unit(), $size));
        }

        return $normalized;
    }

    #[Override]
    public function size(mixed $value): int
    {
        return $this->field->size($value);
    }

    #[Override]
    public function unit(): string
    {
        return $this->field->unit();
    }
}

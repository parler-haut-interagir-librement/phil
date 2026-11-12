<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Stringable;

/**
 * Three uppercase letters; anything else is refused with `InvalidLabelCode`, a `DomainError`.
 */
final readonly class LabelCode implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Z]{3}$/D', $value)) {
            throw new InvalidLabelCode(\sprintf('"%s" is not a label code.', $value));
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

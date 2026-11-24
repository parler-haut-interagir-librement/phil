<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Symfony\Component\Uid\Uuid;

/**
 * A review's identifier: a UUID in its canonical lowercase form.
 */
final readonly class ReviewId
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * A new identifier, a UUID v7, ordered by time.
     */
    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    /**
     * @throws InvalidReview when the value is not a UUID in its canonical lowercase form
     */
    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value) || strtolower($value) !== $value) {
            throw InvalidReview::because('A review identifier is a UUID in lowercase.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

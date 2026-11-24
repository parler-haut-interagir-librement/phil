<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Texts;

/**
 * A member, by the stable identifier the application gives them. An email address is refused: an identifier kept
 * beside every review must not be a way to reach the person.
 */
final readonly class MemberId
{
    public const int MAX_LENGTH = 190;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidReview when the identifier is malformed or holds "@"
     */
    public static function fromString(string $value): self
    {
        Texts::identifier($value, self::MAX_LENGTH, 'A member identifier');

        if (str_contains($value, '@')) {
            throw InvalidReview::because('A member identifier is not an email address.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

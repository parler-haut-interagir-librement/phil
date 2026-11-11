<?php

declare(strict_types=1);

namespace Phil\Slug;

use Stringable;

/**
 * One URL segment, in one spelling: `my-first-article`.
 *
 * **Lowercase ASCII letters and digits, in words joined by single hyphens**, at most 255 characters. A slug is
 * looked up by equality on this string, so `My-Article` beside `my-article` would be two addresses for one page:
 * this type refuses what cannot be a slug, and never fixes it. Turning a text into a slug is a `Slugger`'s.
 */
final readonly class Slug implements Stringable
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidSlug when the text is longer than 255 characters, or not lowercase words joined by hyphens
     */
    public static function fromString(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidSlug::because(\sprintf('A slug is at most %d characters long.', self::MAX_LENGTH));
        }

        if (1 !== preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value)) {
            throw InvalidSlug::because(\sprintf('"%s" is not a slug of lowercase words joined by hyphens.', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

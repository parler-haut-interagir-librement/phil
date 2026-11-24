<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Domain\ValueObject;

use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use Stringable;

/**
 * A tag an archived issue is filed under, and the archive filtered by: a slug of at most 64 characters, `events` or
 * `game-night`, in one spelling, so that a tag names one page of the archive.
 */
final readonly class Tag implements Stringable
{
    public const int MAX_LENGTH = 64;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidArchiveValue when the text is not a slug of at most 64 characters
     */
    public static function of(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidArchiveValue::because(\sprintf('A tag holds at most %d characters.', self::MAX_LENGTH));
        }

        try {
            return new self(Slug::fromString($value)->value);
        } catch (InvalidSlug) {
            throw InvalidArchiveValue::because(\sprintf('"%s" is not a tag: lowercase letters and digits, in words joined by single hyphens.', $value));
        }
    }

    /**
     * Distinct tags, sorted.
     *
     * @param iterable<string> $values
     *
     * @return list<self>
     *
     * @throws InvalidArchiveValue when one is not a tag, or more than 16 are given
     */
    public static function list(iterable $values): array
    {
        $tags = [];

        foreach ($values as $value) {
            $tag = self::of($value);
            $tags[$tag->value] = $tag;
        }

        if (\count($tags) > ArchiveFilter::MAX_TAGS) {
            throw InvalidArchiveValue::because(\sprintf('An issue is filed under at most %d tags.', ArchiveFilter::MAX_TAGS));
        }

        ksort($tags, \SORT_STRING);

        return array_values($tags);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

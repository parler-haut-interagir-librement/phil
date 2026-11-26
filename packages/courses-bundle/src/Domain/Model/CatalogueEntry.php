<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;

/**
 * A course at its place in a catalogue: whether a learner who sees the catalogue enrolls in it themselves, its price
 * there when it is sold — a course is free without one —, and the tags the catalogue is filtered by. A course may be
 * offered by several catalogues, free in one and sold in another.
 */
final class CatalogueEntry
{
    /** The type of the sellable reference that names a course sold from a catalogue. */
    public const string SELLABLE_TYPE = 'course';

    public const int MAX_TAGS = 10;

    public const int TAG_LENGTH = 50;

    /** @var list<string> */
    public readonly array $tags;

    /**
     * @param int<0, max>  $position
     * @param list<string> $tags     trimmed and lowercased, each kept once in the order given
     *
     * @throws InvalidCourse when a tag is empty, too long or holds other characters than letters, digits, spaces and
     *                       `-`, or there are too many
     */
    public function __construct(
        public readonly CatalogueId $catalogue,
        public readonly CourseId $course,
        public readonly int $position,
        public readonly bool $selfEnrollment = true,
        public readonly ?CoursePrice $price = null,
        array $tags = [],
    ) {
        $kept = [];

        foreach ($tags as $tag) {
            $tag = self::tag($tag);
            $kept[$tag] = $tag;
        }

        if (\count($kept) > self::MAX_TAGS) {
            throw InvalidCourse::because(\sprintf('A course holds %d tags in a catalogue at most.', self::MAX_TAGS));
        }

        $this->tags = array_values($kept);
    }

    /**
     * A tag as an entry keeps it: trimmed and lowercased.
     *
     * @throws InvalidCourse when it is empty, too long or holds other characters than letters, digits, spaces and `-`
     */
    public static function tag(string $tag): string
    {
        $tag = mb_strtolower(trim($tag));

        if (mb_strlen($tag) > self::TAG_LENGTH || 1 !== preg_match('/^[\p{L}\p{N}][\p{L}\p{N} \-]*$/Du', $tag)) {
            throw InvalidCourse::because(\sprintf('A tag is 1 to %d letters, digits, spaces or "-", starting with a letter or a digit.', self::TAG_LENGTH));
        }

        return $tag;
    }

    public function isFree(): bool
    {
        return !$this->price instanceof CoursePrice;
    }

    public function hasTag(string $tag): bool
    {
        return \in_array(mb_strtolower(trim($tag)), $this->tags, true);
    }

    /**
     * The same course at another place of the catalogue.
     *
     * @param int<0, max> $position
     */
    public function at(int $position): self
    {
        return new self($this->catalogue, $this->course, $position, $this->selfEnrollment, $this->price, $this->tags);
    }

    /**
     * The value of the sellable reference a store adds to an order to sell the course from this catalogue:
     * `course:<catalogue>/<course>`, which `SellableReference::fromValue()` of phil/commerce-bundle reads back.
     */
    public function sellable(): string
    {
        return self::SELLABLE_TYPE.':'.$this->catalogue->value.'/'.$this->course->value;
    }
}

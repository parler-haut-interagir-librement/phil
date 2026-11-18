<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\ValueObject\BlockId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;

/**
 * A block of a cmi5 course structure: its title, the block it is nested in — none at the first level —, its place
 * among the children of that parent, blocks and assignable units counted together, and the identifier the course
 * structure gives it, kept as its source. The activities name the innermost block that holds them.
 */
final class Block
{
    public readonly string $title;

    /**
     * @param int<0, max> $position
     *
     * @throws InvalidCourse when the title or the source is not one a block holds
     */
    public function __construct(
        public readonly BlockId $id,
        public readonly CourseId $course,
        string $title,
        public readonly int $position,
        public readonly string $source,
        public readonly ?BlockId $parent = null,
    ) {
        $this->title = Identifiers::title($title);

        if ('' === $source || \strlen($source) > Course::SOURCE_LENGTH) {
            throw InvalidCourse::because(\sprintf('The source of a block is 1 to %d bytes.', Course::SOURCE_LENGTH));
        }
    }
}

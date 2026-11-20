<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;

/**
 * A group of equivalent courses: the same training given in several languages, each course of the group in one
 * language. A learner takes the course of their language, and completing one course of the group completes them all.
 * Its moment of creation is kept in UTC.
 */
final class EquivalenceGroup
{
    public readonly DateTimeImmutable $createdAt;

    public private(set) string $title;

    /**
     * @throws InvalidCourse when the title is not one a group holds
     */
    public function __construct(
        public readonly EquivalenceGroupId $id,
        string $title,
        DateTimeImmutable $createdAt,
    ) {
        $this->title = Identifiers::title($title);
        $this->createdAt = $createdAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @throws InvalidCourse when the title is not one a group holds
     */
    public function rename(string $title): void
    {
        $this->title = Identifiers::title($title);
    }
}

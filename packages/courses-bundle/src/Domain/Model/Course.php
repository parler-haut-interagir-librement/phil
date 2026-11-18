<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;

/**
 * A course: its title and description, where it comes from, and whether its activities must be done in order. The
 * identifier of the package it was imported from — a SCORM manifest's or a cmi5 course's — is kept as its source.
 */
final class Course
{
    public const int SOURCE_LENGTH = 255;

    public const int DESCRIPTION_LENGTH = 10_000;

    public readonly DateTimeImmutable $createdAt;

    public private(set) string $title;

    public private(set) string $description;

    /**
     * @throws InvalidCourse when the title, the description or the source is not one a course holds
     */
    public function __construct(
        public readonly CourseId $id,
        string $title,
        DateTimeImmutable $createdAt,
        string $description = '',
        public readonly CourseOrigin $origin = CourseOrigin::Native,
        public readonly ?string $source = null,
        public private(set) bool $ordered = false,
    ) {
        $this->title = Identifiers::title($title);
        $this->description = $this->description($description);
        $this->createdAt = $createdAt->setTimezone(new DateTimeZone('UTC'));

        if (null !== $source && ('' === $source || \strlen($source) > self::SOURCE_LENGTH)) {
            throw InvalidCourse::because(\sprintf('The source of a course is 1 to %d bytes.', self::SOURCE_LENGTH));
        }
    }

    /**
     * @throws InvalidCourse when the title or the description is not one a course holds
     */
    public function rename(string $title, string $description): void
    {
        $this->title = Identifiers::title($title);
        $this->description = $this->description($description);
    }

    /**
     * Imposes the order of the activities, or lets the learner take them in any order.
     */
    public function imposeOrder(bool $ordered): void
    {
        $this->ordered = $ordered;
    }

    private function description(string $description): string
    {
        $description = trim($description);

        if (mb_strlen($description) > self::DESCRIPTION_LENGTH) {
            throw InvalidCourse::because(\sprintf('A description is %d characters at most.', self::DESCRIPTION_LENGTH));
        }

        return $description;
    }
}

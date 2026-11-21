<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;

/**
 * A learning path: a titled sequence of courses, each a `PathStep` with the steps it waits for. Its moment of creation
 * is kept in UTC.
 */
final class LearningPath
{
    public const int DESCRIPTION_LENGTH = 10_000;

    public readonly DateTimeImmutable $createdAt;

    public private(set) string $title;

    public private(set) string $description;

    /**
     * @throws InvalidCourse when the title or the description is not one a path holds
     */
    public function __construct(
        public readonly LearningPathId $id,
        string $title,
        DateTimeImmutable $createdAt,
        string $description = '',
    ) {
        $this->title = Identifiers::title($title);
        $this->description = $this->description($description);
        $this->createdAt = $createdAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @throws InvalidCourse when the title or the description is not one a path holds
     */
    public function rename(string $title, string $description): void
    {
        $this->title = Identifiers::title($title);
        $this->description = $this->description($description);
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

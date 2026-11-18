<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * A scaled score, as xAPI and SCORM 2004 write it: from -1 to 1, where 1 is every point.
 */
final readonly class Score
{
    private function __construct(
        public float $scaled,
    ) {
    }

    /**
     * @throws InvalidCourse when the value is outside -1 and 1, or not a number
     */
    public static function scaled(float $scaled): self
    {
        if (is_nan($scaled) || $scaled < -1.0 || $scaled > 1.0) {
            throw InvalidCourse::because('A scaled score is between -1 and 1.');
        }

        return new self($scaled);
    }

    /**
     * A score out of a hundred, as SCORM 1.2 writes it.
     *
     * @throws InvalidCourse when the value is outside 0 and 100
     */
    public static function percent(float $percent): self
    {
        if (is_nan($percent) || $percent < 0.0 || $percent > 100.0) {
            throw InvalidCourse::because('A score out of a hundred is between 0 and 100.');
        }

        return new self($percent / 100);
    }

    public function reaches(float $mastery): bool
    {
        return $this->scaled >= $mastery;
    }
}

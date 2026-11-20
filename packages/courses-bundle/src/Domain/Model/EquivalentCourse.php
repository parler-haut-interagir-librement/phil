<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use Phil\CoursesBundle\Domain\Equivalence\LanguageTag;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;

/**
 * A course in a group of equivalent courses, and the language it is given in, kept as a normalized `LanguageTag`.
 */
final readonly class EquivalentCourse
{
    public string $language;

    /**
     * @throws InvalidCourse when the language is not a language tag
     */
    public function __construct(
        public EquivalenceGroupId $group,
        public CourseId $course,
        string $language,
    ) {
        $this->language = LanguageTag::normalize($language);
    }
}

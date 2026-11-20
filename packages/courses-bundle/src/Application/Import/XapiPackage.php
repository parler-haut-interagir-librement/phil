<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

/**
 * What a reader of xAPI packages found: the course's IRI, title and description, the activities it launches in their
 * order, and the notes of the reading.
 */
final readonly class XapiPackage
{
    /**
     * @param list<XapiLaunchable> $activities
     * @param list<string>         $warnings
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public array $activities,
        public array $warnings = [],
    ) {
    }
}

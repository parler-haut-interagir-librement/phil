<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Contract;

use Phil\CertificatesBundle\Domain\Model\Course;

/**
 * Where the courses are kept.
 */
interface CourseRepository
{
    public function save(Course $course): void;

    public function findBySlug(string $slug): ?Course;

    /**
     * The courses that still award certificates, by name.
     *
     * @return list<Course>
     */
    public function open(): array;
}

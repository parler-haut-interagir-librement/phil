<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use Override;
use Phil\CertificatesBundle\Domain\Contract\CourseRepository;
use Phil\CertificatesBundle\Domain\Model\Course;

final class InMemoryCourses implements CourseRepository
{
    /** @var array<string, Course> */
    public array $courses = [];

    public int $saved = 0;

    /** @var list<string> */
    public array $read = [];

    #[Override]
    public function save(Course $course): void
    {
        $this->courses[$course->slug] = $course;
        ++$this->saved;
    }

    #[Override]
    public function findBySlug(string $slug): ?Course
    {
        $this->read[] = $slug;

        return $this->courses[$slug] ?? null;
    }

    #[Override]
    public function open(): array
    {
        return array_values(array_filter($this->courses, static fn (Course $course): bool => !$course->retired));
    }
}

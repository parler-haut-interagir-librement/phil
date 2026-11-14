<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Contract\CourseRepository;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;

/**
 * The courses an application defines, revises, retires and reopens, each known by its slug, with the credits of
 * continuing education their recertification asks for and the moment they end.
 */
final readonly class Courses
{
    public function __construct(
        private CourseRepository $courses,
    ) {
    }

    public function define(string $slug, string $name, string $description, string $narrative, PassCriteria $criteria = new PassCriteria(), ?int $validityMonths = null, ?string $image = null, ?Credits $recertificationCredits = null, ?DateTimeImmutable $endsAt = null): Course
    {
        if ($this->courses->findBySlug($slug) instanceof Course) {
            throw CertificateRefused::because(\sprintf('A course "%s" already exists.', $slug));
        }

        $course = Course::define($slug, $name, $description, $narrative, $criteria, $validityMonths, $image, $recertificationCredits, $endsAt);
        $this->courses->save($course);

        return $course;
    }

    public function revise(string $slug, string $name, string $description, string $narrative, PassCriteria $criteria = new PassCriteria(), ?int $validityMonths = null, ?string $image = null, ?Credits $recertificationCredits = null, ?DateTimeImmutable $endsAt = null): Course
    {
        $course = $this->get($slug);
        $course->revise($name, $description, $narrative, $criteria, $validityMonths, $image, $recertificationCredits, $endsAt);

        $this->courses->save($course);

        return $course;
    }

    public function retire(string $slug): Course
    {
        $course = $this->get($slug);
        $course->retire();

        $this->courses->save($course);

        return $course;
    }

    public function reopen(string $slug): Course
    {
        $course = $this->get($slug);
        $course->reopen();

        $this->courses->save($course);

        return $course;
    }

    public function get(string $slug): Course
    {
        return $this->courses->findBySlug($slug) ?? throw CertificateRefused::because(\sprintf('There is no course "%s".', $slug));
    }
}

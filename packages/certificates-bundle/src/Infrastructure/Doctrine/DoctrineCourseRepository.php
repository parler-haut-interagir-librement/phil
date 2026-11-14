<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CertificatesBundle\Domain\Contract\CourseRepository;
use Phil\CertificatesBundle\Domain\Model\Course;

/**
 * The courses through Doctrine; `save()` flushes.
 */
final readonly class DoctrineCourseRepository implements CourseRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Course $course): void
    {
        $this->entityManager->persist($course);
        $this->entityManager->flush();
    }

    #[Override]
    public function findBySlug(string $slug): ?Course
    {
        return $this->entityManager->getRepository(Course::class)->findOneBy(['slug' => $slug]);
    }

    #[Override]
    public function open(): array
    {
        return $this->entityManager->getRepository(Course::class)->findBy(['retired' => false], ['name' => 'ASC', 'slug' => 'ASC']);
    }
}

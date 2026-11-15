<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;

/**
 * The certificates through Doctrine; `save()` flushes, and the unique indexes refuse a second award that took the same
 * status index, or the same completion of a course, at the same moment.
 *
 * Doctrine closes an entity manager whose flush failed. With the registry, the refused award resets the manager it was
 * stored with before `StatusIndexTaken` is thrown, so that the award starts again on a manager that is open; without
 * it, the exception of Doctrine is left as it is.
 */
final readonly class DoctrineCertificateRepository implements CertificateRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?ManagerRegistry $registry = null,
        private ?string $managerName = null,
    ) {
    }

    #[Override]
    public function save(Certificate $certificate): void
    {
        $this->entityManager->persist($certificate);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            if (!$this->registry instanceof ManagerRegistry) {
                throw $uniqueConstraintViolationException;
            }

            $this->registry->resetManager($this->managerName);

            throw StatusIndexTaken::at($certificate->statusIndex, $uniqueConstraintViolationException);
        }
    }

    #[Override]
    public function findByCode(string $code): ?Certificate
    {
        return $this->entityManager->getRepository(Certificate::class)->findOneBy(['codeValue' => $code]);
    }

    #[Override]
    public function nextStatusIndex(): int
    {
        return (int) $this->entityManager->createQuery('SELECT COALESCE(MAX(c.statusIndex) + 1, 0) FROM '.Certificate::class.' c')->getSingleScalarResult();
    }

    #[Override]
    public function revokedIndexes(int $from, int $to): array
    {
        /** @var list<int> */
        return $this->entityManager->createQuery('SELECT c.statusIndex FROM '.Certificate::class.' c WHERE c.revokedAt IS NOT NULL AND c.statusIndex >= :from AND c.statusIndex < :to ORDER BY c.statusIndex')
            ->setParameters(['from' => $from, 'to' => $to])
            ->getSingleColumnResult();
    }

    #[Override]
    public function listed(Course $course, int $limit, int $offset = 0): array
    {
        return $this->entityManager->getRepository(Certificate::class)->findBy(['course' => $course, 'listed' => true], ['awardedAt' => 'DESC', 'id' => 'DESC'], $limit, $offset);
    }

    #[Override]
    public function countListed(Course $course): int
    {
        return $this->entityManager->getRepository(Certificate::class)->count(['course' => $course, 'listed' => true]);
    }

    #[Override]
    public function findRenewalOf(Certificate $certificate): ?Certificate
    {
        return $this->entityManager->getRepository(Certificate::class)->findOneBy(['renews' => $certificate]);
    }

    #[Override]
    public function findByCompletion(Course $course, string $completion): ?Certificate
    {
        return $this->entityManager->getRepository(Certificate::class)->findOneBy(['course' => $course, 'completion' => $completion]);
    }

    #[Override]
    public function expiring(DateTimeImmutable $after, DateTimeImmutable $until, int $limit, int $offset = 0): array
    {
        /** @var list<Certificate> */
        return $this->entityManager->createQuery('SELECT c FROM '.Certificate::class.' c WHERE c.revokedAt IS NULL AND c.validUntil > :after AND c.validUntil <= :until AND NOT EXISTS (SELECT r.id FROM '.Certificate::class.' r WHERE r.renews = c) ORDER BY c.validUntil ASC, c.id ASC')
            ->setParameter('after', $after, 'datetime_immutable')
            ->setParameter('until', $until, 'datetime_immutable')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getResult();
    }
}

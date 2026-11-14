<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\OnboardingBundle\Domain\Exception\ProgressNotSaved;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;

/**
 * The progress in the table `phil_onboarding_progress`, through the entity manager, keyed by user and flow. A new
 * progress that another request kept first breaks the primary key: the entity manager, closed by the failure, is reset
 * by the kernel at the end of the request. The progress of an erased user is removed by one query, and the entities the
 * entity manager holds are detached.
 */
final readonly class DoctrineProgressRepository implements ProgressRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $user, string $flow): ?Progress
    {
        return $this->entityManager->find(Progress::class, ['user' => $user, 'flow' => $flow]);
    }

    #[Override]
    public function ofUser(string $user): array
    {
        return $this->entityManager->getRepository(Progress::class)->findBy(['user' => $user]);
    }

    #[Override]
    public function save(Progress $progress): void
    {
        try {
            $this->entityManager->persist($progress);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw ProgressNotSaved::of($progress->flow, $uniqueConstraintViolationException);
        }
    }

    #[Override]
    public function removeAllOf(string $user): void
    {
        $this->entityManager->createQuery('DELETE FROM '.Progress::class.' p WHERE p.user = :user')->setParameter('user', $user)->execute();

        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap()[Progress::class] ?? [] as $progress) {
            if ($progress instanceof Progress && $progress->user === $user) {
                $this->entityManager->detach($progress);
            }
        }
    }
}

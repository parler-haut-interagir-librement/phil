<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Infrastructure\Doctrine;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\WaitlistBundle\Domain\Contract\WaitlistRepository;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Throwable;

/**
 * The waiting lists with Doctrine. `exclusively()` runs its work in a transaction that first updates the list's row,
 * then reads the list again: the database holds the other requests on the list until it commits, and the work sees the
 * places another request may have changed. A refusal rolls the transaction back and leaves the entity manager open.
 */
final readonly class DoctrineWaitlistRepository implements WaitlistRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Waitlist
    {
        return $this->entityManager->find(Waitlist::class, $id);
    }

    #[Override]
    public function ofSubject(string $subjectType, string $subjectId): ?Waitlist
    {
        return $this->entityManager->getRepository(Waitlist::class)->findOneBy(['subjectType' => $subjectType, 'subjectId' => $subjectId]);
    }

    #[Override]
    public function save(Waitlist $waitlist): void
    {
        $this->entityManager->persist($waitlist);
        $this->entityManager->flush();
    }

    #[Override]
    public function exclusively(Waitlist $waitlist, Closure $work): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement('UPDATE '.Waitlist::TABLE.' SET claims = claims + 1 WHERE id = ?', [$waitlist->id]);
            $this->entityManager->refresh($waitlist);
            $result = $work();
        } catch (Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $connection->commit();

        return $result;
    }
}

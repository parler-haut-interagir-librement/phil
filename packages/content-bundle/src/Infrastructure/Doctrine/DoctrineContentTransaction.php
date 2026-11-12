<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Throwable;

/**
 * `ContentTransaction` on the Doctrine ORM.
 *
 * A failure rolls the transaction back and clears the unit of work, whose pending changes no longer match the
 * database. Inside a transaction already open, DBAL nests the new one in a savepoint.
 *
 * - **The entity manager stays open only when the failure comes before a flush**: a refusal of the domain, a
 *   slug taken, a transition refused. A failure of the database during a flush — a unique index refusing a
 *   concurrent publication or the same slug taken twice at once — closes it, as the unit of work of
 *   `doctrine/orm` closes it whenever its commit fails, here as in `wrapInTransaction()`. A console command
 *   or a migration that catches such an error holds a closed entity manager, and resets it through
 *   `ManagerRegistry::resetManager()` before writing again.
 * - **Clearing reaches the caller.** Nested in a transaction of the caller, a failure clears the whole unit of
 *   work, the caller's entities with it: they are detached, and loaded again before they are written.
 */
final readonly class DoctrineContentTransaction implements ContentTransaction
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function run(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $operation();
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (Throwable $throwable) {
            $connection->rollBack();
            $this->entityManager->clear();

            throw $throwable;
        }
    }

    #[Override]
    public function flush(): void
    {
        $this->entityManager->flush();
    }
}

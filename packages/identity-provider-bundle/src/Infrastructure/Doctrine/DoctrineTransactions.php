<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\Transactions;

/**
 * The work run in one transaction of the entity manager the stores share: the flushes of each store join it, and an
 * exception rolls them all back.
 */
final readonly class DoctrineTransactions implements Transactions
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    #[Override]
    public function run(Closure $work): mixed
    {
        return $this->entityManager->getConnection()->transactional(static fn (): mixed => $work());
    }
}

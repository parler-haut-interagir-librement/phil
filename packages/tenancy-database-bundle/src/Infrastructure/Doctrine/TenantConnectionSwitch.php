<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantSwitchInTransaction;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Moves the managed connection to the database of the tenant being entered, and back to the shared one when the unit
 * of work leaves it.
 *
 * - **Placement read on each entry.** A tenant moved to another database is found there by the next request, message
 *   or command, in every process.
 * - **No switch inside a transaction.** A transaction belongs to the database it started on: entering a tenant of
 *   another database while one is open throws {@see TenantSwitchInTransaction}, and the connection stays where it is.
 * - **A clean identity map.** Each change of tenant clears the entity managers of the connection, so that an entity
 *   loaded for one tenant is never handed to another by `find()`, which reads the identity map before the database.
 * - **Fail closed in a worker.** Leaving the tenant, and `kernel.reset`, return the connection to the shared database,
 *   where the Doctrine filter shows nothing without a tenant. A transaction still open then is abandoned with its
 *   connection, which the server rolls back, and a warning is logged.
 *
 * The log lines name the tenant and the database, never a host, a user or a password.
 */
final class TenantConnectionSwitch implements TenantActivation, ResetInterface
{
    private ?TenantId $tenant = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantDatabaseRouter $router,
        private readonly TenantPlacement $placement,
        private readonly ?ManagerRegistry $doctrine = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @throws TenantSwitchInTransaction when a transaction is open and the tenant lives in another database
     */
    #[Override]
    public function activate(TenantId $tenant): void
    {
        $placement = $this->placement->placementOf($tenant);
        $current = $this->router->current();
        $moved = !$current->equals($placement);

        if ($moved) {
            if ($this->connection->isTransactionActive()) {
                throw TenantSwitchInTransaction::between($current, $placement, $tenant);
            }

            $this->move($placement);
            $this->logger?->debug('The connection follows the tenant "{tenant}" to the database "{database}".', ['tenant' => $tenant->value, 'database' => $placement->toString()]);
        }

        if ($moved || !$this->tenant instanceof TenantId || !$this->tenant->equals($tenant)) {
            $this->clearManagers();
        }

        $this->tenant = $tenant;
    }

    #[Override]
    public function deactivate(): void
    {
        $shared = Placement::shared();
        $moved = !$this->router->current()->equals($shared);

        if ($moved) {
            if ($this->connection->isTransactionActive()) {
                $this->logger?->warning('A transaction was still open in the database "{database}" when the unit of work left the tenant "{tenant}": it is abandoned with its connection.', ['tenant' => $this->tenant?->value, 'database' => $this->router->current()->toString()]);
            }

            $this->move($shared);
        }

        if ($moved || $this->tenant instanceof TenantId) {
            $this->clearManagers();
        }

        $this->tenant = null;
    }

    #[Override]
    public function reset(): void
    {
        $this->deactivate();
    }

    private function move(Placement $placement): void
    {
        $this->connection->close();
        $this->router->route($placement);
    }

    private function clearManagers(): void
    {
        foreach ($this->doctrine?->getManagers() ?? [] as $manager) {
            if ($manager instanceof EntityManagerInterface && $manager->getConnection() === $this->connection) {
                $manager->clear();
            }
        }
    }
}

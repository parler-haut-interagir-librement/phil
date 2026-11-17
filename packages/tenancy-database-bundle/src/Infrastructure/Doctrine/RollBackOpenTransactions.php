<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;

/**
 * Ends the unit of work of a request, a command or a message on clean connections: a transaction still open on the
 * connection that follows the tenant, or on the bundle's own connection to the shared database, is rolled back, then
 * the tenant is left, which returns the connection to the shared database.
 *
 * Listens to `kernel.terminate`, after the application's own listeners, to `console.terminate`, before the tenant of
 * the command is left, and to the `WorkerMessageHandledEvent` and `WorkerMessageFailedEvent` of a Messenger worker. In
 * a worker, the next request, command or message would otherwise find the transaction, and the locks it holds, of the
 * previous one: the kernel's reset only comes with the next request.
 *
 * - **Every level.** A transaction is rolled back as many times as it is nested, savepoints first.
 * - **A connection that cannot roll back is closed.** The server rolls back what a lost or broken connection had open;
 *   the next statement opens a new one.
 * - **Nothing to do, nothing done.** A connection never opened in this process is not created, and one with no
 *   transaction, or closed, is left as it is.
 * - **The tenant only.** The warning names the tenant whose unit of work left the transaction open — the active one,
 *   or the one a message was stamped with — never a statement, a row, a host or an account.
 */
final readonly class RollBackOpenTransactions
{
    /**
     * @param iterable<Connection> $connections the connections that are already open in this process, the others skipped
     */
    public function __construct(
        private iterable $connections,
        private TenantContext $context,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param object $event the event that ends the unit of work, whatever it is
     */
    public function endUnitOfWork(object $event): void
    {
        foreach ($this->connections as $connection) {
            $this->rollBack($connection, $event);
        }

        $this->context->leave();
    }

    private function rollBack(Connection $connection, object $event): void
    {
        $levels = $connection->getTransactionNestingLevel();

        if (0 === $levels) {
            return;
        }

        $this->logger?->warning('A transaction was still open at the end of the unit of work of the tenant "{tenant}": it is rolled back.', ['tenant' => $this->tenant($event)]);

        try {
            // Bounded by the levels open now: a connection without autocommit begins a new transaction after each one.
            for (; $levels > 0; --$levels) {
                $connection->rollBack();
            }
        } catch (DbalException) {
            $connection->close();
        }
    }

    private function tenant(object $event): ?string
    {
        $tenant = $this->context->current()?->value;

        if (null === $tenant && $event instanceof AbstractWorkerMessageEvent) {
            $tenant = $event->getEnvelope()->last(TenantStamp::class)?->tenant;
        }

        return $tenant;
    }
}

<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Invoice;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the invoices of the host's tenant, or of two tenants one after the other in one request, with the entity
 * manager and with native SQL; tells what a request of a worker finds as it starts; or fails in the middle of a
 * transaction.
 */
final readonly class TenantsController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantContext $context,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function invoices(): JsonResponse
    {
        return new JsonResponse($this->read());
    }

    public function both(): JsonResponse
    {
        $read = [];

        foreach (['retail', 'wholesale', 'retail'] as $i => $tenant) {
            $read[$i.'-'.$tenant] = $this->context->within(TenantId::fromString($tenant), $this->read(...));
        }

        return new JsonResponse($read);
    }

    /**
     * What a request finds as it starts, before it reads anything: the entities and the transactions the request
     * before left, the database the connection is in, the first invoice, and the owner of the tenant's cache, which
     * the first request of each tenant records under the key of the run.
     */
    public function worker(Request $request): JsonResponse
    {
        $identityMap = $this->entityManager->getUnitOfWork()->size();
        $connection = $this->entityManager->getConnection();
        $transactions = $connection->getTransactionNestingLevel();
        $database = $connection->fetchOne($connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'SELECT current_database()' : 'SELECT DATABASE()');
        $owner = $this->cache->getItem('owner.'.$request->query->getAlnum('run'));
        $cached = $owner->isHit() ? $owner->get() : null;

        if (!$owner->isHit()) {
            $this->cache->save($owner->set($this->context->require()->value));
        }

        return new JsonResponse([
            'identity_map' => $identityMap,
            'transactions' => $transactions,
            'database' => $database,
            'first' => $this->entityManager->find(Invoice::class, 1)?->label,
            'cached' => $cached,
        ]);
    }

    /**
     * Writes a currency in a transaction, then fails before the commit.
     */
    public function fail(): never
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        $connection->insert('currency', ['code' => 'EUR']);

        throw new RuntimeException('Failed in the middle of a transaction.');
    }

    /**
     * @return array{entities: list<string>, native: list<string>, first: ?string}
     */
    private function read(): array
    {
        $entities = array_map(static fn (Invoice $invoice): string => $invoice->label, $this->entityManager->getRepository(Invoice::class)->findBy([], ['label' => 'ASC']));
        /** @var list<string> $native */
        $native = $this->entityManager->getConnection()->fetchFirstColumn('SELECT label FROM invoice ORDER BY label');
        $first = $this->entityManager->find(Invoice::class, 1);

        return ['entities' => $entities, 'native' => $native, 'first' => $first?->label];
    }
}

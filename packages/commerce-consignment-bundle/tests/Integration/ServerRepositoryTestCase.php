<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure\DoctrineRepositoryTestCase;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;

/**
 * The Doctrine repository on a real server read from the environment, its tables dropped and created from the mapping.
 */
abstract class ServerRepositoryTestCase extends DoctrineRepositoryTestCase
{
    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function connection(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }

    public function testKeysCompareByteByByte(): void
    {
        $repository = $this->repository();
        $ledger = new Ledger($repository);
        $ledger->receive(Fixtures::consigned(2, 'ann', ConsignmentTerms::commission(1), 'CS-1'), Fixtures::at(9));
        $this->flushAndClear();

        $repository = $this->repository();
        self::assertSame([], $repository->layersAt(strtolower(Fixtures::BOOK), Fixtures::SHELF));
        self::assertSame([], $repository->layersAt(Fixtures::BOOK, strtoupper(Fixtures::SHELF)));
        self::assertNull($repository->latestLayer(Fixtures::BOOK, 'Ann'));
        self::assertSame([], $repository->entriesOf('cs-1', Fixtures::BOOK));
        self::assertCount(1, $repository->entriesOf('CS-1', Fixtures::BOOK));
    }
}

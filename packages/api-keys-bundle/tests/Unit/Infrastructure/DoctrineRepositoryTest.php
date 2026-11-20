<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Infrastructure\Doctrine\DoctrineApiTokenRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The store on SQLite in memory, its table created from the mapping, each row read back with the entity manager
 * cleared.
 */
#[CoversClass(DoctrineApiTokenRepository::class)]
#[CoversClass(ApiToken::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTable(): void
    {
        $this->repository();
        $connection = $this->entityManager?->getConnection();
        self::assertNotNull($connection);

        self::assertSame([
            ['id', 'CHAR(12)', 1, 1], ['owner_type', 'VARCHAR(32)', 1, 0], ['owner', 'VARCHAR(180)', 1, 0], ['name', 'VARCHAR(100)', 1, 0], ['scopes', 'CLOB', 1, 0], ['secret_hash', 'VARCHAR(100)', 1, 0],
            ['created_at', 'DATETIME', 1, 0], ['expires_at', 'DATETIME', 1, 0], ['last_used_at', 'DATETIME', 0, 0], ['last_used_address', 'VARCHAR(100)', 0, 0], ['revoked_at', 'DATETIME', 0, 0],
        ], array_map(static fn (array $column): array => [$column['name'], $column['type'], filter_var($column['notnull'], \FILTER_VALIDATE_INT), filter_var($column['pk'], \FILTER_VALIDATE_INT)], $connection->fetchAllAssociative('PRAGMA table_info('.ApiToken::TABLE.')')));
        self::assertSame(['phil_api_token_expires_at', 'phil_api_token_owner'], array_values(array_filter(array_column($connection->fetchAllAssociative('PRAGMA index_list('.ApiToken::TABLE.')'), 'name'), static fn (mixed $name): bool => \is_string($name) && str_starts_with($name, 'phil_'))));
        self::assertSame(['owner_type', 'owner'], array_column($connection->fetchAllAssociative('PRAGMA index_info(phil_api_token_owner)'), 'name'));
        self::assertSame("'user'", $connection->fetchOne("SELECT dflt_value FROM pragma_table_info('".ApiToken::TABLE."') WHERE name = 'owner_type'"));
    }

    public function testAUseFlushesNothingElse(): void
    {
        $repository = $this->repository();
        $token = $this->token('AAAAAAAAAAAA', 'alice', '2026-11-08 10:00:00');
        $repository->save($token);
        $token->revoke(new DateTimeImmutable('2026-11-09 10:00:00'));
        $token->recordUse(new DateTimeImmutable('2026-11-08 11:00:00'), null);

        $repository->recordUse($token);
        $this->clear();

        $found = $repository->get('AAAAAAAAAAAA');
        self::assertInstanceOf(ApiToken::class, $found);
        self::assertFalse($found->isRevoked());
        self::assertNotNull($found->lastUsedAt());
    }

    public function testTheInstantsAreKeptInUtc(): void
    {
        $repository = $this->repository();
        date_default_timezone_set('America/Toronto');
        $token = $this->token('AAAAAAAAAAAA', 'alice', '2026-07-01 08:00:00', '2026-12-01 08:00:00');
        $repository->save($token);
        $token->recordUse(new DateTimeImmutable('2026-07-01 09:00:00'), null);
        $repository->recordUse($token);
        $token->revoke(new DateTimeImmutable('2026-07-01 10:00:00'));
        $repository->save($token);

        self::assertSame(['2026-07-01 12:00:00', '2026-12-01 13:00:00', '2026-07-01 13:00:00', '2026-07-01 14:00:00'], $this->entityManager?->getConnection()->fetchNumeric('SELECT created_at, expires_at, last_used_at, revoked_at FROM '.ApiToken::TABLE));
        $this->clear();
        self::assertSame('UTC', $repository->get('AAAAAAAAAAAA')?->expiresAt()->getTimezone()->getName());
    }

    #[Override]
    protected function repository(): ApiTokenRepository
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        return new DoctrineApiTokenRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }

    #[Override]
    protected function assertUnwritten(?ApiToken $token): void
    {
        self::assertNull($token?->lastUsedAt());
    }
}

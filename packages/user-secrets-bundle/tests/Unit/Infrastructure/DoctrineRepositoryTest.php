<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The store on SQLite in memory, its table created from the mapping, each row read back with the entity manager
 * cleared.
 */
#[CoversClass(DoctrineStoredSecretRepository::class)]
#[CoversClass(StoredSecret::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTableIsKeyedByOwnerAndName(): void
    {
        $this->repository();
        $schema = $this->entityManager?->getConnection()->createSchemaManager()->introspectSchema();

        self::assertNotNull($schema);
        $table = $schema->getTable(StoredSecret::TABLE);
        self::assertSame(['owner', 'name'], $table->getPrimaryKey()?->getColumns());
        self::assertSame([
            'owner' => [255, true], 'name' => [64, true], 'service' => [64, true], 'kind' => [16, true], 'key_id' => [32, true],
            'ciphertext' => [null, true], 'format' => [null, false], 'expires_at' => [null, false], 'created_at' => [null, true], 'updated_at' => [null, true],
            'last_used_at' => [null, false], 'revoked_at' => [null, false],
        ], array_merge(...array_map(static fn (Column $column): array => [$column->getName() => [$column->getLength(), $column->getNotnull()]], $table->getColumns())));
    }

    public function testAFreshReadSeesWhatAnotherProcessWroteToAnEntityTheManagerHolds(): void
    {
        $repository = $this->repository();
        $repository->save($this->row('k1', base64_encode("\3first")));

        $held = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertNotNull($held);

        $this->entityManager?->getConnection()->executeStatement('UPDATE '.StoredSecret::TABLE.' SET key_id = ?', ['k9']);

        self::assertSame('k1', $held->sealed()->keyId, 'the manager still holds the old state');
        self::assertSame($held, $repository->fresh(Owner::fromString('member-1'), SecretName::fromString('openai')));
        self::assertSame('k9', $held->sealed()->keyId);
    }

    public function testAReplacementThatLostTheRaceLeavesTheEntityWhatTheOtherProcessWrote(): void
    {
        $repository = $this->repository();
        $repository->save($this->row('k1', base64_encode("\3first")));

        $held = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertNotNull($held);
        $previous = $held->sealed();

        $this->entityManager?->getConnection()->executeStatement('UPDATE '.StoredSecret::TABLE.' SET ciphertext = ?, key_id = ?', [base64_encode("\3other"), 'k8']);
        $held->reseal(ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k2', base64_encode("\3mine")), null, new DateTimeImmutable('@2000'));

        self::assertFalse($repository->replace($held, $previous));
        self::assertSame(['k8', base64_encode("\3other")], [$held->sealed()->keyId, $held->sealed()->ciphertext]);
    }

    public function testAValueThatIsNotBase64IsKeptWithNoFormat(): void
    {
        $repository = $this->repository();
        $repository->save($this->row('k1', base64_encode("\3first")));

        $held = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertNotNull($held);
        $previous = $held->sealed();
        $held->reseal(ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', '%%%%'), null, new DateTimeImmutable('@2000'));

        self::assertTrue($repository->replace($held, $previous));
        self::assertNull($this->entityManager?->getConnection()->fetchOne('SELECT format FROM '.StoredSecret::TABLE));
    }

    public function testTheInstantsAreKeptInUtc(): void
    {
        $repository = $this->repository();
        date_default_timezone_set('America/Toronto');
        $secret = StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\3first")), new DateTimeImmutable('2026-07-01 09:00:00'), new DateTimeImmutable('2026-07-01 08:00:00'));
        $repository->save($secret);

        $previous = $secret->sealed();
        $secret->reseal(ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\3second")), new DateTimeImmutable('2026-12-01 09:00:00'), new DateTimeImmutable('2026-12-01 08:00:00'));
        self::assertTrue($repository->replace($secret, $previous));

        self::assertSame(['2026-12-01 14:00:00', '2026-07-01 12:00:00', '2026-12-01 13:00:00'], $this->entityManager?->getConnection()->fetchNumeric('SELECT expires_at, created_at, updated_at FROM '.StoredSecret::TABLE));
        self::assertSame('UTC', $repository->fresh(Owner::fromString('member-1'), SecretName::fromString('openai'))?->createdAt()->getTimezone()->getName());
    }

    private function row(string $keyId, string $ciphertext): StoredSecret
    {
        return StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret($keyId, $ciphertext), null, new DateTimeImmutable('@1000'));
    }

    #[Override]
    protected function repository(): StoredSecretRepository
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        return new DoctrineStoredSecretRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}

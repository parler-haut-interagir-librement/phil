<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository;
use Phil\UserSecretsBundle\Tests\Unit\Infrastructure\RepositoryTestCase;

/**
 * The Doctrine store on a real server — the order of the key rotation, the conditional `UPDATE` of the compare-and-swap
 * on a `TEXT` column —: the table created from the mapping, each row read back with the entity manager cleared.
 */
abstract class ServerRepositoryTestCase extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    /**
     * The URL of the server, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function repository(): StoredSecretRepository
    {
        $url = $this->url();

        if (null === $url || '' === $url) {
            self::markTestSkipped('Start the servers with `phil-dev services up` and run `phil-dev services test` to run this test.');
        }

        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->entityManager = new EntityManager($connection, $configuration);
        $schema = new SchemaTool($this->entityManager);
        $metadata = [$this->entityManager->getClassMetadata(StoredSecret::class)];
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        return new DoctrineStoredSecretRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}

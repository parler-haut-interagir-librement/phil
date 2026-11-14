<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\MentionsBundle\Infrastructure\Doctrine\DoctrineMentionRepository;
use Phil\MentionsBundle\Tests\Unit\Infrastructure\RepositoryTestCase;

/**
 * The Doctrine store on a real server, where the upsert is that server's own statement: the table created from the
 * mapping, each mention read back from the database, the entity manager cleared.
 */
abstract class ServerRepositoryTestCase extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    /**
     * The URL of the server, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function store(): MentionRepository
    {
        $url = $this->url();

        if (null === $url || '' === $url) {
            self::markTestSkipped('Start the servers with `phil-dev services up` and run `phil-dev services test` to run this test.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->entityManager = new EntityManager($connection, $config);
        $schema = new SchemaTool($this->entityManager);
        $metadata = [$this->entityManager->getClassMetadata(RecordedMention::class)];
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        return new DoctrineMentionRepository($this->entityManager);
    }

    #[Override]
    protected function reread(MentionRepository $store, string $subject): array
    {
        $this->entityManager?->clear();

        return parent::reread($store, $subject);
    }
}

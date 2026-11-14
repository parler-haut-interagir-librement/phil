<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\OnboardingBundle\Domain\Exception\ProgressNotSaved;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Doctrine\DoctrineProgressRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The store on SQLite in memory, its table created from the mapping as an application's schema tools create it, and
 * each progress read back from the database, the entity manager cleared.
 */
#[CoversClass(DoctrineProgressRepository::class)]
#[CoversClass(ProgressNotSaved::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    #[Override]
    protected function store(): ProgressRepository
    {
        $this->entityManager = self::entityManager();

        return new DoctrineProgressRepository($this->entityManager);
    }

    #[Override]
    protected function reread(ProgressRepository $store, string $user, string $flow): ?Progress
    {
        $this->entityManager?->clear();

        return parent::reread($store, $user, $flow);
    }

    public function testAProgressKeptFirstByAnotherRequestIsNotSaved(): void
    {
        $entityManager = self::entityManager();
        $entityManager->getConnection()->insert(Progress::TABLE, [
            'user_identifier' => 'ada',
            'flow' => 'author',
            'steps' => '{}',
            'started_at' => '2026-11-08 10:00:00',
            'updated_at' => '2026-11-08 10:00:00',
        ]);

        try {
            new DoctrineProgressRepository($entityManager)->save(Progress::start('ada', 'author', new DateTimeImmutable()));
            self::fail('The progress was saved twice.');
        } catch (ProgressNotSaved $progressNotSaved) {
            self::assertSame('The progress of the flow "author" was saved by another request at the same time.', $progressNotSaved->getMessage());
            self::assertNotNull($progressNotSaved->getPrevious());
        }
    }

    public function testTheEntitiesOfAnErasedUserAreDetached(): void
    {
        $entityManager = self::entityManager();
        $store = new DoctrineProgressRepository($entityManager);
        $erased = Progress::start('ada', 'author', new DateTimeImmutable());
        $kept = Progress::start('bob', 'author', new DateTimeImmutable());
        $store->save($erased);
        $store->save($kept);

        $store->removeAllOf('ada');

        self::assertSame([false, true], [$entityManager->contains($erased), $entityManager->contains($kept)]);
        self::assertSame(['bob'], $entityManager->getConnection()->fetchFirstColumn('SELECT user_identifier FROM '.Progress::TABLE));
    }

    public static function entityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Progress::class)]);

        return $entityManager;
    }
}

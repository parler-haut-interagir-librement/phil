<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Outbox\Relay;
use Phil\Outbox\RelayReport;
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Infrastructure\Outbox\IndexEntity;
use Phil\SearchBundle\Infrastructure\Outbox\OutboxIndexer;
use Phil\SearchBundle\Infrastructure\Outbox\OutboxIndexingListener;
use Phil\SearchBundle\Infrastructure\Outbox\RemoveDocument;
use Phil\SearchBundle\Infrastructure\Outbox\SearchOutboxCodec;
use Phil\SearchBundle\Tests\Functional\App\Entity\Note;
use Phil\SearchBundle\Tests\Functional\App\Entity\Page;
use Phil\SearchBundle\Tests\Functional\App\OutboxKernel;
use Phil\SearchBundle\Tests\Functional\App\SwitchableIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The entities of a flush go to the index through the outbox: recorded in the flush's transaction, indexed once relayed,
 * caught up after the engine was down, and never indexed when the transaction is rolled back.
 */
#[CoversClass(OutboxIndexingListener::class)]
#[CoversClass(SearchOutboxCodec::class)]
#[CoversClass(OutboxIndexer::class)]
#[CoversClass(IndexEntity::class)]
#[CoversClass(RemoveDocument::class)]
final class OutboxIndexingTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return OutboxKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->entityManager();

        // The tables of the entities, the index and the outbox, as a migration would create them.
        foreach (new SchemaTool($entityManager)->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testAFlushIsIndexedOnceTheOutboxIsRelayed(): void
    {
        $entityManager = $this->entityManager();
        $about = new Page('about', 'À propos de nous');
        $entityManager->persist($about);
        $entityManager->persist(new Page('games', 'Jeux de rôle'));
        $entityManager->flush();

        self::assertSame([], $this->found('pages', 'propos'), 'nothing is indexed before the relay');
        self::assertSame(2, $this->pending());
        self::assertSame(2, $this->relay()->delivered);
        self::assertSame(['about'], $this->found('pages', 'propos'));
        self::assertSame(['games'], $this->found('pages', 'rôle'));

        $about->title = 'Qui sommes-nous';
        $entityManager->flush();
        $entityManager->remove($entityManager->find(Page::class, 'games') ?? throw new RuntimeException('No page.'));
        $entityManager->flush();
        self::assertSame(2, $this->relay()->delivered);

        self::assertSame([], $this->found('pages', 'propos'));
        self::assertSame(['about'], $this->found('pages', 'sommes'));
        self::assertSame([], $this->found('pages', 'rôle'), 'the deleted page left the index');
    }

    public function testAnEntityWithAGeneratedIdentifierIsIndexedThenRemoved(): void
    {
        $entityManager = $this->entityManager();
        $note = new Note('Une soirée de jeux');
        $entityManager->persist($note);
        $entityManager->flush();

        $id = $note->id;
        self::assertNotNull($id);

        $entityManager->remove($note);
        $entityManager->flush();
        self::assertNull($note->id);

        // The records of one entity keep their order, one per batch: the indexing first, the note then gone, and the
        // removal after it.
        self::assertSame(2, $this->pending());
        self::assertSame(1, $this->relay()->delivered);
        self::assertSame(1, $this->pending());
        self::assertSame(1, $this->relay()->delivered);
        self::assertSame(0, $this->pending());
        self::assertSame([], $this->found('notes', 'soirée'));
    }

    public function testAFlushRolledBackIndexesNothing(): void
    {
        $entityManager = $this->entityManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        $entityManager->persist(new Page('ghost', 'Un fantôme'));
        $entityManager->flush();

        $connection->rollBack();
        $entityManager->clear();

        self::assertSame(0, $this->pending(), 'the record left with the row');
        self::assertTrue($this->relay()->isEmpty());
        self::assertSame([], $this->found('pages', 'fantôme'));
    }

    public function testTheContentIsSavedWhileTheEngineIsDownAndTheIndexCatchesUp(): void
    {
        $entityManager = $this->entityManager();
        $index = self::getContainer()->get(SwitchableIndex::class);
        self::assertInstanceOf(SwitchableIndex::class, $index);
        $index->down = true;

        $entityManager->persist(new Page('rules', 'Les règles du jeu'));
        $entityManager->flush();
        $entityManager->clear();
        self::assertNotNull($entityManager->find(Page::class, 'rules'), 'the page is saved');

        self::assertSame(1, $this->relay()->retried, 'the engine down, the record waits');
        self::assertSame(1, $this->pending());

        $index->down = false;
        $clock = self::getContainer()->get('test.clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+2 minutes');

        self::assertSame(1, $this->relay()->delivered);
        self::assertSame(['rules'], $this->found('pages', 'règles'));
    }

    private function relay(): RelayReport
    {
        $relay = self::getContainer()->get(Relay::class);
        self::assertInstanceOf(Relay::class, $relay);

        return $relay->relay();
    }

    /**
     * @return list<string>
     */
    private function found(string $index, string $text): array
    {
        $search = self::getContainer()->get(Search::class);
        self::assertInstanceOf(Search::class, $search);

        return $search->search($index, SearchQuery::of($text))->ids;
    }

    private function pending(): int
    {
        $count = $this->entityManager()->getConnection()->fetchOne("SELECT COUNT(*) FROM phil_outbox WHERE state = 'pending'");
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DoctrineBehaviours\SoftDeleteListener;
use Phil\DoctrineBehaviours\SoftDeletes;
use Phil\DoctrineBehavioursBundle\Infrastructure\DependencyInjection\BehaviourServices;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Page;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\RecordingIndex;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\SearchTestKernel;
use Phil\SearchBundle\Infrastructure\Doctrine\IndexOnFlushListener;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The soft delete listens to `onFlush` after the listeners of the default priority: the search index of
 * phil/search-bundle still sees a removal as a deletion and drops the page, while its row stays, soft-deleted.
 */
#[CoversClass(BehaviourServices::class)]
final class SoftDeleteSearchTest extends KernelTestCase
{
    private EntityManagerInterface $manager;

    #[Override]
    protected static function getKernelClass(): string
    {
        return SearchTestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $this->manager = $manager;
    }

    public function testTheIndexDropsASoftDeletedPage(): void
    {
        $index = self::getContainer()->get(RecordingIndex::class);
        self::assertInstanceOf(RecordingIndex::class, $index);
        $page = new Page('home');
        $this->manager->persist($page);
        $this->manager->flush();
        self::assertArrayHasKey('home', $index->documents['pages'] ?? []);

        $this->manager->remove($page);
        $this->manager->flush();

        self::assertSame([['pages', ['home']]], $index->removed);
        self::assertSame([], $index->documents['pages']);
        self::assertTrue($page->isDeleted());
        self::assertSame('home', $this->manager->getConnection()->fetchOne("SELECT id FROM page WHERE id = 'home'"), 'the row stays');
    }

    public function testTheIndexListensBeforeTheSoftDelete(): void
    {
        $listeners = array_map(get_class(...), array_values($this->manager->getEventManager()->getListeners('onFlush')));

        self::assertLessThan(array_search(SoftDeleteListener::class, $listeners, true), array_search(IndexOnFlushListener::class, $listeners, true));
        self::assertSame(SoftDeleteListener::class, array_last($listeners));
    }

    public function testAPurgedPageLeavesTheIndexAndTheTable(): void
    {
        $index = self::getContainer()->get(RecordingIndex::class);
        self::assertInstanceOf(RecordingIndex::class, $index);
        $page = new Page('home');
        $this->manager->persist($page);
        $this->manager->flush();

        $purge = self::getContainer()->get(SoftDeletes::class);
        self::assertInstanceOf(SoftDeletes::class, $purge);

        $purge->purge($page);

        self::assertSame([['pages', ['home']]], $index->removed);
        self::assertFalse($this->manager->getConnection()->fetchOne("SELECT id FROM page WHERE id = 'home'"));
    }
}

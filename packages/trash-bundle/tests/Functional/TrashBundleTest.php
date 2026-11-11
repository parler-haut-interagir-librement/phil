<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Application\Trash;
use Phil\TrashBundle\Domain\Exception\ParentMissing;
use Phil\TrashBundle\Domain\Model\TrashItem;
use Phil\TrashBundle\Domain\Repository\TrashItemRepository;
use Phil\TrashBundle\PhilTrashBundle;
use Phil\TrashBundle\Tests\Functional\App\Entity\Article;
use Phil\TrashBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PhilTrashBundle::class)]
final class TrashBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testAnArticleGoesToTheTrashComesBackAndIsPurged(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(new Article('s1', 'News'));
        $entityManager->persist(new Article('a1', 'Hello', 's1'));
        $entityManager->flush();

        $trash = $this->service(Trash::class, Trash::class);
        $items = $this->service(TrashItemRepository::class, TrashItemRepository::class);
        $item = $trash->trash($this->article('a1'), 'sub-1');
        $section = $trash->trash($this->article('s1'));
        $entityManager->clear();

        self::assertNull($entityManager->find(Article::class, 'a1'));
        self::assertSame(['News', 'Hello'], array_map(static fn (TrashItem $listed): string => $listed->title(), $items->list()));
        self::assertSame(['Hello'], array_map(static fn (TrashItem $listed): string => $listed->title(), $items->list('article', 1, 1)));
        self::assertSame([], $items->list('page'));

        $stored = $items->get($item->id);
        self::assertNotNull($stored);
        self::assertSame(['article:a1', 'article:s1', 'sub-1', '2026-11-10T12:00:00+00:00'], [$stored->resource()->toString(), $stored->parent()?->toString(), $stored->trashedBy(), $stored->purgeAfter()->format('c')]);

        try {
            $trash->restore($stored);
            self::fail('An article came back under a section that is gone.');
        } catch (ParentMissing) {
        }

        $sectionItem = $items->get($section->id);
        self::assertNotNull($sectionItem);
        $trash->restore($sectionItem);
        $trash->restore($stored, ResourceReference::of('article', 's1'));

        $entityManager->clear();
        self::assertSame('s1', $entityManager->find(Article::class, 'a1')?->section);
        self::assertSame([], $items->list());

        $trash->trash($this->article('a1'));
        self::assertSame(0, $trash->purge(100));
        $this->service('clock', MockClock::class)->modify('+7 days');
        self::assertSame(1, $trash->purge(100));
        self::assertSame([], $items->list());
    }

    private function article(string $id): Article
    {
        $article = $this->entityManager()->find(Article::class, $id);
        self::assertNotNull($article);

        return $article;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}

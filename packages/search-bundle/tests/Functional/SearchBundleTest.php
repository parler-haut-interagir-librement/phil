<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\PhilSearchBundle;
use Phil\SearchBundle\Tests\Functional\App\Entity\Note;
use Phil\SearchBundle\Tests\Functional\App\Entity\Page;
use Phil\SearchBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PhilSearchBundle::class)]
final class SearchBundleTest extends KernelTestCase
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
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);

        // The bundle's table comes with the entities', as a migration would create it.
        foreach (new SchemaTool($entityManager)->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testPagesAreFoundAsTheyAreFlushed(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $search = $this->service(Search::class, Search::class);
        $about = new Page('about', 'À propos de nous');
        $entityManager->persist($about);
        $entityManager->persist(new Page('games', 'Jeux de rôle', 'fr'));
        $entityManager->persist(new Page('games-en', 'Role games', 'en'));
        $entityManager->flush();

        self::assertSame(['about'], $search->search('pages', SearchQuery::of('propos'))->ids);
        self::assertSame(['games'], $search->search('pages', SearchQuery::of('rôle'))->ids);
        self::assertSame(['games-en'], $search->search('pages', SearchQuery::of('role'))->ids);
        self::assertSame(['games-en'], $search->search('pages', SearchQuery::of('role', ['locale' => 'en']))->ids);

        $about->title = 'Qui sommes-nous';
        $entityManager->flush();
        self::assertSame([], $search->search('pages', SearchQuery::of('propos'))->ids);

        $entityManager->remove($about);
        $entityManager->flush();
        self::assertSame(2, $search->search('pages', SearchQuery::of(''))->total);
        self::assertSame(2, $search->reindex('pages'));
    }

    public function testAnEntityWithAGeneratedIdentifierLeavesTheIndexWhenDeleted(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $search = $this->service(Search::class, Search::class);
        $note = new Note('Une soirée de jeux');
        $entityManager->persist($note);
        $entityManager->persist(new Note('Une autre soirée'));
        $entityManager->flush();
        self::assertSame(2, $search->search('notes', SearchQuery::of('soirée'))->total);

        $entityManager->remove($note);
        $entityManager->flush();

        self::assertNull($note->id);
        self::assertSame(1, $search->search('notes', SearchQuery::of('soirée'))->total);
        self::assertSame([], $search->search('notes', SearchQuery::of('jeux'))->ids);
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

<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ReferencesBundle\Application\References;
use Phil\ReferencesBundle\Domain\Exception\StillReferenced;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\ReferencesBundle\PhilReferencesBundle;
use Phil\ReferencesBundle\Tests\Functional\App\Entity\Image;
use Phil\ReferencesBundle\Tests\Functional\App\Entity\Page;
use Phil\ReferencesBundle\Tests\Functional\App\Entity\Post;
use Phil\ReferencesBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

#[CoversClass(PhilReferencesBundle::class)]
final class ReferencesBundleTest extends KernelTestCase
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
        $schemaTool = new SchemaTool($entityManager);

        // The bundle's table comes with the entities', as a migration would create it.
        foreach ($schemaTool->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testUsagesFollowTheFlushesAndAnImageInUseStays(): void
    {
        $entityManager = $this->entityManager();
        $references = $this->references();
        $hero = new Image('hero');
        $page = new Page('about', [['type' => 'text'], ['type' => 'image', 'image' => 'hero'], ['type' => 'image', 'image' => 'gone']]);
        $entityManager->persist($hero);
        $entityManager->persist($page);
        $entityManager->flush();

        self::assertSame(['page:about blocks.1.image'], $this->described($references->usagesOf(ResourceReference::of('image', 'hero'))));
        self::assertSame(['page:about blocks.2.image'], $this->described($references->broken()));

        $entityManager->remove($hero);

        try {
            $entityManager->flush();
            self::fail('An image still used was deleted.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf(StillReferenced::class, $throwable);
            self::assertSame('image:hero is still used by page:about (blocks.1.image).', $throwable->getMessage());
        }
    }

    public function testASourceUpdatedOrDeletedLetsGo(): void
    {
        $entityManager = $this->entityManager();
        $references = $this->references();
        $hero = new Image('hero');
        $page = new Page('about', [['type' => 'image', 'image' => 'hero']]);
        $entityManager->persist($hero);
        $entityManager->persist($page);
        $entityManager->flush();

        $page->blocks = [['type' => 'text']];
        $entityManager->flush();
        self::assertSame([], $references->usagesOf(ResourceReference::of('image', 'hero')));

        $page->blocks = [['type' => 'image', 'image' => 'hero']];
        $entityManager->flush();
        $entityManager->remove($page);
        $entityManager->remove($hero);
        $entityManager->flush();
        self::assertSame([], $references->usagesOf(ResourceReference::of('image', 'hero')));
        self::assertNull($entityManager->find(Image::class, 'hero'));
    }

    public function testASourceWithAGeneratedIdentifierLetsGoWhenDeleted(): void
    {
        $entityManager = $this->entityManager();
        $references = $this->references();
        $hero = new Image('hero');
        $post = new Post('hero');
        $entityManager->persist($hero);
        $entityManager->persist($post);
        $entityManager->flush();
        self::assertSame(['post:'.$post->id.' cover'], $this->described($references->usagesOf(ResourceReference::of('image', 'hero'))));

        $entityManager->remove($post);
        $entityManager->flush();
        self::assertNull($post->id);
        self::assertSame([], $references->usagesOf(ResourceReference::of('image', 'hero')));

        $entityManager->remove($hero);
        $entityManager->flush();
        self::assertNull($entityManager->find(Image::class, 'hero'));
    }

    /**
     * @param list<Usage> $usages
     *
     * @return list<string>
     */
    private function described(array $usages): array
    {
        return array_map(static fn (Usage $usage): string => $usage->source->toString().' '.$usage->field, $usages);
    }

    private function references(): References
    {
        return $this->service(References::class, References::class);
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

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

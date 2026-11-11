<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\HttpCacheTagsBundle\Application\Invalidator;
use Phil\HttpCacheTagsBundle\PhilHttpCacheTagsBundle;
use Phil\HttpCacheTagsBundle\Tests\Functional\App\Entity\Page;
use Phil\HttpCacheTagsBundle\Tests\Functional\App\RecordingVarnish;
use Phil\HttpCacheTagsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

#[CoversClass(PhilHttpCacheTagsBundle::class)]
final class HttpCacheTagsBundleTest extends KernelTestCase
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
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $entityManager->persist(new Page('about', 'About us'));
        $entityManager->flush();

        // The page's creation is purged before the test starts.
        $invalidator = $this->service(Invalidator::class, Invalidator::class);
        self::assertSame(['page:about', 'page-list'], $invalidator->pending());
        $invalidator->flush();
    }

    public function testAPageIsTaggedAndPurgedWhenItChanges(): void
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        $varnish = $this->service(RecordingVarnish::class, RecordingVarnish::class);
        $varnish->purged = [];

        $request = Request::create('/fr/pages/about');
        $response = $kernel->handle($request);
        self::assertSame(['<h1>About us</h1>', 'page-list site:fr page:about menu late'], [trim((string) $response->getContent()), $response->headers->get('xkey')]);
        $kernel->terminate($request, $response);
        self::assertSame([], $varnish->purged);

        $rename = Request::create('/fr/pages/about', 'POST');
        $renamed = $kernel->handle($rename);
        self::assertFalse($renamed->headers->has('xkey'));
        self::assertSame([], $varnish->purged);
        $kernel->terminate($rename, $renamed);
        self::assertSame(['page:about page-list'], $varnish->purged);
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

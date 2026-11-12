<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Application\RunReport;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Phil\ScheduledTransitionsBundle\PhilScheduledTransitionsBundle;
use Phil\ScheduledTransitionsBundle\Tests\Functional\App\Entity\Page;
use Phil\ScheduledTransitionsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PhilScheduledTransitionsBundle::class)]
final class ScheduledTransitionsBundleTest extends KernelTestCase
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
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testAPageIsPublishedThenUnpublishedOnTime(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $entityManager->persist(new Page('about'));
        $entityManager->flush();

        $scheduler = $this->service(Scheduler::class, Scheduler::class);
        $clock = $this->service('clock', MockClock::class);
        $page = ResourceReference::of('page', 'about');

        $scheduler->schedule($page, 'publish', new DateTimeImmutable('2026-11-09 13:00 UTC'));
        $scheduler->schedule($page, 'unpublish', new DateTimeImmutable('2026-11-09 14:00 UTC'));
        self::assertEquals(new RunReport(0, 0), $scheduler->runDue());

        $clock->modify('+1 hour');
        self::assertEquals(new RunReport(1, 0), $scheduler->runDue());
        $entityManager->clear();
        self::assertSame('published', $entityManager->find(Page::class, 'about')?->marking);

        $clock->modify('+1 hour');
        self::assertEquals(new RunReport(1, 0), $scheduler->runDue());
        $entityManager->clear();
        self::assertSame('draft', $entityManager->find(Page::class, 'about')?->marking);
        self::assertSame([], $scheduler->pendingFor($page));
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

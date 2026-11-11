<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Phil\ReferencesBundle\Application\References;
use Phil\ReferencesBundle\Domain\Exception\StillReferenced;
use Phil\ReferencesBundle\Infrastructure\Console\CheckReferencesCommand;
use Phil\ReferencesBundle\Infrastructure\Doctrine\ReferenceTrackingListener;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\Document;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\ImageTarget;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\InMemoryUsages;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\PageCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ReferenceTrackingListener::class)]
#[CoversClass(CheckReferencesCommand::class)]
final class TrackingTest extends TestCase
{
    /** @var array<int, UnitOfWork> by entity manager */
    private array $unitsOfWork = [];

    public function testAFlushRefreshesAndForgetsItsSources(): void
    {
        $usages = new InMemoryUsages();
        $listener = new ReferenceTrackingListener($this->references($usages));
        $kept = new Document('page', '1', ['9']);
        $gone = new Document('page', '2', ['9']);
        $entityManager = $this->entityManager([$kept], [$gone], []);

        $listener->onFlush(new OnFlushEventArgs($entityManager));
        self::assertSame([], $usages->sources());
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame(['page:1', 'page:2'], $usages->sources());

        $kept->images = ['8'];
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([], [$kept], [$gone], $entityManager)));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame(['page:1'], $usages->sources());
        self::assertSame('image:8', $usages->from('page:1')[0]->target->toString());

        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame(['page:1'], $usages->sources());

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new Document('page', '3', ['9'])], [], [], $entityManager)));
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new Document('page', '4', ['9'])], [], [$kept], $entityManager)));
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([], [], [new Document('page', '3')], $entityManager)));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame(['page:4'], $usages->sources());
    }

    public function testADeletedSourceIsForgottenUnderTheIdentifierItHadBeforeTheFlush(): void
    {
        $usages = new InMemoryUsages();
        $references = $this->references($usages);
        $page = new Document('page', '7', ['9']);
        $references->refresh($page);
        $listener = new ReferenceTrackingListener($references);
        $entityManager = $this->entityManager([], [], [$page]);

        $listener->onFlush(new OnFlushEventArgs($entityManager));
        // Doctrine empties a generated identifier once the row is deleted.
        $page->id = '';
        $listener->postFlush(new PostFlushEventArgs($entityManager));

        self::assertSame([], $usages->sources());
    }

    public function testWhatAFailedFlushNotedIsNeverApplied(): void
    {
        $usages = new InMemoryUsages();
        $listener = new ReferenceTrackingListener($this->references($usages));
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new Document('page', 'rolled-back', ['9'])])));

        $next = $this->entityManager([new Document('page', 'kept', ['9'])]);
        $listener->onFlush(new OnFlushEventArgs($next));
        $listener->postFlush(new PostFlushEventArgs($next));

        $cleared = $this->entityManager([new Document('page', 'cleared', ['9'])]);
        $listener->onFlush(new OnFlushEventArgs($cleared));
        $listener->onClear(new OnClearEventArgs($cleared));
        $listener->postFlush(new PostFlushEventArgs($cleared));

        $reset = $this->entityManager([new Document('page', 'reset', ['9'])]);
        $listener->onFlush(new OnFlushEventArgs($reset));
        $listener->reset();
        $listener->postFlush(new PostFlushEventArgs($reset));

        self::assertSame(['page:kept'], $usages->sources());
    }

    public function testATargetStillUsedRefusesItsDeletion(): void
    {
        $usages = new InMemoryUsages();
        $references = $this->references($usages);
        $references->refresh(new Document('page', '1', ['9']));

        $image = new Document('image', '9');

        try {
            new ReferenceTrackingListener($references)->onFlush(new OnFlushEventArgs($this->entityManager([], [], [$image])));
            self::fail('An image still used was deleted.');
        } catch (StillReferenced $stillReferenced) {
            self::assertSame('image:9', $stillReferenced->target->toString());
        }

        new ReferenceTrackingListener($references)->onFlush(new OnFlushEventArgs($this->entityManager([], [], [$image, new Document('page', '1')])));
        new ReferenceTrackingListener($references, false)->onFlush(new OnFlushEventArgs($this->entityManager([], [], [$image])));
        $this->addToAssertionCount(2);
    }

    public function testTheCommandListsBrokenLinks(): void
    {
        $usages = new InMemoryUsages();
        $references = $this->references($usages);
        $tester = new CommandTester(new CheckReferencesCommand($references));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("No broken link.\n", $tester->getDisplay());

        $references->refresh(new Document('page', '1', ['9', '7']));
        $broken = new CommandTester(new CheckReferencesCommand($references));
        self::assertSame(Command::FAILURE, $broken->execute([]));
        self::assertSame("page:1 (blocks.1.image) → image:7, gone\n", $broken->getDisplay());
        self::assertSame(['phil:references:check', 'Lists the links to resources that are gone'], [new CheckReferencesCommand($references)->getName(), new CheckReferencesCommand($references)->getDescription()]);
    }

    private function references(InMemoryUsages $usages): References
    {
        return new References($usages, [new PageCollector()], [new ImageTarget(['9'])]);
    }

    /**
     * An entity manager whose unit of work schedules these entities; given one, the same entity manager.
     *
     * @param list<object> $inserted
     * @param list<object> $updated
     * @param list<object> $deleted
     */
    private function entityManager(array $inserted, array $updated = [], array $deleted = [], ?EntityManagerInterface $same = null): EntityManagerInterface
    {
        $unitOfWork = self::createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn(array_combine(array_map(spl_object_id(...), $inserted), $inserted));
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn(array_combine(array_map(spl_object_id(...), $updated), $updated));
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn(array_combine(array_map(spl_object_id(...), $deleted), $deleted));

        if ($same instanceof EntityManagerInterface) {
            $this->unitsOfWork[spl_object_id($same)] = $unitOfWork;

            return $same;
        }

        $entityManager = self::createStub(EntityManagerInterface::class);
        $this->unitsOfWork[spl_object_id($entityManager)] = $unitOfWork;
        $entityManager->method('getUnitOfWork')->willReturnCallback(fn (): UnitOfWork => $this->unitsOfWork[spl_object_id($entityManager)]);

        return $entityManager;
    }
}

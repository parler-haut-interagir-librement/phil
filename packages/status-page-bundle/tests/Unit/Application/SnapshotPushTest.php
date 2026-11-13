<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Application;

use Phil\StatusPageBundle\Application\Message\PushStatusSnapshot;
use Phil\StatusPageBundle\Application\Render\AtomFeed;
use Phil\StatusPageBundle\Application\Render\JsonDocument;
use Phil\StatusPageBundle\Application\Snapshot\PushReport;
use Phil\StatusPageBundle\Application\Snapshot\Snapshot;
use Phil\StatusPageBundle\Application\Snapshot\SnapshotFile;
use Phil\StatusPageBundle\Application\SnapshotPush;
use Phil\StatusPageBundle\Application\StatusPageBuilder;
use Phil\StatusPageBundle\Domain\Exception\UnknownSnapshotTarget;
use Phil\StatusPageBundle\Infrastructure\Source\DeclaredStatuses;
use Phil\StatusPageBundle\Tests\Unit\Support\Fixtures;
use Phil\StatusPageBundle\Tests\Unit\Support\MemoryPublisher;
use Phil\StatusPageBundle\Tests\Unit\Support\MemoryRecords;
use Phil\StatusPageBundle\Tests\Unit\Support\PlainRenderer;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SnapshotPush::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(SnapshotFile::class)]
#[CoversClass(PushReport::class)]
#[CoversClass(PushStatusSnapshot::class)]
#[CoversClass(UnknownSnapshotTarget::class)]
final class SnapshotPushTest extends TestCase
{
    public function testTheSnapshotHoldsTheJsonTheFeedAndTheHtmlLast(): void
    {
        $snapshot = $this->push([])->snapshot();

        self::assertEquals(Fixtures::now(), $snapshot->generatedAt);
        self::assertSame([Snapshot::JSON, Snapshot::ATOM, Snapshot::HTML], array_map(static fn (SnapshotFile $file): string => $file->path, $snapshot->files));
        self::assertSame(['application/json', 'application/atom+xml', 'text/html; charset=utf-8'], array_map(static fn (SnapshotFile $file): string => $file->mediaType, $snapshot->files));
        self::assertSame('Example|operational|./|status.json|feed.atom|standalone', $snapshot->files[2]->contents);
        self::assertStringContainsString('"url": "https://status.example.org/mirror/"', $snapshot->files[0]->contents);
        self::assertStringContainsString('<link rel="self" href="https://status.example.org/mirror/feed.atom"', $snapshot->files[1]->contents);
        self::assertStringContainsString('<link rel="alternate" href="https://status.example.org/mirror/status.json"', $snapshot->files[1]->contents);
    }

    public function testEveryTargetTakesTheSameSnapshotAndAFailureIsReportedWithoutItsMessage(): void
    {
        $first = new MemoryPublisher();
        $broken = new MemoryPublisher(fails: true);
        $last = new MemoryPublisher();
        $logger = $this->logger();

        $report = $this->push(['first' => $first, 'broken' => $broken, 'last' => $last], $logger)->push();

        self::assertSame(['first', 'last'], $report->published);
        self::assertSame(['broken' => 'RuntimeException'], $report->failed);
        self::assertFalse($report->isSuccessful());
        self::assertSame([$report->snapshot], $first->published);
        self::assertSame([$report->snapshot], $last->published);
        self::assertSame(['error The status page snapshot was not published to {target}. broken RuntimeException'], $logger->lines);
    }

    public function testOneTargetIsPublishedAlone(): void
    {
        $first = new MemoryPublisher();
        $second = new MemoryPublisher();

        $report = $this->push(['first' => $first, 'second' => $second])->push('second');

        self::assertSame(['second'], $report->published);
        self::assertSame([], $report->failed);
        self::assertTrue($report->isSuccessful());
        self::assertSame([], $first->published);
        self::assertCount(1, $second->published);
    }

    public function testAnUnknownTargetIsRefusedBeforeAnythingIsWritten(): void
    {
        $first = new MemoryPublisher();

        try {
            $this->push(['first' => $first, 'second' => new MemoryPublisher()])->push('third');
            self::fail('An unknown target is refused.');
        } catch (UnknownSnapshotTarget $unknownSnapshotTarget) {
            self::assertSame('No snapshot target is named "third"; the targets are: first, second.', $unknownSnapshotTarget->getMessage());
        }

        self::assertSame([], $first->published);
        self::assertSame('No snapshot target is named "a"; the targets are: none.', UnknownSnapshotTarget::named('a', [])->getMessage());
    }

    public function testAMessageNamesItsTargetOrNone(): void
    {
        self::assertNull(new PushStatusSnapshot()->target);
        self::assertSame('mirror', new PushStatusSnapshot('mirror')->target);
    }

    /**
     * @param array<string, MemoryPublisher> $publishers
     */
    private function push(array $publishers, ?AbstractLogger $logger = null): SnapshotPush
    {
        $builder = new StatusPageBuilder('Example', Fixtures::components(), new DeclaredStatuses([]), new MemoryRecords(), new MockClock(Fixtures::now()));

        return new SnapshotPush($builder, new PlainRenderer(), new JsonDocument(), new AtomFeed(), Url::fromString('https://status.example.org/mirror/'), $publishers, $logger);
    }

    /**
     * @return AbstractLogger&object{lines: list<string>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $exception = $context['exception'] ?? null;
                $target = $context['target'] ?? '';
                $this->lines[] = \sprintf('%s %s %s %s', \is_string($level) ? $level : '', $message, \is_string($target) ? $target : '', $exception instanceof RuntimeException ? $exception::class : '');
            }
        };
    }

    public function testAFailureIsReportedWithoutALogger(): void
    {
        $report = $this->push(['broken' => new MemoryPublisher(fails: true)])->push();

        self::assertSame([], $report->published);
        self::assertSame(['broken' => 'RuntimeException'], $report->failed);
    }
}

<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use Exception;
use LogicException;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Exception\InvalidOption;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTracking\Tests\Unit\Fixture\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

#[CoversClass(EventFactory::class)]
#[CoversClass(InvalidOption::class)]
final class EventFactoryTest extends TestCase
{
    public function testAnEventFromAThrowable(): void
    {
        $root = \dirname(__DIR__, 2);
        $request = new RequestSummary('GET', '/a');
        $event = new EventFactory($root.'/', new FrozenClock(), '1.2.0', 'prod')->fromThrowable($this->thrown(), new CaptureContext(Source::Console, Severity::Fatal, $request, ['command' => 'app:export']));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $event->id);
        self::assertSame('2026-11-08T10:00:00.250000+00:00', $event->occurredAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(Source::Console, $event->source);
        self::assertSame(Severity::Fatal, $event->severity);
        self::assertSame($request, $event->request);
        self::assertSame(['command' => 'app:export'], $event->context);
        self::assertSame('1.2.0', $event->release);
        self::assertSame('prod', $event->environment);
        self::assertCount(2, $event->exceptions);
        [$thrown, $previous] = $event->exceptions;
        self::assertSame(RuntimeException::class, $thrown->type);
        self::assertSame('The export failed', $thrown->message);
        self::assertSame(LogicException::class, $previous->type);
        self::assertSame('Disk full', $previous->message);

        $top = $thrown->frames[0];
        self::assertSame('tests/Unit/EventFactoryTest.php', $top->file);
        self::assertSame(self::class.'::thrown', $top->function);
        self::assertTrue($top->inApp);
        self::assertSame(self::class.'::testAnEventFromAThrowable', $thrown->frames[1]->function);
        self::assertSame('tests/Unit/EventFactoryTest.php', $thrown->frames[1]->file);
        self::assertSame(self::class.'::{closure}', $previous->frames[0]->function);
        self::assertSame(self::class.'::thrown', $previous->frames[1]->function);

        $runner = array_values(array_filter($thrown->frames, static fn (Frame $frame): bool => !str_starts_with($frame->file, 'tests/')));
        self::assertNotSame([], $runner);
        self::assertSame([], array_filter($runner, static fn (Frame $frame): bool => $frame->inApp));
        self::assertNull($thrown->frames[\count($thrown->frames) - 1]->function);
    }

    public function testFramesOutsideTheProjectKeepTheirPath(): void
    {
        $factory = new EventFactory(__DIR__, new FrozenClock());
        $frames = $factory->fromThrowable($this->thrown())->exceptions[0]->frames;

        self::assertSame('EventFactoryTest.php', $frames[0]->file);
        self::assertTrue($frames[0]->inApp);
        $outside = array_values(array_filter($frames, static fn (Frame $frame): bool => str_starts_with($frame->file, '/')));
        self::assertNotSame([], $outside);
        self::assertFalse($outside[0]->inApp);
        self::assertEquals(new Frame('/elsewhere/a.php', 3, 'f'), $factory->frame('/elsewhere/a.php', 3, 'f'));
    }

    public function testAFileNamedLikeTheProjectIsNotInIt(): void
    {
        $frame = new EventFactory('/app', new FrozenClock())->frame('/application/a.php', 3);

        self::assertSame('/application/a.php', $frame->file);
        self::assertFalse($frame->inApp);
        self::assertSame('src/a.php', new EventFactory('/app', new FrozenClock())->frame('/app/src/a.php', 3)->file);
        self::assertTrue(new EventFactory('/app', new FrozenClock())->frame('/app/src/a.php', 3)->inApp);
        self::assertFalse(new EventFactory('/app', new FrozenClock())->frame('/app/vendor/a.php', 3)->inApp);
        self::assertTrue(new EventFactory('/app', new FrozenClock())->frame('/app/vendors.php', 3)->inApp);
    }

    public function testTheChainAndTheFramesAreBounded(): void
    {
        $throwable = new RuntimeException('0');

        for ($index = 1; $index < 5; ++$index) {
            $throwable = new RuntimeException((string) $index, 0, $throwable);
        }

        $event = new EventFactory(__DIR__, new FrozenClock(), maxFrames: 2, maxChain: 3)->fromThrowable($throwable);

        self::assertSame(['4', '3', '2'], array_map(static fn (ExceptionRecord $exception): string => $exception->message, $event->exceptions));
        self::assertCount(2, $event->exceptions[0]->frames);
        self::assertCount(1, new EventFactory(__DIR__, new FrozenClock(), maxFrames: 1)->fromThrowable($throwable)->exceptions[0]->frames);
        self::assertCount(1, new EventFactory(__DIR__, new FrozenClock(), maxChain: 1)->fromThrowable($throwable)->exceptions);
        self::assertCount(5, new EventFactory(__DIR__, new FrozenClock())->fromThrowable($throwable)->exceptions);
        self::assertSame([50, 10], [EventFactory::DEFAULT_MAX_FRAMES, EventFactory::DEFAULT_MAX_CHAIN]);
    }

    public function testAThrowableWithoutTrace(): void
    {
        $throwable = new class('lonely') extends RuntimeException {
        };
        $reflection = new ReflectionProperty(Exception::class, 'trace');
        $reflection->setValue($throwable, [['function' => 'main'], ['function' => 'boot', 'file' => '/app/src/Kernel.php', 'line' => 9, 'class' => 'App\Kernel']]);

        $event = new EventFactory('/app', new FrozenClock())->fromThrowable($throwable);

        self::assertSame('RuntimeException@anonymous', $event->exceptions[0]->type);
        self::assertSame('main', $event->exceptions[0]->frames[0]->function);
        self::assertCount(2, $event->exceptions[0]->frames);
        self::assertSame('src/Kernel.php', $event->exceptions[0]->frames[1]->file);
        self::assertSame(9, $event->exceptions[0]->frames[1]->line);
        self::assertNull($event->exceptions[0]->frames[1]->function);

        $reflection->setValue($throwable, [['function' => 'boot', 'file' => '/app/src/Kernel.php']]);
        self::assertSame(0, new EventFactory('/app', new FrozenClock())->fromThrowable($throwable)->exceptions[0]->frames[1]->line);
        $reflection->setValue($throwable, []);
        self::assertNull(new EventFactory('/app', new FrozenClock())->fromThrowable($throwable)->exceptions[0]->frames[0]->function);
    }

    public function testAnEventFromAMessage(): void
    {
        $frames = [new Frame('a.js', 1), new Frame('b.js', 2), new Frame('c.js', 3)];
        $event = new EventFactory('/app', new FrozenClock(), null, 'dev', maxFrames: 2)->fromMessage('TypeError', 'x is undefined', $frames, new CaptureContext(Source::Browser));

        self::assertSame('TypeError: x is undefined', $event->title());
        self::assertSame([$frames[0], $frames[1]], $event->exceptions[0]->frames);
        self::assertSame(Source::Browser, $event->source);
        self::assertSame(Severity::Error, $event->severity);
        self::assertNull($event->release);
        self::assertSame('dev', $event->environment);
        self::assertSame(Source::Manual, new EventFactory('/app', new FrozenClock())->fromMessage('E', 'm', [])->source);
    }

    #[TestWith([0, 1])]
    #[TestWith([1, 0])]
    public function testABoundBelowOneIsRefused(int $frames, int $chain): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The frames and the exceptions of a chain are bounded by one at least.');

        new EventFactory('/app', new FrozenClock(), maxFrames: $frames, maxChain: $chain);
    }

    private function thrown(): Throwable
    {
        $previous = (static fn (): LogicException => new LogicException('Disk full'))();

        return new RuntimeException('The export failed', 0, $previous);
    }
}

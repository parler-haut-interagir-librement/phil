<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\Exception\InvalidEvent;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTracking\Tests\Unit\Fixture\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ErrorEvent::class)]
#[CoversClass(InvalidEvent::class)]
final class ErrorEventTest extends TestCase
{
    public function testAnEventKeepsItsValues(): void
    {
        $request = new RequestSummary('GET', '/a');
        $event = Events::one(request: $request, context: ['transport' => 'async', 'retries' => 2]);

        self::assertSame(str_repeat('a', 32), $event->id);
        self::assertEquals(new DateTimeImmutable('2026-11-08T10:00:00+00:00'), $event->occurredAt);
        self::assertSame(Source::Http, $event->source);
        self::assertSame(Severity::Error, $event->severity);
        self::assertSame('1.0.0', $event->release);
        self::assertSame('prod', $event->environment);
        self::assertSame($request, $event->request);
        self::assertSame(['transport' => 'async', 'retries' => 2], $event->context);
        self::assertSame('RuntimeException: User 42 not found', $event->title());
        self::assertSame('RuntimeException', Events::one('')->title());
        self::assertSame('src/Export.php', $event->culprit()?->file);
    }

    public function testTheCulpritIsLookedForAlongTheChain(): void
    {
        $culprit = new Frame('src/Cause.php', 4, null, true);
        $event = new ErrorEvent(str_repeat('b', 32), new DateTimeImmutable(), Source::Console, Severity::Fatal, [
            new ExceptionRecord('A', 'a', [new Frame('vendor/x.php', 1)]),
            new ExceptionRecord('B', 'b', [$culprit]),
            new ExceptionRecord('C', 'c', [new Frame('src/Other.php', 4, null, true)]),
        ]);

        self::assertSame($culprit, $event->culprit());
        self::assertNull(new ErrorEvent(str_repeat('b', 32), new DateTimeImmutable(), Source::Console, Severity::Fatal, [new ExceptionRecord('A', 'a')])->culprit());
    }

    public function testTheDefaults(): void
    {
        $event = new ErrorEvent(str_repeat('c', 32), new DateTimeImmutable(), Source::Manual, Severity::Warning, [new ExceptionRecord('A', '')]);

        self::assertNull($event->release);
        self::assertNull($event->environment);
        self::assertNull($event->request);
        self::assertSame([], $event->context);
    }

    public function testTheTextsAreBounded(): void
    {
        $context = [];

        for ($index = 0; $index < 60; ++$index) {
            $context['k'.$index] = $index;
        }

        $context['k10'] = str_repeat('v', 2000);
        $event = new ErrorEvent(str_repeat('c', 32), new DateTimeImmutable(), Source::Manual, Severity::Warning, [new ExceptionRecord('A', '')], str_repeat('r', 200), str_repeat('e', 100), null, $context + [str_repeat('k', 80) => true]);

        self::assertSame(str_repeat('r', 128), $event->release);
        self::assertSame(str_repeat('e', 64), $event->environment);
        self::assertCount(ErrorEvent::MAX_CONTEXT, $event->context);
        self::assertSame(str_repeat('v', ErrorEvent::MAX_CONTEXT_LENGTH), $event->context['k10']);
        self::assertSame(49, $event->context['k49']);
        self::assertSame([50, 1024], [ErrorEvent::MAX_CONTEXT, ErrorEvent::MAX_CONTEXT_LENGTH]);
        self::assertSame([str_repeat('k', 64) => true], new ErrorEvent(str_repeat('c', 32), new DateTimeImmutable(), Source::Manual, Severity::Warning, [new ExceptionRecord('A', '')], context: [str_repeat('k', 80) => true])->context);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'short' => [str_repeat('a', 31)];
        yield 'long' => [str_repeat('a', 33)];
        yield 'upper case' => [str_repeat('A', 32)];
        yield 'trailing line' => [str_repeat('a', 32)."\n"];
        yield 'leading text' => ['x'.str_repeat('a', 32)];
    }

    #[DataProvider('badIdentifiers')]
    public function testABadIdentifierIsRefused(string $id): void
    {
        $this->expectException(InvalidEvent::class);
        $this->expectExceptionMessageIsOrContains('An event identifier is 32 lower-case hexadecimal digits.');

        new ErrorEvent($id, new DateTimeImmutable(), Source::Manual, Severity::Error, [new ExceptionRecord('A', '')]);
    }

    public function testAnEmptyChainIsRefused(): void
    {
        $this->expectException(InvalidEvent::class);
        $this->expectExceptionMessageIsOrContains('An event holds at least one exception.');

        new ErrorEvent(str_repeat('a', 32), new DateTimeImmutable(), Source::Manual, Severity::Error, []);
    }

    public function testWithReplacesTheTexts(): void
    {
        $event = Events::one(context: ['a' => 1]);
        $request = new RequestSummary('GET', '/b');
        $other = $event->with([new ExceptionRecord('B', 'b')], $request, ['b' => 2]);

        self::assertSame('B: b', $other->title());
        self::assertSame($request, $other->request);
        self::assertSame(['b' => 2], $other->context);
        self::assertSame([$event->id, $event->occurredAt, $event->source, $event->severity, $event->release, $event->environment], [$other->id, $other->occurredAt, $other->source, $other->severity, $other->release, $other->environment]);
    }

    public function testThePlainValues(): void
    {
        $event = Events::one(request: new RequestSummary('GET', '/a', 'home', ['accept' => 'text/html'], '192.0.2.0'), context: ['retries' => 2, 'retry' => false, 'ratio' => 0.5, 'none' => null]);
        $data = $event->toArray();

        self::assertSame([
            'id' => str_repeat('a', 32),
            'occurred_at' => '2026-11-08T10:00:00.000000+00:00',
            'source' => 'http',
            'severity' => 'error',
            'release' => '1.0.0',
            'environment' => 'prod',
            'exceptions' => [[
                'type' => 'RuntimeException',
                'message' => 'User 42 not found',
                'frames' => [
                    ['file' => 'vendor/lib/Client.php', 'line' => 10, 'function' => 'Lib\Client::send', 'in_app' => false],
                    ['file' => 'src/Export.php', 'line' => 20, 'function' => 'App\Export::run', 'in_app' => true],
                ],
            ]],
            'request' => ['method' => 'GET', 'url' => '/a', 'route' => 'home', 'headers' => ['accept' => 'text/html'], 'client_address' => '192.0.2.0'],
            'context' => ['retries' => 2, 'retry' => false, 'ratio' => 0.5, 'none' => null],
        ], $data);
        self::assertEquals($event, ErrorEvent::fromArray($data));
        self::assertNull(Events::one()->toArray()['request']);
        self::assertEquals(Events::one(release: null), ErrorEvent::fromArray(Events::one(release: null)->toArray()));
    }

    public function testReadingBackDropsWhatIsNotAValue(): void
    {
        $data = Events::one(request: new RequestSummary('GET', '/a'))->toArray();
        $frames = $data['exceptions'][0]['frames'];
        $frames[0]['in_app'] = 'yes';
        $event = ErrorEvent::fromArray([
            'context' => ['kept' => 'yes', 'list' => [1], 'object' => new stdClass(), 'none' => null],
            'request' => ['headers' => ['a' => 'b', 'c' => ['d']]] + (array) $data['request'],
            'exceptions' => [['frames' => [...$frames, 'not a frame']] + $data['exceptions'][0], 'not an exception'],
        ] + $data);

        self::assertSame(['kept' => 'yes', 'none' => null], $event->context);
        self::assertSame(['a' => 'b'], $event->request?->headers);
        self::assertCount(1, $event->exceptions);
        self::assertCount(2, $event->exceptions[0]->frames);
        self::assertFalse($event->exceptions[0]->frames[0]->inApp);
        self::assertTrue($event->exceptions[0]->frames[1]->inApp);
    }

    /**
     * @return iterable<string, array{Closure(array<array-key, mixed>): array<array-key, mixed>, string}>
     */
    public static function badData(): iterable
    {
        yield 'no instant' => [static fn (array $data): array => ['occurred_at' => null] + $data, '"occurred_at" is not a text.'];
        yield 'not a date' => [static fn (array $data): array => ['occurred_at' => 'never'] + $data, 'The instant of the event is not a date.'];
        yield 'unknown source' => [static fn (array $data): array => ['source' => 'mail'] + $data, 'An unknown source.'];
        yield 'unknown severity' => [static fn (array $data): array => ['severity' => 'mild'] + $data, 'An unknown severity.'];
        yield 'release of another type' => [static fn (array $data): array => ['release' => 3] + $data, '"release" is not a text.'];
        yield 'no exceptions' => [static fn (array $data): array => ['exceptions' => 'none'] + $data, '"exceptions" is not a list.'];
        yield 'no context' => [static fn (array $data): array => ['context' => null] + $data, '"context" is not a list.'];
        yield 'a line of another type' => [static fn (array $data): array => ['exceptions' => [['type' => 'E', 'message' => '', 'frames' => [['file' => 'a.php', 'line' => '10']]]]] + $data, '"line" is not an integer.'];
        yield 'no line' => [static fn (array $data): array => ['exceptions' => [['type' => 'E', 'message' => '', 'frames' => [['file' => 'a.php']]]]] + $data, '"line" is not an integer.'];
    }

    /**
     * @param Closure(array<array-key, mixed>): array<array-key, mixed> $change
     */
    #[DataProvider('badData')]
    public function testBadDataIsRefused(Closure $change, string $message): void
    {
        $this->expectException(InvalidEvent::class);
        $this->expectExceptionMessageIsOrContains($message);

        ErrorEvent::fromArray($change(Events::one()->toArray()));
    }
}

<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Support;

use DateTimeImmutable;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fields::class)]
final class FieldsTest extends TestCase
{
    public function testTypedReads(): void
    {
        $fields = new Fields([
            's' => 'text',
            'i' => 3,
            'f' => 1.5,
            'b' => true,
            'l' => ['a', 'b'],
            'u' => 'https://lms.example.org/x',
            'd' => '2026-11-14T12:00:00.123456Z',
            'o' => ['k' => 'v'],
            'e' => [],
            'ol' => [['k' => 1], []],
            'm' => ['a' => 'b'],
        ], inToken: false);

        self::assertTrue($fields->has('s'));
        self::assertFalse($fields->has('none'));
        self::assertSame('text', $fields->string('s'));
        self::assertSame('text', $fields->string('s', 4));
        self::assertNull($fields->optionalString('none'));
        self::assertSame(3, $fields->optionalInt('i'));
        self::assertNull($fields->optionalInt('none'));
        self::assertSame(1.5, $fields->optionalNumber('f'));
        self::assertSame(3.0, $fields->optionalNumber('i'));
        self::assertNull($fields->optionalNumber('none'));
        self::assertTrue($fields->bool('b'));
        self::assertFalse($fields->bool('none'));
        self::assertTrue($fields->bool('none', true));
        self::assertSame(['a', 'b'], $fields->stringList('l'));
        self::assertSame(['a', 'b'], $fields->stringList('l', 2));
        self::assertSame([], $fields->stringList('none'));
        self::assertSame('https://lms.example.org/x', $fields->url('u')->toString());
        self::assertNull($fields->optionalUrl('none'));
        self::assertSame('2026-11-14 12:00:00.123456', $fields->optionalDate('d')?->format('Y-m-d H:i:s.u'));
        self::assertNull($fields->optionalDate('none'));
        self::assertSame(['k' => 'v'], $fields->object('o')->data);
        self::assertSame([], $fields->object('e')->data);
        self::assertNull($fields->optionalObject('none'));
        self::assertCount(2, $fields->objectList('ol'));
        self::assertSame([], $fields->objectList('none'));
        self::assertSame(['a' => 'b'], $fields->stringMap('m'));
        self::assertSame([], $fields->stringMap('none'));
        self::assertSame([], $fields->stringMap('e'));
    }

    public function testAWrongTypeNamesTheMemberAndItsPath(): void
    {
        $fields = new Fields(['o' => ['i' => 'x', 'n' => 'x', 'f' => \INF, 'l' => [['x' => 1]], 'deep' => ['u' => 'nope']]], inToken: false);
        $nested = $fields->object('o');
        $cases = [
            [static fn (): ?int => $nested->optionalInt('i'), 'The member "o.i" is an integer.'],
            [static fn (): ?float => $nested->optionalNumber('n'), 'The member "o.n" is a number.'],
            [static fn (): bool => $nested->bool('i'), 'The member "o.i" is a boolean.'],
            [static fn (): string => $nested->string('none'), 'The member "o.none" is required.'],
            [static fn (): string => $nested->string('i', 0), 'The member "o.i" is a UTF-8 string of 0 bytes at most.'],
            [static fn (): Url => $nested->url('none'), 'The member "o.none" is required.'],
            [static fn (): Fields => $nested->object('none'), 'The member "o.none" is required.'],
            [static fn (): Fields => $nested->object('i'), 'The member "o.i" is an object.'],
            [static fn (): Fields => $nested->object('l'), 'The member "o.l" is an object.'],
            [static fn (): array => $nested->objectList('i'), 'The member "o.i" is a list of 256 objects at most.'],
            [static fn (): array => $nested->objectList('l', 0), 'The member "o.l" is a list of 0 objects at most.'],
            [static fn (): Url => $nested->object('deep')->url('u'), 'The member "o.deep.u" is not a web address: '],
            [static fn (): array => $nested->stringList('i'), 'The member "o.i" is a list of 256 strings at most.'],
            [static fn (): array => $nested->stringList('l'), 'The member "o.l" is a list of strings.'],
            [static fn (): array => new Fields(['l' => [str_repeat('x', 4097)]])->stringList('l'), 'The member "l" is a list of strings.'],
            [static fn (): array => new Fields(['l' => ['a', 'b']])->stringList('l', 1), 'The member "l" is a list of 1 strings at most.'],
            [static fn (): ?DateTimeImmutable => new Fields(['d' => '2026-11-14 12:00:00Z'])->optionalDate('d'), 'The member "d" is a date and time of ISO 8601 with its offset.'],
            [static fn (): ?DateTimeImmutable => new Fields(['d' => '2026-11-14T12:00:00'])->optionalDate('d'), 'The member "d" is a date and time of ISO 8601 with its offset.'],
            [static fn (): ?DateTimeImmutable => new Fields(['d' => '2026-11-14T12:00:00.1234567890Z'])->optionalDate('d'), 'The member "d" is a date and time of ISO 8601 with its offset.'],
            [static fn (): ?DateTimeImmutable => new Fields(['d' => 'x2026-11-14T12:00:00Z'])->optionalDate('d'), 'The member "d" is a date and time of ISO 8601 with its offset.'],
            [static fn (): ?DateTimeImmutable => new Fields(['d' => "2026-11-14T12:00:00Z\n"])->optionalDate('d'), 'The member "d" is a date and time of ISO 8601 with its offset.'],
            [static fn (): ?DateTimeImmutable => new Fields(['d' => str_repeat('9', 65)])->optionalDate('d'), 'The member "d" is a UTF-8 string of 64 bytes at most.'],
            [static fn (): array => new Fields(['m' => ['a' => 'b', 'c' => 'd']])->stringMap('m', 1), 'The member "m" is an object of 1 members at most.'],
            [static fn (): array => new Fields(['m' => 'x'])->stringMap('m'), 'The member "m" is an object of 256 members at most.'],
            [static fn (): array => new Fields(['m' => ['a' => null]])->stringMap('m'), 'The member "m" maps names to strings.'],
        ];

        foreach ($cases as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidData|InvalidToken $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }
    }

    public function testTheKindOfFailureFollowsWhereTheMembersAre(): void
    {
        self::assertInstanceOf(InvalidToken::class, new Fields([])->fail('x'));
        self::assertInstanceOf(InvalidData::class, new Fields([], inToken: false)->fail('x'));
        self::assertInstanceOf(InvalidData::class, new Fields(['o' => []], inToken: false)->object('o')->fail('x'));
        self::assertInstanceOf(InvalidData::class, new Fields(['l' => [[]]], inToken: false)->objectList('l')[0]->fail('x'));
        self::assertSame('https://tool.localhost/', new Fields(['o' => ['u' => 'https://tool.localhost/']], allowLocalhost: true)->object('o')->url('u')->toString());
        self::assertSame('https://tool.localhost/', new Fields(['l' => [['u' => 'https://tool.localhost/']]], allowLocalhost: true)->objectList('l')[0]->url('u')->toString());
        self::assertSame(['a' => str_repeat('x', 4096)], new Fields(['a' => ['a' => str_repeat('x', 4096)]])->stringMap('a'));
        self::assertSame([str_repeat('k', 255) => 'v'], new Fields(['a' => [str_repeat('k', 255) => 'v']])->stringMap('a'));
        self::assertSame('2026-11-14T12:00:00+01:30', new Fields(['d' => '2026-11-14T12:00:00+01:30'])->optionalDate('d')?->format(\DATE_ATOM));
    }

    public function testTheEdgesOfTheLimits(): void
    {
        self::assertSame([str_repeat('x', 4096)], new Fields(['l' => [str_repeat('x', 4096)]])->stringList('l'));
        self::assertSame(['a', 'b'], new Fields(['l' => ['a', 'b']])->stringList('l', 2));
        self::assertCount(2, new Fields(['l' => [[], []]])->objectList('l', 2));
        self::assertSame(['a' => 'b', 'c' => 'd'], new Fields(['m' => ['a' => 'b', 'c' => 'd']])->stringMap('m', 2));

        foreach ([
            static fn (): array => new Fields(['l' => [[], [], []]])->objectList('l', 2),
            static fn (): array => new Fields(['m' => ['a' => str_repeat('x', 4097)]])->stringMap('m'),
            static fn (): array => new Fields(['m' => [str_repeat('k', 256) => 'v']])->stringMap('m'),
        ] as $call) {
            try {
                $call();
                self::fail('A value over a limit was read.');
            } catch (InvalidToken $exception) {
                self::assertStringContainsString('at most', $exception->getMessage());
            }
        }
    }

    public function testAnAddressOnTheLocalNetworkIsRefusedByDefault(): void
    {
        self::assertSame('https://tool.localhost/', new Fields(['u' => 'https://tool.localhost/'], allowLocalhost: true)->url('u')->toString());

        $this->expectException(InvalidToken::class);
        new Fields(['u' => 'https://tool.localhost/'])->url('u');
    }

    public function testEveryFailureNamesTheMemberWithItsFullPath(): void
    {
        $fields = new Fields(['o' => ['d' => 'nope', 'm' => ['a' => null], 'big' => ['a' => 1, 'b' => 2], 'l' => [[], 'x'], 'list' => [[], [[1]]], 'cap' => [[], [], []], 'bad' => ['n' => 'x']]], inToken: false);
        $nested = $fields->object('o');
        $cases = [
            [static fn (): ?DateTimeImmutable => $nested->optionalDate('d'), 'The member "o.d" is a date and time of ISO 8601 with its offset.'],
            [static fn (): array => $nested->stringMap('m'), 'The member "o.m" maps names to strings.'],
            [static fn (): array => $nested->stringMap('big', 1), 'The member "o.big" is an object of 1 members at most.'],
            [static fn (): array => $nested->objectList('l'), 'The member "o.l" is a list of objects.'],
            [static fn (): array => $nested->objectList('list'), 'The member "o.list" is a list of objects.'],
            [static fn (): array => $nested->objectList('cap', 2), 'The member "o.cap" is a list of 2 objects at most.'],
            [static fn (): float => $nested->objectList('cap')[2]->object('x')->optionalNumber('y') ?? 0.0, 'The member "o.cap[2].x" is required.'],
            [static fn (): string => $nested->objectList('cap')[1]->string('n'), 'The member "o.cap[1].n" is required.'],
            [static fn (): array => new Fields(['m' => ['a' => str_repeat('x', 4097)]], inToken: false)->object('m')->stringMap('a'), 'The member "m.a" is an object of 256 members at most.'],
            [static fn (): array => new Fields(['o' => ['k' => str_repeat('x', 4097)]], inToken: false)->object('o')->stringMap('k'), 'The member "o.k" is an object of 256 members at most.'],
            [static fn (): array => new Fields(['o' => ['m' => ['k' => str_repeat('x', 4097)]]], inToken: false)->object('o')->stringMap('m'), 'The member "o.m" maps names of 255 bytes to strings of 4096 bytes at most.'],
        ];

        foreach ($cases as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidData $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}

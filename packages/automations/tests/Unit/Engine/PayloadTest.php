<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Engine;

use Phil\Automations\Engine\Payload;
use Phil\Automations\Exception\InvalidPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Payload::class)]
final class PayloadTest extends TestCase
{
    public function testItKeepsIdentifiersSortedByKey(): void
    {
        $payload = Payload::of(['b' => 'x', 'a_1' => 2, 'c' => true, 'd' => null, 'e' => str_repeat('é', 127)]);

        self::assertSame(['a_1', 'b', 'c', 'd', 'e'], array_keys($payload->values));
        self::assertSame('x', $payload->get('b'));
        self::assertNull($payload->get('missing'));
        self::assertSame('2', $payload->identifier('a_1'));
        self::assertSame('x', $payload->identifier('b'));
        self::assertNull($payload->identifier('c'));
        self::assertNull($payload->identifier('d'));
        self::assertNull(Payload::of(['a' => ''])->identifier('a'));
        self::assertSame([], Payload::empty()->values);
    }

    public function testItHoldsThirtyTwoEntriesAtMost(): void
    {
        $values = [];

        for ($i = 0; $i < 32; ++$i) {
            $values['k'.$i] = $i;
        }

        self::assertCount(32, Payload::of($values)->values);

        $this->expectException(InvalidPayload::class);
        $this->expectExceptionMessageIsOrContains('A payload holds at most 32 entries; this one holds 33.');
        Payload::of([...$values, 'k32' => 1]);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('refused')]
    public function testItRefusesWhatIsNotAnIdentifier(array $values, string $message): void
    {
        $this->expectException(InvalidPayload::class);
        $this->expectExceptionMessageIsOrContains($message);

        Payload::of($values);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'a numeric key' => [[0 => 'x'], '"0" is not a payload key'];
        yield 'an uppercase key' => [['Key' => 'x'], '"Key" is not a payload key'];
        yield 'a key starting with a digit' => [['1a' => 'x'], '"1a" is not a payload key'];
        yield 'a key too long' => [[str_repeat('a', 65) => 'x'], 'is not a payload key'];
        yield 'a key with a new line' => [["a\n" => 'x'], 'is not a payload key'];
        yield 'a long string' => [['a' => str_repeat('x', 256)], 'The payload value "a" is not an identifier: valid UTF-8 of at most 255 bytes'];
        yield 'a control character' => [['a' => "x\ty"], 'The payload value "a" is not an identifier'];
        yield 'a delete character' => [['a' => "x\x7F"], 'The payload value "a" is not an identifier'];
        yield 'invalid UTF-8' => [['a' => "\xC3"], 'The payload value "a" is not an identifier'];
        yield 'a float' => [['a' => 1.5], 'The payload value "a" is a float'];
        yield 'an array' => [['a' => []], 'The payload value "a" is a array'];
    }

    public function testItAcceptsTheLongestKeyAndString(): void
    {
        $key = 'a'.str_repeat('b', 63);

        self::assertSame([$key => str_repeat('x', 255)], Payload::of([$key => str_repeat('x', 255)])->values);
    }
}

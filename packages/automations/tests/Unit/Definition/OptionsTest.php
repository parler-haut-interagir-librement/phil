<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Definition\Options;
use Phil\Automations\Exception\InvalidDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Options::class)]
final class OptionsTest extends TestCase
{
    public function testItReadsEachTypeAndSortsTheNames(): void
    {
        $options = Options::of(['text' => "line\nnext\ttab", 'count' => 3, 'on' => false, 'none' => null, 'lists' => ['a', 2]]);

        self::assertSame(['count', 'lists', 'none', 'on', 'text'], array_keys($options->values));
        self::assertSame("line\nnext\ttab", $options->string('text'));
        self::assertNull($options->string('count'));
        self::assertSame(3, $options->integer('count'));
        self::assertNull($options->integer('text'));
        self::assertFalse($options->boolean('on'));
        self::assertNull($options->boolean('count'));
        self::assertSame(['a', 2], $options->get('lists'));
        self::assertTrue($options->has('none'));
        self::assertNull($options->get('none'));
        self::assertFalse($options->has('missing'));
        self::assertNull($options->get('missing'));
        self::assertSame([], Options::none()->values);
    }

    public function testItAcceptsItsBounds(): void
    {
        $values = ['list' => array_fill(0, 64, 'x'), 'text' => str_repeat('x', 2048)];

        for ($i = 0; $i < 30; ++$i) {
            $values['k'.$i] = $i;
        }

        self::assertCount(32, Options::of($values)->values);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('refused')]
    public function testItRefusesWhatIsNotAnOption(array $values, string $message): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($message);

        Options::of($values);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        $many = [];

        for ($i = 0; $i < 33; ++$i) {
            $many['k'.$i] = $i;
        }

        yield 'too many' => [$many, 'Options hold at most 32 entries; these hold 33.'];
        yield 'a numeric name' => [['x'], '"0" is not an option name'];
        yield 'a bad name' => [['A' => 1], '"A" is not an option name'];
        yield 'a long string' => [['a' => str_repeat('x', 2049)], 'The option "a" is valid UTF-8 of at most 2048 bytes, without control characters.'];
        yield 'a control character' => [['a' => "x\x01"], 'The option "a" is valid UTF-8'];
        yield 'a delete character' => [['a' => "x\x7F"], 'The option "a" is valid UTF-8'];
        yield 'invalid UTF-8' => [['a' => "\xFF"], 'The option "a" is valid UTF-8'];
        yield 'a float' => [['a' => 1.5], 'The option "a" is a float'];
        yield 'a map' => [['a' => ['k' => 'v']], 'The option "a" is a list of at most 64 items, without keys.'];
        yield 'a long list' => [['a' => array_fill(0, 65, 1)], 'The option "a" is a list of at most 64 items'];
        yield 'a list of booleans' => [['a' => [true]], 'The list of the option "a" holds integers and strings only.'];
        yield 'a list with a bad string' => [['a' => ["\x00"]], 'The option "a" is valid UTF-8'];
    }
}

<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Check;

use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Exception\InvalidConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flags::class)]
#[CoversClass(InvalidConfiguration::class)]
final class FlagsTest extends TestCase
{
    public function testReadsFlagsWithAndWithoutValues(): void
    {
        $flags = Flags::parse(' ignore-tags ,, max-size:240.5,ignore-same ');

        self::assertSame(['ignore-tags', 'max-size:240.5', 'ignore-same'], $flags->all());
        self::assertSame('ignore-tags, max-size:240.5, ignore-same', $flags->toString());
        self::assertTrue($flags->has('ignore-tags'));
        self::assertFalse($flags->has('ignore'));
        self::assertNull($flags->value('ignore-tags'));
        self::assertNull($flags->value('missing'));
        self::assertSame('240.5', $flags->value('max-size'));
        self::assertTrue($flags->dismisses('tags'));
        self::assertTrue($flags->dismisses('same'));
        self::assertFalse($flags->dismisses('max-size'));
        self::assertSame([], Flags::parse('')->all());
        self::assertSame('', new Flags()->toString());
    }

    public function testTheLastValueOfANameWinsAndMovesLast(): void
    {
        self::assertSame(['ignore-same', 'max-size:300'], new Flags(['max-size:200', 'ignore-same', 'max-size:300'])->all());
    }

    public function testAddsAndRemovesFlags(): void
    {
        $flags = new Flags(['ignore-tags', 'max-size:200']);

        self::assertSame(['ignore-tags', 'max-size:200', 'ignore-same'], $flags->with('ignore-same')->all());
        self::assertSame(['ignore-tags', 'max-size:100'], $flags->with('max-size:100')->all());
        self::assertSame(['ignore-tags'], $flags->without('max-size')->all());
        self::assertSame(['max-size:200'], $flags->without('ignore-tags')->all());
        self::assertSame(['ignore-tags', 'max-size:200'], $flags->without('ignore')->all());
        self::assertSame(['ignore-tags', 'max-size:200'], $flags->all());
    }

    public function testHoldsThirtyTwoFlagsAtMost(): void
    {
        $names = array_map(static fn (int $index): string => 'f'.$index, range(1, 32));
        self::assertCount(32, new Flags($names)->all());

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A segment holds at most 32 flags.');
        new Flags([...$names, 'f33']);
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatIsNotAFlag(string $flag): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a flag such as "ignore-tags" or "max-size:240".', $flag));

        new Flags([$flag]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'upper case' => ['Ignore'];
        yield 'a digit first' => ['1gnore'];
        yield 'empty value' => ['max-size:'];
        yield 'space' => ['max size'];
        yield 'too long a name' => [str_repeat('a', 33)];
        yield 'too long a value' => ['max-size:'.str_repeat('1', 33)];
        yield 'trailing line' => ["ignore-tags\n"];
    }

    public function testAcceptsTheLongestNameAndValue(): void
    {
        $flag = str_repeat('a', 32).':'.str_repeat('Z', 32);

        self::assertSame([$flag], new Flags([$flag])->all());
    }
}

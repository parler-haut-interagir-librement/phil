<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Unit;

use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;
use Phil\CodeOfConduct\Identifier;
use Phil\CodeOfConduct\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidCodeOfConduct::class)]
final class TextAndIdentifierTest extends TestCase
{
    public function testAnIdentifierOfOneTo255BytesPasses(): void
    {
        self::assertSame('a', Identifier::check('a', 'A scope'));
        self::assertSame(str_repeat('a', 255), Identifier::check(str_repeat('a', 255), 'A scope'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield '256 bytes' => [str_repeat('a', 256)];
        yield 'a newline' => ["a\nb"];
        yield 'a null byte' => ["a\0"];
        yield 'a unit separator' => ["a\x1Fb"];
        yield 'a delete' => ["a\x7F"];
    }

    #[DataProvider('badIdentifiers')]
    public function testAnIdentifierOutOfBoundsIsRefused(string $value): void
    {
        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('A member is named by 1 to 255 bytes, without a control character.');

        Identifier::check($value, 'A member');
    }

    public function testATextIsTrimmedAndKeepsItsLinesAndTabs(): void
    {
        self::assertSame("one\n\ttwo", Text::check("  one\n\ttwo\r\n ", 10, 'rule'));
        self::assertSame(str_repeat('é', 10), Text::check(str_repeat('é', 10), 10, 'rule'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \n "];
        yield 'eleven characters' => [str_repeat('é', 11)];
        yield 'a null byte' => ["a\0b"];
        yield 'an escape' => ["a\x1Bb"];
        yield 'a vertical tab' => ["a\x0Bb"];
        yield 'a form feed' => ["a\x0Cb"];
        yield 'a delete' => ["a\x7Fb"];
        yield 'not UTF-8' => ["a\xFFb"];
    }

    #[DataProvider('badTexts')]
    public function testATextOutOfBoundsIsRefused(string $value): void
    {
        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('the rule');

        Text::check($value, 10, 'the rule');
    }
}

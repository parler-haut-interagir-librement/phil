<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader\Markdown;

use InvalidArgumentException;
use Phil\Markup\Exception\InputTooComplex;
use Phil\Markup\Exception\MarkupException;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\MarkupReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The budget of lines of the Markdown reader: a source of as many lines as the budget is read, one line more is refused
 * whole, before a list of its lines is built, whatever ends them.
 */
#[CoversClass(MarkdownReader::class)]
#[CoversClass(InputTooComplex::class)]
final class LineBudgetTest extends TestCase
{
    public function testTheDefaultBudgetReadsAMegabyteOfProse(): void
    {
        self::assertSame(100_000, MarkdownReader::DEFAULT_MAX_LINES);

        $prose = str_repeat(str_repeat("A line of a paragraph.\n", 5)."\n", 8_000);

        self::assertLessThan(MarkupReader::DEFAULT_MAX_BYTES, \strlen($prose));
        self::assertGreaterThan(40_000, substr_count($prose, "\n"));
        self::assertCount(8_000, new MarkdownReader()->read($prose)->document->blocks);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lineEnds(): iterable
    {
        yield 'line feed' => ["\n"];
        yield 'carriage return' => ["\r"];
        yield 'carriage return and line feed' => ["\r\n"];
    }

    #[DataProvider('lineEnds')]
    public function testASourceOfOneLineMoreThanItsBudgetIsRefused(string $end): void
    {
        $reader = new MarkdownReader(maxLines: 3);

        self::assertCount(1, $reader->read(implode($end, ['a', 'b', 'c']))->document->blocks);
        self::assertCount(1, $reader->read('a')->document->blocks);
        self::assertCount(1, new MarkdownReader(maxLines: 1)->read('a')->document->blocks);

        try {
            $reader->read(implode($end, ['a', 'b', 'c', 'd']));
            self::fail('A source of four lines was read under a budget of three.');
        } catch (InputTooComplex $inputTooComplex) {
            self::assertSame('The input holds more than 3 lines.', $inputTooComplex->getMessage());
            self::assertSame('lines', $inputTooComplex->budget);
            self::assertSame(3, $inputTooComplex->limit);
            self::assertInstanceOf(MarkupException::class, $inputTooComplex);
            self::assertInstanceOf(InvalidArgumentException::class, $inputTooComplex);
        }
    }

    public function testMixedLineEndsAreEachCountedOnce(): void
    {
        $reader = new MarkdownReader(maxLines: 4);

        self::assertCount(1, $reader->read("a\r\nb\rc\nd")->document->blocks);

        $this->expectException(InputTooComplex::class);

        $reader->read("a\r\nb\rc\nd\r");
    }

    public function testAHostileSourceUnderTheByteCeilingIsRefusedAtItsBudget(): void
    {
        $hostile = str_repeat("\n", MarkupReader::DEFAULT_MAX_BYTES - 1);

        self::assertCount(0, new MarkdownReader()->read(str_repeat("\n", MarkdownReader::DEFAULT_MAX_LINES - 1))->document->blocks);

        $this->expectException(InputTooComplex::class);
        $this->expectExceptionMessageIsOrContains('The input holds more than 100000 lines.');

        new MarkdownReader()->read($hostile);
    }
}

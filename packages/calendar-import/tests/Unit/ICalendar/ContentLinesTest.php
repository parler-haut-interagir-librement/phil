<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Unit\ICalendar;

use Phil\CalendarImport\Exception\LimitExceeded;
use Phil\CalendarImport\ICalendar\ContentLine;
use Phil\CalendarImport\ICalendar\ContentLines;
use Phil\CalendarImport\ICalendar\ReaderLimits;
use Phil\CalendarImport\ICalendar\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(ContentLines::class)]
#[CoversClass(ContentLine::class)]
#[CoversClass(Text::class)]
final class ContentLinesTest extends TestCase
{
    public function testALineIsANameParametersAndAValue(): void
    {
        $line = ContentLines::line('dtStart;tzid="Europe/Paris";X-LIST=a,"b;c",;X-EMPTY=:20261212T100000;x');

        self::assertSame('DTSTART', $line->name);
        self::assertSame(['TZID' => ['Europe/Paris'], 'X-LIST' => ['a', 'b;c', ''], 'X-EMPTY' => ['']], $line->parameters);
        self::assertSame('20261212T100000;x', $line->value);
        self::assertSame('Europe/Paris', $line->parameter('TZID'));
        self::assertNull($line->parameter('VALUE'));
        self::assertNull($line->error);
    }

    public function testAValueMayHoldColonsAndTabs(): void
    {
        $line = ContentLines::line("URL:https://example.org/a:b\tc");

        self::assertSame("https://example.org/a:b\tc", $line->value);
        self::assertSame([], $line->parameters);
        self::assertSame('', ContentLines::line('X-EMPTY:')->value);
    }

    public function testParameterValuesAreDecodedAsRfc6868Writes(): void
    {
        self::assertSame(["a\nb\"c^d^x"], ContentLines::line('X;P="a^nb^\'c^^d^x":v')->parameters['P']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformed(): iterable
    {
        yield 'no colon' => ['SUMMARY', '"SUMMARY" is not a name, parameters, a colon and a value.'];
        yield 'an empty name' => [':value', '":value" is not a name, parameters, a colon and a value.'];
        yield 'a name with a space' => ['SUM MARY:x', '"SUM MARY:x" is not a name, parameters, a colon and a value.'];
        yield 'a parameter without a value' => ['X;P:v', '"X;P:v" is not a name, parameters, a colon and a value.'];
        yield 'a parameter without a name' => ['X;=a:v', '"X;=a:v" is not a name, parameters, a colon and a value.'];
        yield 'a quote inside a bare value' => ['X;P=a"b":v', '"X;P=a"b":v" is not a name, parameters, a colon and a value.'];
        yield 'a quote not closed' => ['X;P="a:v', '"X;P="a:v" is not a name, parameters, a colon and a value.'];
        yield 'a control character in the value' => ["X:a\x01b", '"X:a?b" is not a name, parameters, a colon and a value.'];
        yield 'a control character in a quoted value' => ["X;P=\"a\x1Fb\":v", '"X;P="a?b":v" is not a name, parameters, a colon and a value.'];
        yield 'a long line, cut in its excerpt' => [str_repeat('é', 30), '"'.str_repeat('é', 20).'…" is not a name, parameters, a colon and a value.'];
        yield 'a delete character' => ["X:\x7F", '"X:?" is not a name, parameters, a colon and a value.'];
    }

    #[DataProvider('malformed')]
    public function testALineThatCannotBeReadKeepsItsError(string $text, string $error): void
    {
        $line = ContentLines::line($text);

        self::assertSame($error, $line->error);
        self::assertSame('', $line->name);
        self::assertSame([], $line->parameters);
        self::assertSame('', $line->value);
    }

    public function testTheExcerptOfALineThatCannotBeReadIsCutAtACharacter(): void
    {
        self::assertSame('"'.str_repeat('a', 39).'…" is not a name, parameters, a colon and a value.', ContentLines::line(str_repeat('a', 39).'éé')->error);
        self::assertSame('"'.str_repeat('a', 40).'…" is not a name, parameters, a colon and a value.', ContentLines::line(str_repeat('a', 41))->error);
    }

    public function testAParameterTwiceIsAnError(): void
    {
        self::assertSame('The parameter TZID of DTSTART appears twice.', ContentLines::line('DTSTART;TZID=a;Tzid=b:x')->error);
    }

    public function testLinesAreUnfoldedAndCutAtEveryLineBreak(): void
    {
        $lines = ContentLines::of("A:1\r\n 2\n\t3\r\nB:4\nC:5\rD:6\r\n\r\n", new ReaderLimits());

        self::assertSame(['A' => '123', 'B' => '4', 'C' => '5', 'D' => '6'], array_combine(array_map(static fn (ContentLine $line): string => $line->name, $lines), array_map(static fn (ContentLine $line): string => $line->value, $lines)));
    }

    public function testAByteOrderMarkIsSkippedOnlyAtTheStart(): void
    {
        self::assertSame('A', ContentLines::of("\u{FEFF}A:1", new ReaderLimits())[0]->name);
        self::assertNotNull(ContentLines::of("A:1\r\n\u{FEFF}B:2", new ReaderLimits())[1]->error);
    }

    public function testALineAtItsBoundIsReadAndOnePastIsRefused(): void
    {
        self::assertCount(1, ContentLines::of('X:'.str_repeat('a', 73), new ReaderLimits(lineBytes: 75)));

        $this->expectException(LimitExceeded::class);
        ContentLines::of('X:'.str_repeat('a', 74), new ReaderLimits(lineBytes: 75));
    }

    /**
     * Folding a line anywhere, with CRLF or LF and a space or a tab, as often as wanted, gives the line back: forty
     * thousand foldings of random lines, from a fixed seed.
     */
    public function testUnfoldingUndoesAnyFolding(): void
    {
        $random = new Randomizer(new Mt19937(20_261_121));
        $alphabet = ['a', 'Z', '0', ' ', ':', ';', ',', '"', '\\', 'é', '€', '🎲', "\t"];

        for ($run = 0; $run < 40_000; ++$run) {
            $value = '';

            for ($length = $random->getInt(0, 60); \strlen($value) < $length;) {
                $value .= $alphabet[$random->getInt(0, \count($alphabet) - 1)];
            }

            $line = 'X-TEST:'.$value;
            $folded = '';

            // Folded between any two bytes, even inside a character: the text is checked once unfolded.
            for ($at = 0, $bytes = \strlen($line); $at < $bytes; ++$at) {
                $folded .= $line[$at];

                if ($at < $bytes - 1 && 0 === $random->getInt(0, 7)) {
                    $folded .= ['', "\r"][$random->getInt(0, 1)]."\n".[' ', "\t"][$random->getInt(0, 1)];
                }
            }

            $lines = ContentLines::of($folded."\r\n", new ReaderLimits());

            self::assertCount(1, $lines);
            self::assertSame($value, $lines[0]->value, $folded);
        }
    }

    /**
     * Escaping a text as RFC 5545 writes it, then reading it, gives the text back: from a fixed seed.
     */
    public function testATextEscapedIsReadBack(): void
    {
        $random = new Randomizer(new Mt19937(5545));
        $alphabet = ['a', 'b', ' ', ',', ';', '\\', "\n", 'é'];

        for ($run = 0; $run < 5_000; ++$run) {
            $text = 'x';

            for ($length = $random->getInt(1, 30); mb_strlen($text) < $length;) {
                $text .= $alphabet[$random->getInt(0, \count($alphabet) - 1)];
            }

            $text .= 'y';
            $escaped = strtr($text, ['\\' => '\\\\', ';' => '\;', ',' => '\,', "\n" => '\n']);

            self::assertSame($text, Text::of($escaped));
            self::assertSame(array_values(array_unique(array_filter(array_map(trim(...), explode(',', $text)), static fn (string $item): bool => '' !== $item))), Text::list($escaped === $text ? $text : strtr($text, ['\\' => '\\\\', ';' => '\;', "\n" => '\n'])));
        }
    }

    public function testTextsAreUnescapedAndNeverReadAsHtml(): void
    {
        self::assertSame("a\nb\nc\\d;e,f\\xg", Text::of('a\nb\Nc\\\\d\;e\,f\xg'));
        self::assertSame('<b>&amp;</b>', Text::of(' <b>&amp;</b> '));
        self::assertNull(Text::of('  '));
        self::assertNull(Text::of(''));
        self::assertSame('a\\', Text::of('a\\'));
    }

    public function testAListIsCutAtTheCommasThatAreNotEscaped(): void
    {
        self::assertSame(['a', 'b,c', 'd\\', 'e'], Text::list(' a ,b\,c,,d\\\\, e ,a'));
        self::assertSame(['x\\'], Text::list('x\\'));
        self::assertSame([], Text::list(', ,'));
    }
}

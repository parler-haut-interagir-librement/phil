<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Check;

use Phil\Fonts\Font;
use Phil\Fonts\Name\NameId;
use Phil\Fonts\Name\NameRecord;
use Phil\Fonts\Name\Names;
use Phil\TranslationMemory\Check\AdvanceWidths;
use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\Checker;
use Phil\TranslationMemory\Check\CheckIssue;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Check\Glyphs;
use Phil\TranslationMemory\Check\MaxSize;
use Phil\TranslationMemory\Exception\InvalidConfiguration;
use Phil\TranslationMemory\LanguageTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdvanceWidths::class)]
#[CoversClass(MaxSize::class)]
#[CoversClass(Glyphs::class)]
#[CoversClass(Checker::class)]
#[CoversClass(InvalidConfiguration::class)]
final class SizeTest extends TestCase
{
    public function testMeasuresALineFromTheAdvancesOfItsCharacters(): void
    {
        $widths = new AdvanceWidths(1000, 16.0, [0x41 => 500, 0xE9 => 250], 1000);

        self::assertSame(8.0, $widths->width('A'));
        self::assertSame(4.0, $widths->width('é'));
        self::assertSame(28.0, $widths->width('AéZ'));
        self::assertSame(0.0, $widths->width(''));
        self::assertSame(0.0, new AdvanceWidths(16, 1000.0, [], 0)->width('xyz'));
        self::assertSame(1.0, new AdvanceWidths(16_384, 0.5, [0x41 => 32_768], 0)->width('A'));
    }

    /**
     * @return iterable<string, array{int, float, array<int, int>, int, string}>
     */
    public static function invalidWidths(): iterable
    {
        yield 'too few units' => [15, 10.0, [], 0, 'A font has 16 to 16384 units per em; got 15.'];
        yield 'too many units' => [16_385, 10.0, [], 0, 'A font has 16 to 16384 units per em; got 16385.'];
        yield 'no size' => [1000, 0.0, [], 0, 'A text is drawn above 0 and at most 1000 pixels high; got 0.'];
        yield 'too large' => [1000, 1000.5, [], 0, 'A text is drawn above 0 and at most 1000 pixels high; got 1000.5.'];
        yield 'negative fallback' => [1000, 10.0, [], -1, 'An advance is 0 or more font units.'];
        yield 'negative advance' => [1000, 10.0, [65 => 0, 66 => -1], 0, 'An advance is 0 or more font units.'];
    }

    /**
     * @param array<int, int> $advances
     */
    #[DataProvider('invalidWidths')]
    public function testRefusesWidthsOutOfRange(int $units, float $size, array $advances, int $fallback, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains($message);

        new AdvanceWidths($units, $size, $advances, $fallback);
    }

    public function testChecksTheWidestLineAgainstTheLimit(): void
    {
        $check = new MaxSize($this->tenPixels(), 50.0);

        self::assertSame('max-size', $check->name());
        self::assertSame([], $this->issues($check, 'Hello'));
        self::assertSame([], $this->issues($check, '<b>Hello</b><br>World<br/>Again'));
        self::assertSame([], $this->issues($check, "Hello\r\nWorld"));
        self::assertSame([['max-size', 'The target is 60 px wide; at most 50 px fit.']], $this->issues($check, 'Hello!'));
        self::assertSame([['max-size', 'The target is 70 px wide; at most 50 px fit.']], $this->issues($check, "Hi<BR >Goodbye\nYo"));
        self::assertSame([['max-size', 'The target is 60 px wide; at most 50 px fit.']], $this->issues($check, 'A &amp; B,'));
    }

    public function testTakesTheLimitOfTheSegmentsFlag(): void
    {
        self::assertSame([], $this->issues(new MaxSize($this->tenPixels()), 'A long, long target'));
        self::assertSame([['max-size', 'The target is 40 px wide; at most 30.5 px fit.']], $this->issues(new MaxSize($this->tenPixels()), 'Four', new Flags(['max-size:30.5'])));
        self::assertSame([], $this->issues(new MaxSize($this->tenPixels(), 30.0), 'Four', new Flags(['max-size:40'])));
        self::assertSame([['max-size', 'The target is 40 px wide; at most 30 px fit.']], $this->issues(new MaxSize($this->tenPixels(), 30.0), 'Four', new Flags(['max-size:wide'])));
        self::assertSame([['max-size', 'The target is 40 px wide; at most 30 px fit.']], $this->issues(new MaxSize($this->tenPixels(), 30.0), 'Four', new Flags(['max-size:0'])));
        self::assertSame([], $this->issues(new MaxSize($this->tenPixels(), 40.0), 'Four'));
        self::assertSame([['max-size', 'The target is 41.3 px wide; at most 40 px fit.']], $this->issues(new MaxSize(new AdvanceWidths(1000, 10.0, [], 1033), 40.0), 'Four'));
    }

    public function testRefusesALimitOfNothing(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The widest a target may be is above 0 pixels; got 0.');

        new MaxSize($this->tenPixels(), 0.0);
    }

    public function testChecksTheCharactersTheFontDraws(): void
    {
        $font = new Font(names: new Names([new NameRecord(3, 1, 0x409, NameId::Family->value, 'Gnome Sans')]), unicodeRanges: [[0x20, 0x7E], [0xC0, 0xFF], [0x2019, 0x2019]]);
        $check = new Glyphs($font);

        self::assertSame('glyphs', $check->name());
        self::assertSame([], $this->issues($check, "L\u{2019}épée <b>~</b>  \u{00A0}"));
        self::assertSame([['glyphs', 'The font “Gnome Sans” cannot draw “Œ” (U+0152), “✓” (U+2713).']], $this->issues($check, 'Œuvre ✓ Œ'));
        self::assertSame([['glyphs', 'The font of the template cannot draw “a” (U+0061).']], $this->issues(new Glyphs(new Font()), 'a'));
        self::assertSame([], $this->issues(new Glyphs(new Font(unicodeRanges: [[0x61, 0x61]])), 'a'));
        self::assertSame([], $this->issues(new Glyphs(new Font(unicodeRanges: [[0x41, 0x41], [0x61, 0x63], [0x100, 0x200]])), 'Aabc'));
    }

    public function testFindsEachRunOfTheCharacterMapWhereverItStands(): void
    {
        $letters = range('A', 'Q', 2);
        $check = new Glyphs(new Font(unicodeRanges: array_map(static fn (string $letter): array => [mb_ord($letter), mb_ord($letter)], $letters)));

        foreach (range('@', 'R') as $letter) {
            self::assertSame(\in_array($letter, $letters, true) ? [] : [['glyphs', \sprintf('The font of the template cannot draw “%s” (U+%04X).', $letter, mb_ord($letter))]], $this->issues($check, $letter), $letter);
        }
    }

    public function testAnAdvanceOfZeroIsAllowedAndDrawsNothing(): void
    {
        self::assertSame(0.0, new AdvanceWidths(1000, 10.0, [0x41 => 0], 0)->width('AA'));
    }

    public function testAnEmptyTargetFitsAnyLimit(): void
    {
        self::assertSame([], $this->issues(new MaxSize($this->tenPixels(), 0.5), ''));
    }

    public function testWritesTheLimitWithOneDecimal(): void
    {
        self::assertSame([['max-size', 'The target is 40 px wide; at most 12.3 px fit.']], $this->issues(new MaxSize($this->tenPixels(), 12.34), 'Four'));
    }

    public function testListsTenMissingCharactersAtMost(): void
    {
        $issue = $this->issues(new Glyphs(new Font()), 'abcdefghijkl')[0][1];

        self::assertStringEndsWith('“j” (U+006A).', $issue);
        self::assertSame(10, substr_count($issue, 'U+'));
    }

    public function testRunsTheChecksTheFlagsDoNotDismiss(): void
    {
        $checker = Checker::defaults();
        self::assertSame(['punctuation', 'spaces', 'tags', 'plurals', 'same'], $checker->names());
        self::assertSame(['punctuation', 'spaces', 'tags', 'plurals', 'same', 'max-size', 'glyphs'], Checker::defaults($this->tenPixels(), 10.0, new Font())->names());

        $checked = new Checked('<b>Done.</b>', 'Done', LanguageTag::of('en'), LanguageTag::of('fr'));
        self::assertSame(['punctuation', 'tags', 'tags'], array_map(static fn (CheckIssue $issue): string => $issue->check, $checker->check($checked)));

        $dismissed = new Checked('<b>Done.</b>', 'Done', LanguageTag::of('en'), LanguageTag::of('fr'), new Flags(['ignore-tags']));
        self::assertSame(['punctuation'], array_map(static fn (CheckIssue $issue): string => $issue->check, $checker->check($dismissed)));

        $sized = Checker::defaults($this->tenPixels(), 10.0);
        self::assertSame(['same', 'max-size'], array_map(static fn (CheckIssue $issue): string => $issue->check, $sized->check(new Checked('Done', 'Done', LanguageTag::of('en'), LanguageTag::of('fr')))));
        self::assertSame([], new Checker([])->check($checked));
    }

    private function tenPixels(): AdvanceWidths
    {
        return new AdvanceWidths(1000, 10.0, [], 1000);
    }

    /**
     * @return list<array{string, string}>
     */
    private function issues(MaxSize|Glyphs $check, string $target, Flags $flags = new Flags()): array
    {
        return array_map(static fn (CheckIssue $issue): array => [$issue->check, $issue->message], $check->check(new Checked('Source', $target, LanguageTag::of('en'), LanguageTag::of('fr'), $flags)));
    }
}

<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Layout;

use Closure;
use Phil\BrailleExport\Layout\Internal\Flow;
use Phil\BrailleExport\Tests\Unit\Fixture\Ascii;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flow::class)]
final class FlowTest extends TestCase
{
    public function testTheWordsAreTheCellsBetweenBlanksInOrder(): void
    {
        self::assertSame(['AB', 'C'], Ascii::rows(Flow::words(Ascii::braille(' AB  C '))));
        self::assertSame([], Flow::words(''));
    }

    public function testAnEmptyWordAddsNoBlank(): void
    {
        $words = [Ascii::braille('AB'), '', Ascii::braille('CD')];

        self::assertSame(['AB CD'], $this->rows($words, 12));
    }

    public function testNothingToLayOutGivesNoLine(): void
    {
        self::assertSame([], $this->rows([], 12));
        self::assertSame(0, Flow::count([], 12));
    }

    public function testEachLineAsksForItsWidthByItsNumber(): void
    {
        $asked = [];
        $width = static function (int $number) use (&$asked): int {
            $asked[] = $number;

            return 4;
        };

        $lines = array_map(Ascii::of(...), iterator_to_array(Flow::lines([Ascii::braille('AAAAAAAAAA')], $width), false));

        self::assertSame(['AAA-', 'AAA-', 'AAAA'], $lines);
        self::assertSame([0, 1, 2], $asked);
    }

    public function testTheRoomLeftByTheLineCountsInTheCutAfterAHyphen(): void
    {
        $rows = $this->rows([Ascii::braille('AB'), Ascii::braille('C-DEFG-HI-JKLMNOPQ')], 12);

        self::assertSame(['AB C-DEFG-', 'HI-JKLMNOPQ'], $rows);
    }

    public function testAHyphenOnTheLastCellOfTheRoomIsACut(): void
    {
        self::assertSame(['AA-BB-CC-DD-', 'EE'], $this->rows([Ascii::braille('AA-BB-CC-DD-EE')], 12));
    }

    public function testAHyphenOnTheCellAfterTheRoomIsNoCut(): void
    {
        self::assertSame(['AAAAAAAAAAA-', 'A-BB'], $this->rows([Ascii::braille('AAAAAAAAAAAA-BB')], 12));
    }

    public function testEachCutAfterAHyphenStartsWhereTheLastEnded(): void
    {
        $rows = $this->rows([Ascii::braille('AA-BB-CC-DD-EE-FF-GG-HH-II-JJ')], 12);

        self::assertSame(['AA-BB-CC-DD-', 'EE-FF-GG-HH-', 'II-JJ'], $rows);
    }

    public function testACutOfAnnouncingCellsStopsAtTheFirstOtherCell(): void
    {
        self::assertSame(['A##########-', '##########'], $this->rows([Ascii::braille('A'.str_repeat('#', 20))], 12));
    }

    public function testTheLinesOfAWordsCountAreThoseItYields(): void
    {
        self::assertSame(3, Flow::count([Ascii::braille('AAAAAAAAAA')], 4));
    }

    /**
     * @param list<string>          $words
     * @param int|Closure(int): int $width
     *
     * @return list<string>
     */
    private function rows(array $words, int|Closure $width): array
    {
        $width = $width instanceof Closure ? $width : static fn (): int => $width;

        return array_map(Ascii::of(...), iterator_to_array(Flow::lines($words, $width), false));
    }
}

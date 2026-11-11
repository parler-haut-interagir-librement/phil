<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Phil\FootnotesSidenotes\Exception\InvalidOptions;
use Phil\FootnotesSidenotes\NoteOptions;
use Phil\FootnotesSidenotes\Numbering;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoteOptions::class)]
#[CoversClass(InvalidOptions::class)]
final class NoteOptionsTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([7])]
    public function testItRefusesAChapterLevelOutOfRange(int $level): void
    {
        $this->expectException(InvalidOptions::class);
        $this->expectExceptionMessageIsOrContains('Invalid options: the chapter level goes from 1 to 6.');

        new NoteOptions(chapterLevel: $level);
    }

    #[TestWith([1])]
    #[TestWith([6])]
    public function testItAcceptsTheEndsOfTheRange(int $level): void
    {
        self::assertSame($level, new NoteOptions(chapterLevel: $level)->chapterLevel);
    }

    public function testTheDefaultsAreTheFirstLevelAndPageBreaks(): void
    {
        $options = new NoteOptions();

        self::assertSame(1, $options->chapterLevel);
        self::assertSame(['pageBreak'], $options->pageMarkers);
    }

    public function testTheNumberingOfALocaleWinsOverThatOfItsLanguage(): void
    {
        self::assertSame('*', new NoteOptions(locales: ['fr' => Numbering::lowerRoman(), 'fr-CA' => Numbering::symbols()])->numberingFor('fr-CA')->format(1));
    }

    public function testTheNumberingIsDecimalByDefault(): void
    {
        self::assertSame('12', new NoteOptions()->numberingFor('fr')->format(12));
        self::assertSame('12', new NoteOptions()->numberingFor(null)->format(12));
    }

    public function testALocaleFindsItsNumberingThenThatOfItsLanguage(): void
    {
        $options = new NoteOptions(numbering: Numbering::upperRoman(), locales: ['FR_ca' => Numbering::symbols(), 'de' => Numbering::lowerRoman()]);

        self::assertSame('*', $options->numberingFor('fr-CA')->format(1));
        self::assertSame('*', $options->numberingFor('fr_CA')->format(1));
        self::assertSame('i', $options->numberingFor('de-AT')->format(1));
        self::assertSame('I', $options->numberingFor('fr')->format(1));
        self::assertSame('I', $options->numberingFor(null)->format(1));
    }
}

<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Layout;

use InvalidArgumentException;
use Phil\BrailleExport\Layout\PageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageFormat::class)]
final class PageFormatTest extends TestCase
{
    public function testTheDefaultIsFortyCellsByTwentyFiveLines(): void
    {
        $format = new PageFormat();

        self::assertSame(40, $format->cellsPerLine);
        self::assertSame(25, $format->linesPerPage);
        self::assertTrue($format->braillePageNumbers);
        self::assertTrue($format->printPageNumbers);
        self::assertNull($format->pagesPerVolume);
        self::assertFalse($format->duplex);
        self::assertSame(10_000, $format->maxPages);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $small = new PageFormat(12, 3, pagesPerVolume: 1, maxPages: 1);
        $large = new PageFormat(200, 200);

        self::assertSame([12, 3, 1, 1], [$small->cellsPerLine, $small->linesPerPage, $small->pagesPerVolume, $small->maxPages]);
        self::assertSame([200, 200], [$large->cellsPerLine, $large->linesPerPage]);
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'too few cells' => [['cellsPerLine' => 11], 'A line holds from 12 to 200 cells.'];
        yield 'too many cells' => [['cellsPerLine' => 201], 'A line holds from 12 to 200 cells.'];
        yield 'too few lines' => [['linesPerPage' => 2], 'A page holds from 3 to 200 lines.'];
        yield 'too many lines' => [['linesPerPage' => 201], 'A page holds from 3 to 200 lines.'];
        yield 'an empty volume' => [['pagesPerVolume' => 0], 'A volume holds at least one page.'];
        yield 'no page' => [['maxPages' => 0], 'A document may fill at least one page.'];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('invalid')]
    public function testTheBoundsAreChecked(array $arguments, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PageFormat(...$arguments);
    }
}

<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Format;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Format::class)]
final class FormatTest extends TestCase
{
    public function testEachFormatHasItsExtensionAndMediaType(): void
    {
        self::assertSame(['csv', 'text/csv'], [Format::Csv->extension(), Format::Csv->mediaType()]);
        self::assertSame(['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], [Format::Xlsx->extension(), Format::Xlsx->mediaType()]);
        self::assertSame(['ods', 'application/vnd.oasis.opendocument.spreadsheet'], [Format::Ods->extension(), Format::Ods->mediaType()]);
    }

    /**
     * @return iterable<array{string, Format}>
     */
    public static function names(): iterable
    {
        yield ['csv', Format::Csv];
        yield ['.XLSX', Format::Xlsx];
        yield [' ods ', Format::Ods];
        yield ['report.final.xlsx', Format::Xlsx];
        yield ['/var/exports/answers.csv', Format::Csv];
        yield ['text/csv', Format::Csv];
        yield ['application/csv', Format::Csv];
        yield ['Text/CSV; charset=utf-8', Format::Csv];
        yield ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', Format::Xlsx];
        yield ['application/vnd.oasis.opendocument.spreadsheet', Format::Ods];
    }

    #[DataProvider('names')]
    public function testItDetectsAFormatFromAnExtensionANameOrAMediaType(string $name, Format $format): void
    {
        self::assertSame($format, Format::detect($name));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function unknown(): iterable
    {
        yield [''];
        yield ['xls'];
        yield ['report.pdf'];
        yield ['text/plain'];
        yield ['application/vnd.ms-excel'];
        yield ['folder/csv'];
    }

    #[DataProvider('unknown')]
    public function testItRefusesWhatNamesNoFormat(string $name): void
    {
        $this->expectException(InvalidSpreadsheet::class);
        $this->expectExceptionMessageIsOrContains('names none of the formats csv, xlsx and ods.');

        Format::detect($name);
    }

    public function testTheMessageIsCut(): void
    {
        try {
            Format::detect(str_repeat('a', 150)."\xFF");
            self::fail('The name is refused.');
        } catch (InvalidSpreadsheet $invalidSpreadsheet) {
            self::assertSame('"'.str_repeat('a', 100).'" names none of the formats csv, xlsx and ods.', $invalidSpreadsheet->getMessage());
        }
    }
}

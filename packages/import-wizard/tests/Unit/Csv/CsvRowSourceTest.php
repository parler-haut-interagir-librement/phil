<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Csv;

use Phil\ImportWizard\Csv\CsvDialect;
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Source\SourceLimits;
use Phil\ImportWizard\Source\SourceRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvRowSource::class)]
#[CoversClass(CsvDialect::class)]
#[CoversClass(SourceLimits::class)]
final class CsvRowSourceTest extends TestCase
{
    /**
     * @return list<array{int, list<string>}>
     */
    private function rows(CsvRowSource $source): array
    {
        $rows = [];

        foreach ($source->rows() as $row) {
            $rows[] = [$row->line, $row->cells];
        }

        return $rows;
    }

    public function testTheFirstRecordThatIsNotBlankIsTheHeader(): void
    {
        $source = CsvRowSource::fromString("\n , \n Email , Name\n\na@example.org,Ana\n");

        self::assertSame(['Email', 'Name'], $source->headers());
        self::assertSame([[4, ['']], [5, ['a@example.org', 'Ana']]], $this->rows($source));
    }

    public function testItDropsTheByteOrderMark(): void
    {
        self::assertSame(['Email'], CsvRowSource::fromString("\u{FEFF}Email\n")->headers());
    }

    public function testItDetectsTheDelimiterOfTheFirstLine(): void
    {
        self::assertSame(['a', 'b', 'c,d'], CsvRowSource::fromString("a;b;c,d\n1;2\n")->headers());
        self::assertSame(['a', 'b'], CsvRowSource::fromString("a\tb\n")->headers());
        self::assertSame(['a;b', 'c', 'd'], CsvRowSource::fromString("a;b,c,d\n")->headers());
    }

    public function testTheDialectGivenWinsOverTheDetection(): void
    {
        self::assertSame(['a;b', 'c;d;e'], CsvRowSource::fromString("a;b,c;d;e\n", new CsvDialect())->headers());
    }

    public function testDetectionReadsTheFirstLineOnly(): void
    {
        self::assertSame(',', CsvDialect::detect("a,b\nc;d;e;f")->delimiter);
        self::assertSame(';', CsvDialect::detect("a;b\rc,d,e,f")->delimiter);
        self::assertSame('|', CsvDialect::detect('a|b')->delimiter);
        self::assertSame(',', CsvDialect::detect('ab')->delimiter);
        self::assertSame(',', CsvDialect::detect('a;b,c')->delimiter);
        self::assertSame('Windows-1252', CsvDialect::detect('a', 'Windows-1252')->encoding);
        self::assertSame('UTF-8', CsvDialect::detect('a')->encoding);
    }

    public function testItConvertsAWindowsFileToUtf8(): void
    {
        $source = CsvRowSource::fromString("Pr\xE9nom\n\x80uro\n", new CsvDialect(encoding: 'Windows-1252'));

        self::assertSame(['Prénom'], $source->headers());
        self::assertSame([[2, ['€uro']]], $this->rows($source));
        self::assertSame(['Prénom'], CsvRowSource::fromString("Pr\xE9nom\n", new CsvDialect(encoding: 'ISO-8859-1'))->headers());
    }

    public function testAFileWithAByteOrderMarkIsUtf8WhateverTheDialectSays(): void
    {
        self::assertSame(['Prénom'], CsvRowSource::fromString("\u{FEFF}Prénom\n", new CsvDialect(encoding: 'Windows-1252'))->headers());
    }

    public function testItRefusesTextThatIsNotUtf8(): void
    {
        $this->expectException(SourceRefused::class);
        $this->expectExceptionMessageIsOrContains('The file is not UTF-8: save it as CSV UTF-8, or read it with the encoding it is written in.');

        CsvRowSource::fromString("Pr\xE9nom\n");
    }

    public function testItRefusesAFileWithoutHeader(): void
    {
        foreach (['', "\n\n", " , \n"] as $csv) {
            try {
                CsvRowSource::fromString($csv);
                self::fail('A file without header was read.');
            } catch (SourceRefused $exception) {
                self::assertSame('The file has no header.', $exception->getMessage());
            }
        }
    }

    public function testItRefusesAFileLargerThanItsLimit(): void
    {
        self::assertSame(['abcd'], CsvRowSource::fromString('abcd', limits: new SourceLimits(maxBytes: 4))->headers());

        $this->expectException(SourceRefused::class);
        $this->expectExceptionMessageIsOrContains('The file is larger than 4 bytes.');

        CsvRowSource::fromString('abcde', limits: new SourceLimits(maxBytes: 4));
    }

    public function testItAppliesTheLimitsToTheRecords(): void
    {
        $this->expectExceptionMessageIsOrContains('The file has more than 1 rows.');

        CsvRowSource::fromString("h\n1\n2", limits: new SourceLimits(maxRows: 1));
    }

    public function testItReadsAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        self::assertIsString($path);
        file_put_contents($path, "Email\na@example.org\n");

        try {
            $source = CsvRowSource::fromFile($path);
            self::assertSame(['Email'], $source->headers());
            self::assertSame([[2, ['a@example.org']]], $this->rows($source));
            self::assertSame(['Email'], CsvRowSource::fromFile($path, new CsvDialect(';'))->headers());

            $this->expectExceptionMessageIsOrContains('The file is larger than 6 bytes.');
            CsvRowSource::fromFile($path, limits: new SourceLimits(maxBytes: 6));
        } finally {
            unlink($path);
        }
    }

    public function testItReadsOnlyOneByteMoreThanTheLimit(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        self::assertIsString($path);
        file_put_contents($path, 'abcdef');

        try {
            self::assertSame(['abcdef'], CsvRowSource::fromFile($path, limits: new SourceLimits(maxBytes: 6))->headers());
            $this->expectExceptionMessageIsOrContains('The file is larger than 5 bytes.');
            CsvRowSource::fromFile($path, limits: new SourceLimits(maxBytes: 5));
        } finally {
            unlink($path);
        }
    }

    public function testItRefusesAFileItCannotRead(): void
    {
        foreach ([sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(4)).'.csv', sys_get_temp_dir()] as $path) {
            try {
                CsvRowSource::fromFile($path);
                self::fail('A file that cannot be read was read.');
            } catch (SourceRefused $exception) {
                self::assertSame('The file cannot be read.', $exception->getMessage());
            }
        }
    }

    public function testItRefusesAFileItHasNoRightToRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        self::assertIsString($path);
        file_put_contents($path, "Email\n");
        chmod($path, 0o000);

        try {
            if (is_readable($path)) {
                self::markTestSkipped('This user reads every file, whatever its rights.');
            }

            $this->expectExceptionMessageIsOrContains('The file cannot be read.');
            CsvRowSource::fromFile($path);
        } finally {
            chmod($path, 0o600);
            unlink($path);
        }
    }

    public function testTheRowsAreSourceRows(): void
    {
        foreach (CsvRowSource::fromString("a\n\"x\" y\n")->rows() as $row) {
            self::assertInstanceOf(SourceRow::class, $row);
            self::assertTrue($row->malformed);
        }
    }

    public function testItRefusesADialectItCannotRead(): void
    {
        foreach ([[':', '"', 'UTF-8'], [',', '`', 'UTF-8'], [',', '"', 'UTF-16']] as [$delimiter, $enclosure, $encoding]) {
            try {
                new CsvDialect($delimiter, $enclosure, $encoding);
                self::fail('A dialect that cannot be read was accepted.');
            } catch (InvalidProfile) {
            }
        }

        self::assertSame("'", new CsvDialect(enclosure: "'")->enclosure);
        self::assertTrue(new CsvDialect()->byteOrderMark);
        self::assertTrue(new CsvDialect()->guardFormulas);
        self::assertSame('"', new CsvDialect()->enclosure);
    }

    public function testItRefusesLimitsBelowOne(): void
    {
        foreach ([[0, 1, 1], [1, 0, 1], [1, 1, 0]] as [$bytes, $rows, $columns]) {
            try {
                new SourceLimits($bytes, $rows, $columns);
                self::fail('A limit below one was accepted.');
            } catch (InvalidProfile $exception) {
                self::assertSame('The limits of a source must be positive.', $exception->getMessage());
            }
        }

        $limits = new SourceLimits(1, 1, 1);
        self::assertSame([1, 1, 1], [$limits->maxBytes, $limits->maxRows, $limits->maxColumns]);
        $defaults = new SourceLimits();
        self::assertSame([20_971_520, 100_000, 256], [$defaults->maxBytes, $defaults->maxRows, $defaults->maxColumns]);
    }

    public function testTheDelimiterIsCountedOnTheWholeFirstLine(): void
    {
        self::assertSame(';', CsvDialect::detect(";;,x\nignored")->delimiter);
    }
}

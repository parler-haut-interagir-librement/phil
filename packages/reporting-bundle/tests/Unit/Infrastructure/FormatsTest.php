<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use Phil\ReportingBundle\Application\Contract\ReportFormat;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Infrastructure\Format\CsvFormat;
use Phil\ReportingBundle\Infrastructure\Format\DelimitedWriter;
use Phil\ReportingBundle\Infrastructure\Format\JsonFormat;
use Phil\ReportingBundle\Infrastructure\Format\TsvFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvFormat::class)]
#[CoversClass(TsvFormat::class)]
#[CoversClass(JsonFormat::class)]
#[CoversClass(DelimitedWriter::class)]
final class FormatsTest extends TestCase
{
    private const array ROWS = [
        ['=1+1', 1, 2.5, true, null],
        ['+33', -4, 0.0, false, ''],
        ['-x', 0, 1.0E-7, true, "@me\tyou"],
        ["\tx", 7, 3.0, false, "\rx"],
        ['a "b", c', 8, 4.0, false, "two\nlines"],
        ['é', 9, 5.0, false, 'plain'],
    ];

    public function testCsv(): void
    {
        $format = new CsvFormat();

        self::assertSame(['csv', 'text/csv; charset=UTF-8', 'csv'], [CsvFormat::key(), $format->mediaType(), $format->extension()]);
        self::assertSame(
            "Name,Count,Ratio,Paid,Note\r\n"
            ."'=1+1,1,2.5,true,\r\n"
            ."'+33,-4,0,false,\r\n"
            ."'-x,0,1.0E-7,true,\"'@me\tyou\"\r\n"
            ."\"'\tx\",7,3,false,\"'\rx\"\r\n"
            ."\"a \"\"b\"\", c\",8,4,false,\"two\nlines\"\r\n"
            ."é,9,5,false,plain\r\n",
            $this->write($format),
        );
    }

    public function testTsv(): void
    {
        $format = new TsvFormat();

        self::assertSame(['tsv', 'text/tab-separated-values; charset=UTF-8', 'tsv'], [TsvFormat::key(), $format->mediaType(), $format->extension()]);
        self::assertSame("Name\tCount\tRatio\tPaid\tNote\r\n'=1+1\t1\t2.5\ttrue\t\r\n", explode("'+33", $this->write($format))[0]);
    }

    public function testJson(): void
    {
        $format = new JsonFormat();

        self::assertSame(['json', 'application/json', 'json'], [JsonFormat::key(), $format->mediaType(), $format->extension()]);
        self::assertSame(
            "[\n"
            .'{"Name":"=1+1","Count":1,"Ratio":2.5,"Paid":true,"Note":null},'."\n"
            .'{"Name":"+33","Count":-4,"Ratio":0.0,"Paid":false,"Note":""},'."\n"
            .'{"Name":"-x","Count":0,"Ratio":1.0e-7,"Paid":true,"Note":"@me\tyou"},'."\n"
            .'{"Name":"\tx","Count":7,"Ratio":3.0,"Paid":false,"Note":"\rx"},'."\n"
            .'{"Name":"a \"b\", c","Count":8,"Ratio":4.0,"Paid":false,"Note":"two\nlines"},'."\n"
            .'{"Name":"é","Count":9,"Ratio":5.0,"Paid":false,"Note":"plain"}'."\n"
            ."]\n",
            $this->write($format),
        );
        self::assertSame("[]\n", $this->write($format, []));
        self::assertSame("[\n{\"a/b\":\"c/d\"}\n]\n", $this->write($format, [['c/d']], ['a/b']));
    }

    /**
     * @param list<list<bool|float|int|string|null>> $rows
     * @param list<string>                           $columns
     */
    private function write(ReportFormat $format, array $rows = self::ROWS, array $columns = ['Name', 'Count', 'Ratio', 'Paid', 'Note']): string
    {
        $stream = ReportStream::temporary();
        $format->write($stream, $columns, (static fn (): iterable => yield from $rows)());
        $stream->rewind();

        return $stream->contents();
    }
}

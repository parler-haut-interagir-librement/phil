<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Ods;

use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Internal\Archive;
use Phil\Spreadsheet\Internal\BudgetFilter;
use Phil\Spreadsheet\Internal\ByteBudget;
use Phil\Spreadsheet\Ods\OdsContentParser;
use Phil\Spreadsheet\Ods\OdsReader;
use Phil\Spreadsheet\Ods\OdsTableParser;
use Phil\Spreadsheet\Ods\OdsText;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Tests\Support\Workbooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The corners of the content of an OpenDocument spreadsheet: what sits between the tables, the cells and the
 * paragraphs in files other programs write.
 */
#[CoversClass(OdsReader::class)]
#[CoversClass(OdsContentParser::class)]
#[CoversClass(OdsTableParser::class)]
#[CoversClass(OdsText::class)]
#[CoversClass(Archive::class)]
#[CoversClass(BudgetFilter::class)]
#[CoversClass(ByteBudget::class)]
final class OdsEdgesTest extends TestCase
{
    use TemporaryFiles;

    private function ods(string $tables): string
    {
        return $this->zip('book.ods', ['content.xml' => Workbooks::odsContent($tables)]);
    }

    /**
     * @return array<int, list<string>>
     */
    private function read(string $rows): array
    {
        return Workbooks::dump(new OdsReader()->rows($this->ods('<table:table table:name="T">'.$rows.'</table:table>')));
    }

    private function cell(string $content, string $type = 'string'): string
    {
        return '<table:table-cell office:value-type="'.$type.'">'.$content.'</table:table-cell>';
    }

    public function testATableWithoutANameHasAnEmptyName(): void
    {
        self::assertSame([''], new OdsReader()->sheetNames($this->ods('<table:table/>')));
    }

    public function testASheetNameInAMessageIsCutToAHundredCharacters(): void
    {
        try {
            iterator_to_array(new OdsReader()->rows($this->ods('<table:table table:name="T"/>'), 'a'.str_repeat('é', 150)));
            self::fail('The spreadsheet has no such sheet.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The spreadsheet has no sheet "a'.str_repeat('é', 99).'".', $readRefused->getMessage());
        }
    }

    public function testWhatIsSkippedEndsWithItsOwnElement(): void
    {
        $path = $this->ods('<table:table table:name="One"><table:table-row><table:table-cell/></table:table-row><table:table table:name="Inner"/></table:table><table:table table:name="Two"/>');

        self::assertSame(['One', 'Two'], new OdsReader()->sheetNames($path));
    }

    public function testAnElementOfTheTableNamespaceBetweenTheTablesIsNotATable(): void
    {
        $path = $this->ods('<table:calculation-settings/><table:table table:name="One"/><table:content-validations/>');

        self::assertSame(['One'], new OdsReader()->sheetNames($path));
    }

    public function testTextOutsideAParagraphIsNotTheTextOfACell(): void
    {
        self::assertSame([1 => ['text:a']], $this->read('<table:table-row>'.$this->cell('stray<text:p>a</text:p>').'</table:table-row>'));
        self::assertSame([1 => ['text:ab']], $this->read('<table:table-row>'.$this->cell('<text:p>a<!-- hidden -->b</text:p>').'</table:table-row>'));
    }

    public function testAnElementOfTheTableNamespaceInARowIsNotACell(): void
    {
        self::assertSame([1 => ['number:5']], $this->read('<table:table-row><table:table-column></table:table-column><table:table-cell office:value-type="float" office:value="5"/></table:table-row>'));
    }

    public function testOnlyTheDrawingsAnnotationsAndTablesOfACellAreSkipped(): void
    {
        self::assertSame([1 => ['text:z']], $this->read('<table:table-row>'.$this->cell('<office:foo><text:p>z</text:p></office:foo>').'</table:table-row>'));
        self::assertSame([1 => ['text:z']], $this->read('<table:table-row>'.$this->cell('<table:foo><text:p>z</text:p></table:foo>').'</table:table-row>'));
        self::assertSame([1 => ['text:out']], $this->read('<table:table-row>'.$this->cell('<text:p>out</text:p><table:table table:name="I"><table:table-row><table:table-cell office:value-type="string"><text:p>in</text:p></table:table-cell></table:table-row></table:table>').'</table:table-row>'));
    }

    public function testNestedParagraphsAreOneText(): void
    {
        self::assertSame([1 => ['text:abc']], $this->read('<table:table-row>'.$this->cell('<text:p>a<text:p>b</text:p>c</text:p>').'</table:table-row>'));
    }

    public function testASpaceRunIsCutToTheMostACellHolds(): void
    {
        $rows = $this->read('<table:table-row>'.$this->cell('<text:p>a<text:s text:c="40000"/>b</text:p>').'</table:table-row>');

        self::assertSame([1 => ['text:a'.str_repeat(' ', 32767).'b']], $rows);
    }

    public function testAnElementNamedAsACellOfAnotherNamespaceDoesNotEndTheCell(): void
    {
        self::assertSame([1 => ['text:amb']], $this->read('<table:table-row>'.$this->cell('<text:p>a<x:table-cell xmlns:x="urn:x">m</x:table-cell>b</text:p>').'</table:table-row>'));
    }

    public function testACellInACellIsPlacedOnce(): void
    {
        self::assertSame([1 => ['text:a']], $this->read('<table:table-row><table:table-cell office:value-type="string"><table:table-cell><text:p>a</text:p></table:table-cell></table:table-cell></table:table-row>'));
    }

    public function testAnElementNamedAsARowOfAnotherNamespaceDoesNotEndTheRow(): void
    {
        self::assertSame([1 => ['text:a']], $this->read('<table:table-row><x:table-row xmlns:x="urn:x"></x:table-row>'.$this->cell('<text:p>a</text:p>').'</table:table-row>'));
    }

    public function testARowInARowDoesNotCountTwice(): void
    {
        self::assertSame([1 => ['text:a'], 2 => ['text:b']], $this->read(
            '<table:table-row><table:table-row>'.$this->cell('<text:p>a</text:p>').'</table:table-row></table:table-row>'
            .'<table:table-row>'.$this->cell('<text:p>b</text:p>').'</table:table-row>',
        ));
    }

    public function testARepeatIsDigitsFromOne(): void
    {
        foreach (['1&#10;', 'x5', '12345678901', '0'] as $repeat) {
            try {
                $this->read('<table:table-row table:number-rows-repeated="'.$repeat.'"/>');
                self::fail('The repeat is refused.');
            } catch (ReadRefused $readRefused) {
                self::assertSame('The part "content.xml" repeats a row or a cell a number of times that is not one.', $readRefused->getMessage());
            }
        }
    }

    public function testAnElementNamedAsARowOfAnotherNamespaceIsNotARow(): void
    {
        self::assertSame([1 => ['text:a']], $this->read('<x:table-row xmlns:x="urn:x"/><table:table-row>'.$this->cell('<text:p>a</text:p>').'</table:table-row>'));
    }

    public function testATableEndsWhereItsElementEnds(): void
    {
        $path = $this->ods('<table:table table:name="One"><table:table-row>'.$this->cell('<text:p>a</text:p>').'</table:table-row></table:table><table:table table:name="Two"><table:table-row>'.$this->cell('<text:p>b</text:p>').'</table:table-row></table:table>');

        self::assertSame([1 => ['text:a']], Workbooks::dump(new OdsReader()->rows($path, 'One')));
        self::assertSame([1 => ['text:a']], Workbooks::dump(new OdsReader()->rows($path)));
        self::assertSame([1 => ['text:b']], Workbooks::dump(new OdsReader()->rows($path, 'Two')));
    }

    public function testATimeCellWithoutAValueIsEmpty(): void
    {
        self::assertSame([1 => ['empty:', 'text:a']], $this->read('<table:table-row><table:table-cell office:value-type="time"/>'.$this->cell('<text:p>a</text:p>').'</table:table-row>'));
        self::assertSame([], $this->read('<table:table-row><table:table-cell office:value-type="time"/></table:table-row>'));
    }

    public function testTheCharactersAnXmlTextRefusesAreReplaced(): void
    {
        self::assertSame('<text:p>'."\u{FFFD}".'</text:p>', OdsText::paragraphs("\x01"));
        self::assertSame('<text:p>'."\u{FFFD}".'</text:p>', OdsText::paragraphs("\u{FFFE}"));
        self::assertSame('<text:p>'."\u{FFFD}".'</text:p>', OdsText::paragraphs("\u{FFFF}"));
        self::assertSame('<text:p>a &amp; b</text:p>', OdsText::paragraphs('a & b'));
    }

    public function testAPartThatLiesAboutItsSizeIsRefusedWhileItIsRead(): void
    {
        $path = $this->zip('lie.zip', ['x' => str_repeat('a', 10_000)]);
        $bytes = (string) file_get_contents($path);
        $central = strpos($bytes, "PK\x01\x02");
        self::assertIsInt($central);
        $bytes = substr_replace($bytes, pack('V', 100), 22, 4);
        $bytes = substr_replace($bytes, pack('V', 100), $central + 24, 4);
        file_put_contents($path, $bytes);

        $archive = Archive::open($path, new ReadLimits());

        try {
            $archive->text('x', 200);
            self::fail('The part is bigger than it says.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The size of the part "x" goes over the limit of 200.', $readRefused->getMessage());
        }
    }

    public function testAPartThatDeclaresMoreThanTheLimitIsRefusedBeforeItIsRead(): void
    {
        $archive = Archive::open($this->zip('big.zip', ['x' => str_repeat('a', 500)]), new ReadLimits(maxPartBytes: 100));

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The size of the part "x" goes over the limit of 100.');
        $archive->xml('x');
    }
}

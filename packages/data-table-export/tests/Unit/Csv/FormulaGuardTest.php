<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Tests\Unit\Csv;

use Phil\DataTableExport\Csv\FormulaGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormulaGuard::class)]
final class FormulaGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'a formula' => ['=SUM(A1:A2)', "'=SUM(A1:A2)"];
        yield 'a plus' => ['+cmd|"/C calc"!A0', "'+cmd|\"/C calc\"!A0"];
        yield 'a minus' => ['-2+3', "'-2+3"];
        yield 'an at sign' => ['@SUM(1)', "'@SUM(1)"];
        yield 'a tab' => ["\t=1", "'\t=1"];
        yield 'a carriage return' => ["\r=1", "'\r=1"];
        yield 'after spaces' => ['  =1', "'  =1"];
        yield 'plain text' => ['Dune', 'Dune'];
        yield 'empty' => ['', ''];
        yield 'spaces alone' => ['   ', '   '];
        yield 'a sign inside' => ['a=b', 'a=b'];
        yield 'a negative integer' => ['-12', '-12'];
        yield 'a positive decimal' => ['+3.5', '+3.5'];
        yield 'a decimal comma' => ['-3,5', '-3,5'];
        yield 'a fraction' => ['-.5', '-.5'];
        yield 'an exponent' => ['-1e5', '-1e5'];
        yield 'a negative exponent' => ['1.5E-3', '1.5E-3'];
        yield 'a minus alone' => ['-', "'-"];
        yield 'a number then text' => ['-12 apples', "'-12 apples"];
        yield 'text then a number' => ['-x12', "'-x12"];
        yield 'a number after spaces' => [' -12', "' -12"];
        yield 'two points' => ['-1.2.3', "'-1.2.3"];
    }

    #[DataProvider('texts')]
    public function testATextThatWouldRunIsKeptAsText(string $text, string $written): void
    {
        self::assertSame($written, FormulaGuard::protect($text));
    }
}

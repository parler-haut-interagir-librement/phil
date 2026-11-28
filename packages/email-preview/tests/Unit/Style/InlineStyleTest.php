<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Style;

use Phil\EmailPreview\Style\InlineStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(InlineStyle::class)]
final class InlineStyleTest extends TestCase
{
    public function testTheDeclarationsAreReadByPropertyInLowercaseAndTheLastWins(): void
    {
        self::assertSame(
            ['width' => '600px', 'background' => 'url(https://a.test/x.png)', 'color' => 'blue'],
            InlineStyle::declarations(' WIDTH : 500px; width:600px ; background: url(https://a.test/x.png); color: red; color:blue;; : 3px; height: ; nothing'),
        );
        self::assertSame([], InlineStyle::declarations(''));
    }

    public function testADeclarationWithoutColonIsSkippedAndTheNextOnesAreRead(): void
    {
        self::assertSame(['width' => '1px', 'height' => '1px'], InlineStyle::declarations('display none; width: 1px; height: 1px'));
    }

    /**
     * @return iterable<string, array{string, ?float}>
     */
    public static function lengths(): iterable
    {
        yield 'a number' => ['600', 600.0];
        yield 'pixels' => [' 600px ', 600.0];
        yield 'uppercase pixels' => ['600PX', 600.0];
        yield 'important' => ['600px !important', 600.0];
        yield 'important without space' => ['1px!IMPORTANT', 1.0];
        yield 'a decimal' => ['0.5px', 0.5];
        yield 'zero' => ['0', 0.0];
        yield 'a percentage' => ['100%', null];
        yield 'another unit' => ['40em', null];
        yield 'a word' => ['auto', null];
        yield 'empty' => ['', null];
        yield 'only the unit' => ['px', null];
    }

    #[DataProvider('lengths')]
    public function testALengthIsReadInPixels(string $value, ?float $pixels): void
    {
        self::assertSame($pixels, InlineStyle::pixels($value));
    }
}

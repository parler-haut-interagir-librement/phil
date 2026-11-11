<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Quote;

use Phil\Typography\Locale;
use Phil\Typography\Quote\QuoteStyle;
use Phil\Typography\Quote\QuoteStyles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuoteStyles::class)]
#[CoversClass(QuoteStyle::class)]
final class QuoteStylesTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function styles(): iterable
    {
        yield ['en', '“”‘’'];
        yield ['en_GB', '“”‘’'];
        yield ['fr', '«»“”'];
        yield ['fr_CA', '«»“”'];
        yield ['fr_CH', '«»‹›'];
        yield ['de_DE', '„“‚‘'];
        yield ['de_CH', '«»‹›'];
        yield ['pt_BR', '“”‘’'];
        yield ['pt_PT', '«»“”'];
        yield ['es', '«»“”'];
        yield ['es_MX', '“”‘’'];
        yield ['pl', '„”«»'];
        yield ['ru', '«»„“'];
        yield ['ja', '「」『』'];
        yield ['zh_Hant_TW', '「」『』'];
        yield ['zh_Hans_CN', '“”‘’'];
        yield ['sv', '””’’'];
        yield ['xx', '“”‘’'];
    }

    #[DataProvider('styles')]
    public function testTheMarksOfALocale(string $locale, string $marks): void
    {
        $style = QuoteStyles::for(Locale::parse($locale));

        self::assertSame($marks, $style->open.$style->close.$style->innerOpen.$style->innerClose);
    }

    public function testEveryStyleHasFourMarks(): void
    {
        foreach (QuoteStyles::STYLES as $locale => $marks) {
            self::assertSame((string) Locale::parse($locale), $locale);
            self::assertCount(4, $marks, $locale);

            foreach ($marks as $mark) {
                self::assertSame(1, mb_strlen($mark), $locale);
            }
        }
    }
}

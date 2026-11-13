<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit;

use Phil\Citations\Locator;
use Phil\Citations\Terms;
use Phil\Citations\Tests\Unit\Style\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Terms::class)]
final class TermsTest extends TestCase
{
    public function testTheTypographyOfEachLanguage(): void
    {
        $english = Terms::english();
        $french = Terms::french();

        self::assertTrue($english->serialComma);
        self::assertTrue($english->punctuationInsideQuotes);
        self::assertTrue($english->capitalizeAbbreviations);
        self::assertFalse($french->serialComma);
        self::assertFalse($french->punctuationInsideQuotes);
        self::assertFalse($french->capitalizeAbbreviations);
    }

    public function testEveryLocatorHasItsSingularAndItsPlural(): void
    {
        $english = Terms::english();
        $french = Terms::french();
        $expected = [
            'page' => [['p.', 'pp.'], ['p.', 'p.']],
            'chapter' => [['chap.', 'chaps.'], ['chap.', 'chap.']],
            'section' => [['sec.', 'secs.'], ['sect.', 'sect.']],
            'paragraph' => [['para.', 'paras.'], ['paragr.', 'paragr.']],
            'volume' => [['vol.', 'vols.'], ['vol.', 'vol.']],
            'figure' => [['fig.', 'figs.'], ['fig.', 'fig.']],
            'line' => [['l.', 'll.'], ['l.', 'l.']],
            'note' => [['n.', 'nn.'], ['n.', 'n.']],
        ];

        foreach (Locator::cases() as $locator) {
            self::assertSame($expected[$locator->value][0], [$english->locatorLabel($locator, '1'), $english->locatorLabel($locator, '1-2')], $locator->value);
            self::assertSame($expected[$locator->value][1], [$french->locatorLabel($locator, '1'), $french->locatorLabel($locator, '1-2')], $locator->value);
        }
    }

    public function testOnlyTheMarksOfARangeOrAListMakeAPlural(): void
    {
        $terms = Terms::english();

        foreach (['1-2', '1–2', '1,2', '1 & 2'] as $value) {
            self::assertSame('pp.', $terms->locatorLabel(Locator::Page, $value), $value);
        }

        // An em dash shares two of the three bytes of an en dash.
        self::assertSame('p.', $terms->locatorLabel(Locator::Page, '1—2'));
    }

    public function testTheWholeNumberComesBeforeItsLastDigits(): void
    {
        $terms = Fixtures::englishWith(ordinalSuffixes: ['=12' => 'whole', '12' => 'two digits', '2' => 'one digit', 'default' => 'other']);

        self::assertSame('12whole', $terms->ordinal(12));
        self::assertSame('112two digits', $terms->ordinal(112));
        self::assertSame('22one digit', $terms->ordinal(22));
        self::assertSame('5other', $terms->ordinal(5));
    }
}

<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Style;

use Phil\Citations\Cite;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\Reference;
use Phil\Citations\Style\AbstractStyle;
use Phil\Citations\Terms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractStyle::class)]
final class AbstractStyleTest extends TestCase
{
    private function style(): StyleHarness
    {
        return new StyleHarness();
    }

    public function testALiteralNameIsWrittenAsItIs(): void
    {
        $name = new Name(null, 'Jo', null, null, 'Jr.', 'Acme Corp');

        self::assertSame('Acme Corp', $this->style()->writeNatural($name));
        self::assertSame('Acme Corp', $this->style()->writeInverted($name));
    }

    public function testNamesWithoutAGivenNameOrAFamily(): void
    {
        $style = $this->style();

        self::assertSame('Al Jr.', $style->writeNatural(new Name('', 'Al', suffix: 'Jr.')));
        self::assertSame('de Gaulle', $style->writeNatural(new Name('Gaulle', droppingParticle: 'de')));
        self::assertSame('Charles de Gaulle', $style->writeNatural(new Name('Gaulle', 'Charles', 'de')));
        self::assertSame('C. de Gaulle', $style->writeNatural(new Name('Gaulle', 'Charles', 'de'), true));
        self::assertSame('Gaulle, Charles de, Jr.', $style->writeInverted(new Name('Gaulle', 'Charles', 'de', suffix: 'Jr.')));
        self::assertSame('Gaulle, C. de', $style->writeInverted(new Name('Gaulle', 'Charles', 'de'), true));
        self::assertSame('Gaulle', $style->writeInverted(new Name('Gaulle')));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function quotes(): iterable
    {
        yield 'a period after a question' => ['Why?', '.', '“Why?”', "«\u{a0}Why?\u{a0}»"];
        yield 'a period after an exclamation' => ['Wow!', '.', '“Wow!”', "«\u{a0}Wow!\u{a0}»"];
        yield 'a period after a statement' => ['Why', '.', '“Why.”', "«\u{a0}Why\u{a0}»."];
        yield 'a comma after a question' => ['Why?', ',', '“Why?,”', "«\u{a0}Why?\u{a0}»,"];
        yield 'a period after a period' => ['Fine.', '.', '“Fine..”', "«\u{a0}Fine.\u{a0}»."];
        yield 'nothing after' => ['Why', '', '“Why”', "«\u{a0}Why\u{a0}»"];
    }

    #[DataProvider('quotes')]
    public function testQuotedTitles(string $text, string $after, string $english, string $french): void
    {
        $style = $this->style();

        self::assertSame($english, $style->writeQuoted($text, Terms::english(), $after));
        self::assertSame($french, $style->writeQuoted($text, Terms::french(), $after));
    }

    public function testTheTitles(): void
    {
        $style = $this->style();
        $terms = Terms::english();

        self::assertSame('', $style->writeTitle(new Reference('a', ItemType::Book), $terms));
        self::assertSame('', $style->writeTitle(new Reference('a', ItemType::Chapter), $terms));
        self::assertSame('Being and Time', $style->writeShortTitle(new Reference('a', ItemType::Book, fields: ['title' => 'Being and Time : A Translation'])));
        self::assertSame('Short', $style->writeShortTitle(new Reference('a', ItemType::Book, fields: ['title' => 'A Long Title', 'title-short' => 'Short'])));
        self::assertSame('Whole', $style->writeShortTitle(new Reference('a', ItemType::Book, fields: ['title' => 'Whole'])));
        self::assertSame('', $style->writeShortTitle(new Reference('a', ItemType::Book)));
    }

    public function testTheLocator(): void
    {
        $style = $this->style();
        $terms = Terms::english();

        self::assertSame('p. 12', $style->writeLocator(new Cite('a', ' 12 '), $terms));
        self::assertSame('pp. 12–14', $style->writeLocator(new Cite('a', '12 - 14'), $terms));
        self::assertSame('', $style->writeLocator(new Cite('a', '   '), $terms));
        self::assertSame('', $style->writeLocator(new Cite('a'), $terms));
    }

    public function testThePages(): void
    {
        $style = $this->style();

        self::assertSame('45–67', $style->writePages('45-67'));
        self::assertSame('45–67', $style->writePages("45 \t\n\v\f\r--- \t\n\v\f\r67"));
        self::assertSame(' 1–2–3 ', $style->writePages(' 1 - 2 -- 3 '));
        self::assertSame("12–\0", $style->writePages("12 -\0"));
        self::assertSame("\0–12", $style->writePages("\0- 12"));
        self::assertSame(' 47 ', $style->writePages(' 47 '));
        self::assertSame('–', $style->writePages(' - '));
    }

    public function testPagesAfterALongRunOfSpacesTakeLinearTime(): void
    {
        $start = hrtime(true);

        self::assertSame(str_repeat(' ', 1_000_000).'1–2', $this->style()->writePages(str_repeat(' ', 1_000_000).'1-2'));
        self::assertLessThan(1_000_000_000, hrtime(true) - $start);
    }
}

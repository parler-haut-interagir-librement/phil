<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Hyphenation;

use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Hyphenation\Hyphenator;
use Phil\Typography\Hyphenation\Patterns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Hyphenator::class)]
final class HyphenatorTest extends TestCase
{
    public function testABreakWhereTheHighestDigitIsOdd(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('a1b xa2b', '', 1, 1));

        self::assertSame([1], $hyphenator->breaks('ab'));
        self::assertSame([2], $hyphenator->breaks('yab'));
        self::assertSame([], $hyphenator->breaks('xab'));
        self::assertSame('ya-b', $hyphenator->hyphenate('yab', '-'));
        self::assertSame("ya\u{AD}b", $hyphenator->hyphenate('yab'));
    }

    public function testThePatternsSeeTheEndsOfTheWord(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('.a1b b1c. 1d', '', 1, 1));

        self::assertSame([1, 2], $hyphenator->breaks('abc'));
        self::assertSame([], $hyphenator->breaks('cba'));
        self::assertSame([3], $hyphenator->breaks('xxbc'), 'only at the end');
        self::assertSame([1, 3], $hyphenator->breaks('abcd'));
        self::assertSame([1, 2, 3], $hyphenator->breaks('dddd'));
        self::assertSame([], $hyphenator->breaks('d'));
    }

    public function testTheEndsKeepTheirLetters(): void
    {
        $patterns = Patterns::parse('1a1b1c1d1e1', '', 2, 3);

        self::assertSame([2], new Hyphenator($patterns)->breaks('abcde'));
        self::assertSame([1, 2, 3, 4], new Hyphenator($patterns, 1, 1)->breaks('abcde'));
        self::assertSame([1, 2, 3, 4], new Hyphenator($patterns, 0, -2)->breaks('abcde'));
        self::assertSame([3], new Hyphenator($patterns, 3)->breaks('abcdef'));
        self::assertSame([2, 3, 4], new Hyphenator($patterns, null, 1)->breaks('abcde'));
        self::assertSame([], new Hyphenator($patterns)->breaks('abcd'));
    }

    public function testExceptionsWin(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('1a1b1c1d1e1', 'abc-de abcd a-b-c-d-e-f', 1, 1));

        self::assertSame([1, 2, 3, 4, 5], $hyphenator->breaks('abcdef'));

        self::assertSame([3], $hyphenator->breaks('abcde'));
        self::assertSame([3], $hyphenator->breaks('ABCDE'));
        self::assertSame('ABC-DE', $hyphenator->hyphenate('ABCDE', '-'));
        self::assertSame([], $hyphenator->breaks('abcd'));
        self::assertSame([], $hyphenator->breaks('abc'));
    }

    public function testExceptionsKeepTheEnds(): void
    {
        self::assertSame([], new Hyphenator(Patterns::parse('', 'a-bcde abcd-e', 2, 2))->breaks('abcde'));
        self::assertSame([], new Hyphenator(Patterns::parse('', 'abcd-e', 2, 2))->breaks('abcde'));
    }

    public function testAWordWhoseLowerCaseChangesLengthIsLeft(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('1i1 1s1 1t1 1a1 1n1 1b1 1u1 1l1', '', 1, 1));

        self::assertSame([], $hyphenator->breaks('İstanbul'));
        self::assertSame('İstanbul', $hyphenator->hyphenate('İstanbul', '-'));
        self::assertSame('I-s-t-a-n-b-u-l', $hyphenator->hyphenate('Istanbul', '-'));
    }

    public function testTheWordIsReadInLowerCase(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('a1é', '', 1, 1));

        self::assertSame([1], $hyphenator->breaks('AÉ'));
        self::assertSame([1], $hyphenator->breaks('aé'));
    }

    public function testNeverBeforeACombiningMark(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('1e1 1a1 1\u{301}1', '', 1, 1));

        self::assertSame([2], $hyphenator->breaks("e\u{301}a"));
        self::assertSame("e\u{301}-a", $hyphenator->hyphenate("e\u{301}a", '-'));
    }

    public function testTheDictionaryGivenIsUsed(): void
    {
        $directory = sys_get_temp_dir().'/phil-typography-hyphenator-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/fr.pat.txt', '1a1 1b1');

        try {
            self::assertSame('ab-ab', Hyphenator::for('fr', new Dictionary($directory))->hyphenate('abab', '-'));
        } finally {
            unlink($directory.'/fr.pat.txt');
            rmdir($directory);
        }
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function languages(): iterable
    {
        yield ['en_US', 'hyphenation', 'hy-phen-ation'];
        yield ['en_US', 'typography', 'ty-pog-ra-phy'];
        yield ['en_US', 'table', 'ta-ble'];
        yield ['en_US', 'project', 'project'];
        yield ['en_GB', 'typography', 'ty-po-graphy'];
        yield ['fr', 'anticonstitutionnellement', 'an-ti-cons-ti-tu-tion-nel-le-ment'];
        yield ['de', 'Silbentrennungsalgorithmus', 'Sil-ben-tren-nungs-al-go-rith-mus'];
        yield ['de-1901', 'Silbentrennung', 'Sil-ben-tren-nung'];
        yield ['de_CH', 'Rechtschreibung', 'Recht-schrei-bung'];
        yield ['nb', 'jernbanestasjon', 'jern-bane-sta-sjon'];
        yield ['sr', 'ћирилица', 'ћи-ри-ли-ца'];
        yield ['sr_Latn', 'latinica', 'la-ti-ni-ca'];
        yield ['el', 'υπολογιστής', 'υ-πο-λο-γι-στής'];
        yield ['ru', 'программирование', 'про-грам-ми-ро-ва-ние'];
        yield ['it', 'programmazione', 'pro-gram-ma-zio-ne'];
        yield ['es', 'programación', 'pro-gra-ma-ción'];
        yield ['pt_BR', 'programação', 'pro-gra-ma-ção'];
        yield ['pl', 'programowanie', 'pro-gra-mo-wa-nie'];
        yield ['nl', 'programmering', 'pro-gram-me-ring'];
    }

    #[DataProvider('languages')]
    public function testTheShippedPatterns(string $language, string $word, string $expected): void
    {
        self::assertSame($expected, Hyphenator::for($language)->hyphenate($word, '-'));
        self::assertSame($expected, Hyphenator::for($language, new Dictionary())->hyphenate($word, '-'));
    }
}

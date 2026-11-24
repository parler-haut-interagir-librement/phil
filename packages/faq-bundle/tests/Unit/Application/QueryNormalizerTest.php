<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use Phil\FaqBundle\Application\QueryNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryNormalizer::class)]
final class QueryNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function searches(): iterable
    {
        yield 'accents, case and punctuation' => ['  Où est  MA commande ?', 'ou est ma commande'];
        yield 'compatibility forms' => ['ﬁn ①', 'fin 1'];
        yield 'apostrophes and hyphens' => ["L'Ordre du Corbeau — tome-2", 'l ordre du corbeau tome 2'];
        yield 'other scripts' => ['Ελληνικά Ünïcödé', 'ελληνικα unicode'];
        yield 'nothing but symbols' => ['?!… — ', ''];
        yield 'empty' => ['', ''];
        yield 'invalid UTF-8' => ["caf\xC3", 'caf'];
    }

    #[DataProvider('searches')]
    public function testASearchIsFolded(string $search, string $normalized): void
    {
        self::assertSame($normalized, new QueryNormalizer()->normalize($search));
    }

    public function testASearchIsCutToOneHundredCharactersWithoutATrailingSpace(): void
    {
        $normalizer = new QueryNormalizer();

        self::assertSame(str_repeat('e', 100), $normalizer->normalize(str_repeat('é', 150)));
        self::assertSame(str_repeat('a', 99), $normalizer->normalize(str_repeat('a', 99).' bcd'));
    }

    public function testOnlyTheFirstThousandBytesAreRead(): void
    {
        $normalizer = new QueryNormalizer();

        self::assertSame('', $normalizer->normalize(str_repeat(' ', 1_000).'hidden'));
        self::assertSame('x', $normalizer->normalize(str_repeat(' ', 999).'x'));
    }

    public function testAWholeTextIsFoldedWithoutLimit(): void
    {
        $text = str_repeat('Été ', 300);

        self::assertSame(trim(str_repeat('ete ', 300)), new QueryNormalizer()->fold($text));
    }

    public function testTheWordsAreDistinctAndTenAtMost(): void
    {
        $normalizer = new QueryNormalizer();

        self::assertSame([], $normalizer->terms(''));
        self::assertSame(['a', 'b'], $normalizer->terms('a b a'));
        self::assertSame(['w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7', 'w8', 'w9', 'w10'], $normalizer->terms('w1 w2 w3 w4 w5 w6 w7 w8 w9 w10 w11'));
    }

    public function testATextMatchesWhenItHoldsEveryWord(): void
    {
        $normalizer = new QueryNormalizer();

        self::assertTrue($normalizer->matches('how do i pay by card', ['pay', 'card']));
        self::assertTrue($normalizer->matches('anything', []));
        self::assertFalse($normalizer->matches('how do i pay by card', ['pay', 'cash']));
    }

    public function testTheTextOfHtmlKeepsItsWordsApart(): void
    {
        self::assertSame('By card or transfer. Ask “us” & see', QueryNormalizer::textOf("<p>By <strong>card</strong> or\n transfer.</p><p>Ask &ldquo;us&rdquo; &amp; see</p>"));
        self::assertSame('one two', QueryNormalizer::textOf('<li>one</li><li>two</li>'));
    }

    public function testASearchIsCutInCharacters(): void
    {
        self::assertSame(str_repeat('日', 100), new QueryNormalizer()->normalize(str_repeat('日', 150)));
    }

    public function testTheEntitiesOfHtml5AreRead(): void
    {
        self::assertSame('it\'s "here"', QueryNormalizer::textOf('it&apos;s &quot;here&quot;'));
    }
}

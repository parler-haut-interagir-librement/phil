<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Unit;

use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Internal\Distance;
use Phil\EmailAddress\SyntaxPolicy;
use Phil\EmailAddress\TypoSuggester;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(TypoSuggester::class)]
#[CoversClass(Distance::class)]
final class TypoSuggesterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function slips(): iterable
    {
        yield 'swapped letters' => ['ada@gmial.com', 'ada@gmail.com'];
        yield 'missing letter' => ['ada@gmai.com', 'ada@gmail.com'];
        yield 'extra letter' => ['ada@gmaill.com', 'ada@gmail.com'];
        yield 'wrong letter' => ['ada@gmsil.com', 'ada@gmail.com'];
        yield 'missing dot' => ['ada@gmailcom', 'ada@gmail.com'];
        yield 'cut top-level domain' => ['ada@gmail.co', 'ada@gmail.com'];
        yield 'uppercase' => ['Ada@GMIAL.com', 'Ada@gmail.com'];
        yield 'two slips in a long domain' => ['ada@hotmial.con', 'ada@hotmail.com'];
        yield 'two slips in a longer domain' => ['ada@vidoetron.ca', 'ada@videotron.ca'];
        yield 'first of two at the same distance' => ['ada@yahoo.co', 'ada@yahoo.com'];
        yield 'top-level slip of another domain' => ['ada@example.con', 'ada@example.com'];
        yield 'top-level slip of net' => ['ada@example.nte', 'ada@example.net'];
        yield 'top-level slip of org' => ['ada@example.ogr', 'ada@example.org'];
        yield 'known domain' => ['ada@gmail.com', null];
        yield 'mail.com is known' => ['ada@mail.com', null];
        yield 'far from every domain' => ['ada@example.com', null];
        yield 'two slips in a short domain' => ['ada@gmali.cmo', 'ada@gmali.com'];
        yield 'three slips' => ['ada@gaiml.co', null];
        yield 'short known domains are left alone' => ['ada@aon.com', null];
        yield 'delegated top-level domain' => ['ada@example.co', null];
        yield 'no at' => ['gmial.com', null];
        yield 'non-ASCII domain' => ['ada@gmäil.com', null];
        yield 'empty domain' => ['ada@', null];
        yield 'no top-level domain to fix' => ['ada@examplecon', null];
        yield 'suggestion that is not an address' => ['a da@gmial.com', null];
        yield 'known domain of eight characters' => ['ada@livv.com', 'ada@live.com'];
        yield 'two slips in a domain of ten characters' => ['ada@hotmial.fe', 'ada@hotmail.fr'];
        yield 'three slips in a long domain' => ['ada@htomial.cxm', null];
        yield 'non-ASCII at the end' => ['ada@videotron.cé', null];
        yield 'non-ASCII at the start' => ['ada@éideotron.ca', null];
    }

    #[DataProvider('slips')]
    public function testSuggests(string $input, ?string $expected): void
    {
        self::assertSame($expected, new TypoSuggester()->suggest($input)?->toString());
    }

    public function testSuggestsForAnAddress(): void
    {
        self::assertSame('ada@gmail.com', new TypoSuggester()->suggest(EmailAddress::parse('ada@gmial.com'))?->toString());
        self::assertSame('ada@gmail.com', new TypoSuggester()->suggest('  ada@gmial.com ')?->toString());
    }

    public function testTakesTheApplicationsDomainsAndPolicy(): void
    {
        $suggester = new TypoSuggester(['example-mail.ca'], ['cmo' => 'com'], new SyntaxPolicy()->withQuotedLocalPart());

        self::assertSame('"a b"@example-mail.ca', $suggester->suggest('"a b"@exmaple-mail.ca')?->toString());
        self::assertNull($suggester->suggest('ada@gmial.com'));
        self::assertNull($suggester->suggest('ada@example.con'));
        self::assertSame('ada@example.com', $suggester->suggest('ada@example.cmo')?->toString());
    }

    public function testPrefersTheFirstDomainOnATie(): void
    {
        self::assertSame('ada@first.example', new TypoSuggester(['first.example', 'firsu.example'])->suggest('ada@firss.example')?->toString());
        self::assertSame('ada@firsu.example', new TypoSuggester(['firsu.example', 'first.example'])->suggest('ada@firss.example')?->toString());
        self::assertSame('ada@aaaaaaaaab.com', new TypoSuggester(['aaaaaaaaxx.com', 'aaaaaaaaab.com'])->suggest('ada@aaaaaaaaaa.com')?->toString());
    }

    /**
     * @return iterable<string, array{string, string, int, int}>
     */
    public static function distances(): iterable
    {
        yield 'same' => ['gmail.com', 'gmail.com', 2, 0];
        yield 'swap' => ['gmial.com', 'gmail.com', 2, 1];
        yield 'swap at the start' => ['mgail.com', 'gmail.com', 2, 1];
        yield 'insertion' => ['gmail.com', 'gmaill.com', 2, 1];
        yield 'deletion' => ['gmaill.com', 'gmail.com', 2, 1];
        yield 'substitution' => ['gmail.com', 'gmall.com', 2, 1];
        yield 'two' => ['gmial.con', 'gmail.com', 2, 2];
        yield 'beyond the limit' => ['abcdef', 'ghijkl', 2, 3];
        yield 'lengths too far apart' => ['ab', 'abcde', 2, 3];
        yield 'lengths at the limit' => ['abc', 'abcde', 2, 2];
        yield 'empty' => ['', 'ab', 2, 2];
        yield 'both empty' => ['', '', 0, 0];
        yield 'limit zero' => ['a', 'b', 0, 1];
        yield 'over the limit only at the end' => ['abcx', 'abcy', 0, 1];
        yield 'over the limit early' => ['xbcd', 'abcd', 0, 1];
        yield 'swap is not two substitutions' => ['ab', 'ba', 1, 1];
        yield 'swap needs both letters' => ['ac', 'ba', 2, 2];
        yield 'swap needs the other letter' => ['ab', 'bc', 2, 2];
        yield 'one row over the limit' => ['xyab', 'ab', 3, 2];
        yield 'over the limit in the last cell' => ['abcd', 'abdx', 1, 2];
        yield 'far over the limit in the last cell' => ['ab', 'bca', 1, 2];
    }

    #[DataProvider('distances')]
    public function testMeasuresTheDistance(string $a, string $b, int $limit, int $expected): void
    {
        self::assertSame($expected, Distance::between($a, $b, $limit));
    }

    public function testMatchesTheWholeTable(): void
    {
        $random = new Randomizer(new Xoshiro256StarStar(20_261_108));

        for ($pair = 0; $pair < 3_000; ++$pair) {
            $a = substr($random->getBytesFromString('abc', 7), $random->getInt(0, 7));
            $b = substr($random->getBytesFromString('abc', 7), $random->getInt(0, 7));
            $limit = $random->getInt(0, 3);

            self::assertSame(min($this->wholeTable($a, $b), $limit + 1), Distance::between($a, $b, $limit), $a.' '.$b.' '.$limit);
        }
    }

    /**
     * The distance by the whole table, without a limit.
     */
    private function wholeTable(string $a, string $b): int
    {
        /** @var array<int, array<int, int>> $table */
        $table = [];

        for ($i = 0; $i <= \strlen($a); ++$i) {
            for ($j = 0; $j <= \strlen($b); ++$j) {
                $table[$i][$j] = match (true) {
                    0 === $i => $j,
                    0 === $j => $i,
                    default => min($table[$i - 1][$j] + 1, $table[$i][$j - 1] + 1, $table[$i - 1][$j - 1] + ($a[$i - 1] === $b[$j - 1] ? 0 : 1)),
                };

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $table[$i][$j] = min($table[$i][$j], $table[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $table[\strlen($a)][\strlen($b)];
    }
}

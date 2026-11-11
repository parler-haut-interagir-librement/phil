<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Rdf;

use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Rdf\Canonicalizer;
use Phil\OpenBadges\Rdf\IdentifierIssuer;
use Phil\OpenBadges\Rdf\NQuads;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The canonical labels of datasets whose blank nodes only their neighbourhood tells apart, and their independence from
 * the labels and the order the quads come in.
 */
#[CoversClass(Canonicalizer::class)]
#[CoversClass(IdentifierIssuer::class)]
#[CoversClass(NQuads::class)]
final class CanonicalizerTest extends TestCase
{
    private const string P = '<urn:p>';

    private const string Q = '<urn:q>';

    /**
     * @return iterable<string, array{list<array{string, string, string, string}>, string}>
     */
    public static function datasets(): iterable
    {
        yield 'no blank node' => [[['<urn:s>', self::P, '"o"', ''], ['<urn:a>', self::P, '<urn:b>', '<urn:g>']], "<urn:a> <urn:p> <urn:b> <urn:g> .\n<urn:s> <urn:p> \"o\" .\n"];

        yield 'unique first degree' => [[['_:x', self::P, '"1"', ''], ['_:y', self::P, '"2"', '']], "_:c14n0 <urn:p> \"1\" .\n_:c14n1 <urn:p> \"2\" .\n"];

        yield 'a cycle of three' => [
            [['_:x', self::P, '_:y', ''], ['_:y', self::P, '_:z', ''], ['_:z', self::P, '_:x', '']],
            "_:c14n0 <urn:p> _:c14n2 .\n_:c14n1 <urn:p> _:c14n0 .\n_:c14n2 <urn:p> _:c14n1 .\n",
        ];

        yield 'two cycles' => [
            [['_:a', self::P, '_:b', ''], ['_:b', self::P, '_:a', ''], ['_:c', self::P, '_:d', ''], ['_:d', self::P, '_:e', ''], ['_:e', self::P, '_:c', '']],
            "_:c14n0 <urn:p> _:c14n1 .\n_:c14n1 <urn:p> _:c14n0 .\n_:c14n2 <urn:p> _:c14n4 .\n_:c14n3 <urn:p> _:c14n2 .\n_:c14n4 <urn:p> _:c14n3 .\n",
        ];

        $nodes = ['_:a', '_:b', '_:c', '_:d'];
        $complete = [];

        foreach ($nodes as $from) {
            foreach ($nodes as $to) {
                if ($from !== $to) {
                    $complete[] = [$from, self::P, $to, ''];
                }
            }
        }

        yield 'a complete graph of four' => [$complete, implode('', array_map(static fn (array $pair): string => \sprintf("_:c14n%d <urn:p> _:c14n%d .\n", ...$pair), [[0, 1], [0, 2], [0, 3], [1, 0], [1, 2], [1, 3], [2, 0], [2, 1], [2, 3], [3, 0], [3, 1], [3, 2]]))];

        yield 'blank graph names' => [
            [['_:s', self::P, '"v"', '_:g'], ['_:t', self::P, '"v"', '_:h'], ['<urn:x>', self::Q, '_:g', ''], ['<urn:x>', self::Q, '_:h', '']],
            "<urn:x> <urn:q> _:c14n0 .\n<urn:x> <urn:q> _:c14n2 .\n_:c14n1 <urn:p> \"v\" _:c14n0 .\n_:c14n3 <urn:p> \"v\" _:c14n2 .\n",
        ];

        yield 'a diamond' => [
            [['_:top', self::P, '_:l', ''], ['_:top', self::P, '_:r', ''], ['_:l', self::Q, '_:bottom', ''], ['_:r', self::Q, '_:bottom', ''], ['_:bottom', self::P, '"end"', '']],
            "_:c14n0 <urn:p> _:c14n2 .\n_:c14n0 <urn:p> _:c14n3 .\n_:c14n1 <urn:p> \"end\" .\n_:c14n2 <urn:q> _:c14n1 .\n_:c14n3 <urn:q> _:c14n1 .\n",
        ];

        yield 'twins told apart further away' => [
            [['<urn:a>', self::P, '_:x', ''], ['<urn:a>', self::P, '_:y', ''], ['_:x', self::Q, '_:z', ''], ['_:y', self::Q, '_:w', ''], ['_:z', self::P, '"1"', ''], ['_:w', self::P, '"2"', '']],
            "<urn:a> <urn:p> _:c14n2 .\n<urn:a> <urn:p> _:c14n3 .\n_:c14n0 <urn:p> \"1\" .\n_:c14n1 <urn:p> \"2\" .\n_:c14n2 <urn:q> _:c14n1 .\n_:c14n3 <urn:q> _:c14n0 .\n",
        ];
    }

    /**
     * @param list<array{string, string, string, string}> $quads
     */
    #[DataProvider('datasets')]
    public function testTheCanonicalForm(array $quads, string $canonical): void
    {
        self::assertSame($canonical, Canonicalizer::canonicalize($quads));
    }

    /**
     * @param list<array{string, string, string, string}> $quads
     */
    #[DataProvider('datasets')]
    public function testTheCanonicalFormIgnoresLabelsAndOrder(array $quads, string $canonical): void
    {
        for ($round = 0; $round < 6; ++$round) {
            $labels = [];
            $renamed = [];

            foreach ($quads as $quad) {
                foreach ([0, 2, 3] as $position) {
                    if (str_starts_with($quad[$position], '_:')) {
                        $quad[$position] = $labels[$quad[$position]] ??= '_:n'.random_int(0, 1_000_000).'x'.\count($labels);
                    }
                }

                $renamed[] = $quad;
            }

            $order = array_keys($renamed);
            usort($order, static fn (): int => random_int(-1, 1));

            self::assertSame($canonical, Canonicalizer::canonicalize(array_map(static fn (int $index): array => $renamed[$index], $order)));
        }
    }

    /**
     * The first 16 hexadecimal digits of the SHA-256 of the canonical form of each seeded dataset: from 4 to 6 blank
     * nodes, each the subject and the object of two or three links of one predicate, so that every blank node has the
     * same first-degree hash and only the n-degree hashes tell them apart. The values were computed by this
     * canonicalizer, which passes every evaluation test of the W3C RDFC-1.0 suite.
     */
    private const array SEEDED = [
        1 => '5d17c5fb6071d325',
        2 => '46c3df2285583c61',
        3 => '4393aab87d68b58c',
        4 => '116af3ff1f4dfa76',
        5 => 'ffabd6e58c288e2c',
        6 => '34fa2d56ffdb96dd',
        7 => '41420a86aa5b6c33',
        8 => '3919673f7c574593',
        9 => '092762a40d97a59e',
        10 => 'e34ae72361bd4c03',
        11 => 'e84fbcc22709d6d7',
        12 => 'd96a6498966f6e60',
        13 => 'b8258f9a1deb432e',
        14 => '3f378ebc53002a4c',
        15 => 'e8505abadff96aae',
        16 => '89bc07cc32d05c07',
        17 => '12afca334ca8164b',
        18 => 'e83e6bce27b76e9f',
        19 => 'f787f3c1029cc120',
        20 => '26eef0f969ee5df2',
        21 => '0bfb252177d98191',
        22 => 'd85e46fbe79207be',
        23 => 'c22a7030adb42926',
        24 => '4367aae32a34f244',
        25 => 'be28f54b77468a34',
        26 => '73045ee4f70856ad',
        27 => 'abc59998cd7447e0',
        28 => 'ff4b39385748a31e',
        29 => 'e28502132bdbadb5',
        30 => '8fea02e55b428619',
        31 => '8f629b8e9aedf963',
        32 => '1808bc3c8bd90d4b',
        33 => '52c5f4d46e5e0f59',
        34 => '1808bc3c8bd90d4b',
        35 => '26eddae557946fc8',
        36 => 'f506b9c24a458edd',
        37 => 'b7b472ac95ce3348',
        38 => '53af2e90a97f2f74',
        39 => 'c0197dd3bd100455',
        40 => '955eee19cebfc557',
        41 => '70fc67fc1314a6da',
        42 => '1808bc3c8bd90d4b',
        43 => '4c62609671f0ee53',
        44 => '94da0293af3014c3',
        45 => 'c2d2bc7d80c5b3fc',
        46 => '5360b78aea1eaa78',
        47 => 'c6d7a87ed9edbf5b',
        48 => 'a42da2f2ac672ad2',
        49 => '149067f043a85bb3',
        50 => '0b82866e01e69df4',
        51 => '13de13110d602d5a',
        52 => '38f2a49640a5b8c5',
        53 => '63738f179912dba9',
        54 => '78337a5915335791',
        55 => 'e20b76ddf20114cd',
        56 => '12a2742357d647ca',
        57 => '0b4dee986c2958e8',
        58 => 'e2b772bc2630026d',
        59 => 'e1ccab187c4bab47',
        60 => '5b5230c7ec32e1b5',
        61 => '3a22f7274f8ed856',
        62 => '1e0d423be669c0d8',
        63 => '2b5ba850fbe9a9f8',
        64 => 'd6ed90f39358b50d',
        65 => 'ecd60567a690399a',
        66 => '5b19beae6f650f1c',
        67 => '01cbc9a1f7f4a4eb',
        68 => '662fcf240529c6c8',
        69 => 'da374a567ecb7dfa',
        70 => '02de1e74e4eae796',
        71 => '8166e7337fed1fbb',
        72 => 'e28502132bdbadb5',
        73 => '77fe555e136ee708',
        74 => 'd85e46fbe79207be',
        75 => '98e1c868ca0df2f3',
        76 => '00bd6aee7c3f9af4',
        77 => '4367aae32a34f244',
        78 => 'dffece00676ba461',
        79 => '3bb6ab59a00db259',
        80 => 'e2b772bc2630026d',
    ];

    public function testSeededDatasets(): void
    {
        foreach (self::SEEDED as $seed => $hash) {
            $quads = $this->seeded($seed);

            self::assertSame($hash, substr(hash('sha256', Canonicalizer::canonicalize($quads)), 0, 16), \sprintf('The dataset of seed %d.', $seed));
            self::assertSame($hash, substr(hash('sha256', Canonicalizer::canonicalize(array_reverse($quads))), 0, 16), \sprintf('The reversed dataset of seed %d.', $seed));
        }
    }

    /**
     * Two hubs and their leaves, all linked by `<urn:f>`: with this predicate the hubs' group of first-degree hashes
     * comes first, and the leaves of a hub are permuted.
     */
    public function testAsManyIndistinguishableNeighboursAsAllowedArePermuted(): void
    {
        $quads = [];

        foreach (['_:h1', '_:h2'] as $hub) {
            foreach (range(1, Canonicalizer::MAX_PERMUTED) as $leaf) {
                $quads[] = [$hub, '<urn:f>', $hub.'-'.$leaf, ''];
            }
        }

        self::assertSame(2 * Canonicalizer::MAX_PERMUTED, substr_count(Canonicalizer::canonicalize($quads), "\n"));
    }

    public function testAQuadGivenTwiceCountsOnce(): void
    {
        self::assertSame("_:c14n0 <urn:p> \"1\" .\n", Canonicalizer::canonicalize([['_:x', self::P, '"1"', ''], ['_:x', self::P, '"1"', '']]));
    }

    public function testTooManyIndistinguishableNeighboursAreRefused(): void
    {
        // A complete graph: each blank node has one more neighbour than may be permuted.
        $nodes = array_map(static fn (int $index): string => '_:n'.$index, range(0, Canonicalizer::MAX_PERMUTED + 1));
        $quads = [];

        foreach ($nodes as $from) {
            foreach ($nodes as $to) {
                if ($from !== $to) {
                    $quads[] = [$from, self::P, $to, ''];
                }
            }
        }

        $this->expectExceptionObject(InvalidCredential::because('The canonical form of the document permutes too many blank nodes nothing tells apart.'));
        Canonicalizer::canonicalize($quads);
    }

    public function testAsManyNeighboursAsAllowedArePermuted(): void
    {
        $quads = [];

        foreach (range(0, Canonicalizer::MAX_PERMUTED - 1) as $index) {
            $quads[] = ['_:hub', self::P, '_:n'.$index, ''];
            $quads[] = ['_:n'.$index, self::P, '_:hub', ''];
        }

        self::assertSame(2 * Canonicalizer::MAX_PERMUTED, substr_count(Canonicalizer::canonicalize($quads), "\n"));
    }

    public function testTooMuchWorkIsRefused(): void
    {
        $quads = [];

        for ($pair = 0; $pair < intdiv(Canonicalizer::MAX_CALLS, 4) + 1; ++$pair) {
            $quads[] = ['_:a'.$pair, self::P, '_:b'.$pair, ''];
            $quads[] = ['_:b'.$pair, self::P, '_:a'.$pair, ''];
        }

        $this->expectExceptionObject(InvalidCredential::because('The canonical form of the document takes too much work to compute.'));
        Canonicalizer::canonicalize($quads);
    }

    public function testAsMuchWorkAsAllowedIsDone(): void
    {
        $quads = [];

        for ($pair = 0; $pair < intdiv(Canonicalizer::MAX_CALLS, 4); ++$pair) {
            $quads[] = ['_:a'.$pair, self::P, '_:b'.$pair, ''];
            $quads[] = ['_:b'.$pair, self::P, '_:a'.$pair, ''];
        }

        self::assertSame(Canonicalizer::MAX_CALLS / 2, substr_count(Canonicalizer::canonicalize($quads), "\n"));
    }

    public function testLiteralsAreEscaped(): void
    {
        self::assertSame('"a\"b\\\\c\n\r\t\b\f\u0000\u001F\u007F\u000B é"', NQuads::literal("a\"b\\c\n\r\t\x08\x0C\0\x1F\x7F\x0B é"));
        self::assertSame('"1"^^<http://www.w3.org/2001/XMLSchema#integer>', NQuads::literal('1', NQuads::XSD.'integer'));
        self::assertSame('"chat"@fr', NQuads::literal('chat', NQuads::XSD.'integer', 'fr'));
        self::assertSame('"x"', NQuads::literal('x'));
        self::assertSame('<urn:x>', NQuads::iri('urn:x'));
        self::assertSame("<urn:s> <urn:p> <urn:o> .\n", NQuads::line(['<urn:s>', '<urn:p>', '<urn:o>', '']));
        self::assertSame("<urn:s> <urn:p> <urn:o> <urn:g> .\n", NQuads::line(['<urn:s>', '<urn:p>', '<urn:o>', '<urn:g>']));
    }

    /**
     * A dataset drawn from its seed by a linear congruential generator: from 4 to 6 blank nodes, linked by two or three
     * permutations of themselves.
     *
     * @return list<array{string, string, string, string}>
     */
    private function seeded(int $seed): array
    {
        $state = $seed;
        $next = static function (int $bound) use (&$state): int {
            $state = ($state * 1_103_515_245 + 12_345) % 2_147_483_648;

            return ($state >> 8) % $bound;
        };
        $nodes = 4 + $next(3);
        $quads = [];

        for ($round = 0; $round < 2 + $seed % 2; ++$round) {
            $targets = range(0, $nodes - 1);

            for ($index = $nodes - 1; $index > 0; --$index) {
                $other = $next($index + 1);
                [$targets[$index], $targets[$other]] = [$targets[$other], $targets[$index]];
            }

            foreach ($targets as $from => $to) {
                $quads[] = ['_:n'.$from, self::P, '_:n'.$to, ''];
            }
        }

        return $quads;
    }

    public function testAnIssuerIssuesInOrder(): void
    {
        $issuer = new IdentifierIssuer('b');

        self::assertFalse($issuer->has('_:x'));
        self::assertSame('b0', $issuer->issue('_:x'));
        self::assertSame('b1', $issuer->issue('_:y'));
        self::assertSame('b0', $issuer->issue('_:x'));
        self::assertTrue($issuer->has('_:x'));
        self::assertSame(['_:x' => 'b0', '_:y' => 'b1'], $issuer->issued);
    }
}

<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Pdf\PdfDictionary;
use Phil\DocumentImport\Internal\Pdf\PdfFilters;
use Phil\DocumentImport\Internal\Pdf\PdfName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each filter of a stream undone byte for byte: LZW written by an encoder of the test, the PNG and TIFF predictors
 * written row by row, ASCII85, ASCIIHex and run-length data at their edges, and the limits on what they give.
 */
#[CoversClass(PdfFilters::class)]
final class PdfFiltersTest extends TestCase
{
    public function testLzwGrowsItsCodesToTwelveBitsAndClearsItsTable(): void
    {
        $text = '';

        for ($index = 0; \strlen($text) < 60_000; ++$index) {
            $text .= 'w'.($index * 7919 % 1009).' '.str_repeat(\chr((97 + $index % 26) & 0xFF), $index % 5);
        }

        self::assertSame([$text, null], $this->filters()->decode($this->lzw($text), new PdfName('LZWDecode'), null));
        self::assertSame(['aaaaaaaaaa', null], $this->filters()->decode($this->lzw('aaaaaaaaaa'), new PdfName('LZW'), null));
    }

    public function testLzwStopsAtItsEndCode(): void
    {
        $data = $this->lzw('abcabc');

        self::assertSame('abcabc', $this->filters()->decode($data.$this->lzw('zzz', false), new PdfName('LZWDecode'), null)[0]);
        self::assertSame('ab', $this->filters()->decode($this->codes([97, 98]), new PdfName('LZWDecode'), null)[0]);
        // An unknown code at the start, or after a clear, stands for nothing.
        self::assertSame('a', $this->filters()->decode($this->codes([300, 256, 300, 97, 257]), new PdfName('LZWDecode'), null)[0]);
    }

    public function testLzwReadsACodeThatEndsExactlyWithTheData(): void
    {
        // Eight codes of nine bits fill nine bytes: the last one is read without an end code.
        self::assertSame('abcdefgh', $this->filters()->decode($this->codes(range(97, 104)), new PdfName('LZWDecode'), null)[0]);
    }

    public function testLzwIsBoundedByTheEntryLimit(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('A stream of the PDF is larger than 20 bytes once decoded.'));
        new PdfFilters(20, 10_000)->decode($this->lzw(str_repeat('abcdefghij', 100)), new PdfName('LZWDecode'), null);
    }

    public function testLzwOfExactlyTheLimitIsDecoded(): void
    {
        self::assertSame(str_repeat('ab', 10), new PdfFilters(20, 10_000)->decode($this->lzw(str_repeat('ab', 10)), new PdfName('LZWDecode'), null)[0]);
    }

    /**
     * @return iterable<string, array{int, list<int>, string}>
     */
    public static function pngRows(): iterable
    {
        // Three columns of two colours of eight bits: rows of six bytes, pixels of two.
        yield 'none' => [0, [1, 2, 3, 4, 5, 6], "\x01\x02\x03\x04\x05\x06"];
        yield 'sub' => [1, [1, 2, 3, 4, 5, 6], "\x01\x02\x04\x06\x09\x0C"];
        yield 'up' => [2, [1, 2, 3, 4, 5, 6], "\x0B\x16\x21\x2C\x37\x42"];
        yield 'average' => [3, [1, 2, 3, 4, 5, 6], "\x06\x0C\x15\x1E\x28\x33"];
        yield 'paeth' => [4, [1, 2, 3, 4, 5, 6], "\x0B\x16\x21\x2C\x37\x42"];
        yield 'unknown' => [9, [1, 2, 3, 4, 5, 6], "\x01\x02\x03\x04\x05\x06"];
        yield 'wrapping' => [1, [255, 255, 2, 2, 0, 0], "\xFF\xFF\x01\x01\x01\x01"];
    }

    /**
     * @param list<int> $row
     */
    #[DataProvider('pngRows')]
    public function testThePngPredictorsUndoEachRow(int $type, array $row, string $second): void
    {
        $first = "\x00".pack('C*', 10, 20, 30, 40, 50, 60);
        $data = $first.\chr($type & 0xFF).pack('C*', ...$row);
        $options = new PdfDictionary(['Predictor' => 12, 'Colors' => 2, 'BitsPerComponent' => 8, 'Columns' => 3]);

        self::assertSame(pack('C*', 10, 20, 30, 40, 50, 60).$second, $this->filters()->decode((string) gzcompress($data), new PdfName('FlateDecode'), $options)[0]);
    }

    public function testPaethPicksTheNearestNeighbour(): void
    {
        $options = new PdfDictionary(['Predictor' => 15, 'Colors' => 1, 'BitsPerComponent' => 8, 'Columns' => 2]);
        // Each case: the row above, the predicted row, the row restored; the second byte is predicted from the
        // first (left), the second above (up) and the first above (corner).
        $cases = [
            'up nearest' => [[100, 10], [5, 0], [105, 10]],
            'left nearest' => [[50, 60], [70, 0], [120, 120]],
            'corner nearest' => [[55, 60], [251, 0], [50, 55]],
            'left as near as corner' => [[10, 15], [246, 0], [0, 0]],
            'up as near as corner' => [[5, 15], [251, 0], [0, 15]],
        ];

        foreach ($cases as [$above, $row, $expected]) {
            $data = "\x00".pack('C*', ...$above)."\x04".pack('C*', ...$row);
            self::assertSame(pack('C*', ...$above, ...$expected), $this->filters()->decode((string) gzcompress($data), new PdfName('FlateDecode'), $options)[0]);
        }
    }

    public function testThePredictorsReadTheirOptions(): void
    {
        $flate = new PdfName('FlateDecode');
        $decode = fn (string $data, array $options): string => $this->filters()->decode((string) gzcompress($data), $flate, new PdfDictionary($options))[0];

        // Without Columns, Colors nor BitsPerComponent: rows of one byte.
        self::assertSame("\x05\x0A", $decode("\x01\x05\x02\x05", ['Predictor' => 10]));
        // Predictor 1 and none: the data as it is.
        self::assertSame("\x01\x05", $decode("\x01\x05", ['Predictor' => 1]));
        self::assertSame("\x01\x05", $decode("\x01\x05", ['Predictor' => '12']));
        // Sixteen bits a component: pixels of two bytes.
        self::assertSame("\x01\x02\x04\x06", $decode("\x01\x01\x02\x03\x04", ['Predictor' => 11, 'BitsPerComponent' => 16, 'Columns' => 2]));
        // Four colours of two bits: a pixel of one byte, a row of two for eight columns... wait, of two bytes.
        self::assertSame("\x01\x03", $decode("\x01\x01\x02", ['Predictor' => 11, 'Colors' => 4, 'BitsPerComponent' => 2, 'Columns' => 2]));
        // Eight columns of the default eight bits: rows of eight bytes.
        self::assertSame(str_repeat("\x01", 8).str_repeat("\x02", 8), $decode("\x00".str_repeat("\x01", 8)."\x02".str_repeat("\x01", 8), ['Predictor' => 12, 'Columns' => 8]));
        // Sixteen columns of one bit: rows of two bytes.
        self::assertSame("\x01\x01\x02\x02", $decode("\x00\x01\x01\x02\x01\x01", ['Predictor' => 12, 'BitsPerComponent' => 1, 'Columns' => 16]));
        // Eight columns of sixteen bits: rows of sixteen bytes.
        self::assertSame(str_repeat("\x01", 16).str_repeat("\x02", 16), $decode("\x00".str_repeat("\x01", 16)."\x02".str_repeat("\x01", 16), ['Predictor' => 12, 'BitsPerComponent' => 16, 'Columns' => 8]));
        // One bit a pixel: each byte added to the one before.
        self::assertSame("\x01\x03", $decode("\x01\x01\x02", ['Predictor' => 11, 'BitsPerComponent' => 1, 'Columns' => 16]));
        // Eight colours of eight bits: pixels of eight bytes.
        self::assertSame("\x01".str_repeat("\x00", 7)."\x01\x00", $decode("\x01\x01".str_repeat("\x00", 8), ['Predictor' => 11, 'Colors' => 8, 'Columns' => 2]));
        // No more than thirty-two colours: a pixel of thirty-two bytes.
        self::assertSame("\x01".str_repeat("\x00", 31)."\x01".str_repeat("\x00", 31), $decode("\x01\x01".str_repeat("\x00", 63), ['Predictor' => 11, 'Colors' => 40, 'Columns' => 2]));
        self::assertSame('', $decode('', ['Predictor' => 2]));
        self::assertSame('', $decode('', ['Predictor' => 12]));
        // Damaged options are their defaults, and their bounds hold.
        self::assertSame("\x05\x0A", $decode("\x01\x05\x02\x05", ['Predictor' => 10, 'Colors' => 0, 'BitsPerComponent' => 0, 'Columns' => 0]));
        self::assertSame("\x05\x0A", $decode("\x01\x05\x02\x05", ['Predictor' => 10, 'Colors' => '2', 'BitsPerComponent' => '16', 'Columns' => '3']));
        self::assertSame("\x01\x02\x04\x06", $decode("\x01\x01\x02\x03\x04", ['Predictor' => 11, 'Colors' => 1, 'BitsPerComponent' => 17, 'Columns' => 2]));
    }

    public function testTheTiffPredictorAddsEachSampleToTheOneBefore(): void
    {
        $options = new PdfDictionary(['Predictor' => 2, 'Colors' => 2, 'BitsPerComponent' => 8, 'Columns' => 2]);

        self::assertSame("\x01\x02\x04\x06\x05\x05\x06\x07", $this->filters()->decode((string) gzcompress("\x01\x02\x03\x04\x05\x05\x01\x02"), new PdfName('FlateDecode'), $options)[0]);
        self::assertSame("\xFF\x02", $this->filters()->decode((string) gzcompress("\xFF\x02"), new PdfName('FlateDecode'), new PdfDictionary(['Predictor' => 2]))[0]);
        self::assertSame("\xFF\x01", $this->filters()->decode((string) gzcompress("\xFF\x02"), new PdfName('FlateDecode'), new PdfDictionary(['Predictor' => 2, 'Columns' => 2]))[0]);
    }

    public function testAWidthFarBeyondTheDataIsBoundedByIt(): void
    {
        $options = new PdfDictionary(['Predictor' => 12, 'Colors' => 32, 'BitsPerComponent' => 16, 'Columns' => 1 << 20]);

        self::assertSame("abc\x00", $this->filters()->decode((string) gzcompress("\x00abc"), new PdfName('FlateDecode'), $options)[0]);
    }

    public function testFlateIsInflatedUpToItsLimit(): void
    {
        $data = (string) gzcompress(str_repeat('x', 100));

        self::assertSame(str_repeat('x', 100), new PdfFilters(100, 1000)->decode($data, new PdfName('FlateDecode'), null)[0]);

        $this->expectExceptionObject(LimitExceeded::because('A stream of the PDF is larger than 99 bytes once decoded.'));
        new PdfFilters(99, 1000)->decode($data, new PdfName('Fl'), null);
    }

    public function testADamagedFlateStreamGivesWhatComesBeforeItsDamage(): void
    {
        // The warnings of zlib on a damaged stream are never shown.
        $this->expectOutputString('');
        $text = str_repeat('The quick brown fox. ', 400);
        $damaged = substr((string) gzcompress($text, 0), 0, 3000);
        [$partial] = new PdfFilters(100_000, 100_000)->decode($damaged, new PdfName('FlateDecode'), null);

        self::assertGreaterThan(2900, \strlen($partial));
        self::assertSame(substr($text, 0, 2900), substr($partial, 0, 2900));

        $limited = new PdfFilters(2000, 100_000);
        $this->expectExceptionObject(LimitExceeded::because('A stream of the PDF is larger than 2000 bytes once decoded.'));
        $limited->decode($damaged, new PdfName('FlateDecode'), null);
    }

    public function testADamagedFlateStreamOfExactlyTheLimitIsKept(): void
    {
        $this->expectOutputString('');
        $damaged = substr((string) gzcompress(str_repeat('a', 5000), 0), 0, 2100);
        [$partial] = new PdfFilters(100_000, 100_000)->decode($damaged, new PdfName('FlateDecode'), null);
        $exact = \strlen($partial);

        self::assertSame($partial, new PdfFilters($exact, 100_000)->decode($damaged, new PdfName('FlateDecode'), null)[0]);
        self::assertSame('', new PdfFilters(100, 1000)->decode('garbage', new PdfName('FlateDecode'), null)[0]);
    }

    public function testAscii85(): void
    {
        $decode = fn (string $data): string => $this->filters()->decode($data, new PdfName('ASCII85Decode'), null)[0];

        self::assertSame('Man is ', $decode('<~9jqo^BlbD~>'));
        self::assertSame('Man is ', $decode("9jq\no^ Bl\tbD~>ignored"));
        self::assertSame("\0\0\0\0Man ", $decode('z9jqo^~>'));
        self::assertSame("Man \0\0\0\0\0\0\0\0", $decode('9jqo^z{z~>'));
        self::assertSame('Man', $decode('9jqo~>'));
        self::assertSame('Ma', $decode('9jq~>'));
        self::assertSame('', $decode('9~>'));
        self::assertSame("\xFF\xFF\xFF\xFF", $decode('s8W-!'));
        // A group past four bytes, which no writer gives, keeps its low four bytes.
        self::assertSame(pack('N', (84 * 85 ** 4 + 84 * 85 ** 3 + 84 * 85 ** 2 + 84 * 85 + 83) & 0xFFFFFFFF), $decode('uuuut'));
        self::assertSame("\x00\x00\x00", $decode('s8W-'));
        self::assertSame('Man is ', $decode('9jq o^ v Bl bD'));
        self::assertSame('Man ', $decode('9jqo^!'.\chr(32)));
        self::assertSame("\x00\x00\x00", $decode('!!!!'));
        // The last group is padded with the highest digit: the bytes it keeps depend on it.
        self::assertSame("\xFE", $decode('rV'));
        self::assertSame("\xFB\x92", $decode('qi^'));
        self::assertSame("\x80\x30\xF8", $decode('J2$c'));
    }

    public function testAsciiHex(): void
    {
        $decode = fn (string $data): string => $this->filters()->decode($data, new PdfName('AHx'), null)[0];

        self::assertSame('Hi', $decode('48 69>4142'));
        self::assertSame('Hip', $decode('4869 7'));
        self::assertSame("\xAB", $decode('a b x>'));
    }

    public function testRunLength(): void
    {
        $decode = fn (string $data): string => $this->filters()->decode($data, new PdfName('RunLengthDecode'), null)[0];

        self::assertSame('abcxxxxd', $decode("\x02abc\xFDx\x00d\x80ignored"));
        self::assertSame(str_repeat('y', 128).'z', $decode("\x81y\x00z"));
        self::assertSame('', $decode("\xFF"));
        self::assertSame('ab', $decode("\x7Fab"));
    }

    public function testRunLengthIsBoundedByTheEntryLimit(): void
    {
        self::assertSame(str_repeat('y', 128), new PdfFilters(128, 1000)->decode("\x81y", new PdfName('RL'), null)[0]);

        $this->expectException(LimitExceeded::class);
        new PdfFilters(128, 1000)->decode("\x81y\x00z\x81y", new PdfName('RL'), null);
    }

    public function testFiltersChainAndStopAtOneTheyDoNotUndo(): void
    {
        $data = bin2hex((string) gzcompress('chained'));

        self::assertSame(['chained', null], $this->filters()->decode($data, [new PdfName('ASCIIHexDecode'), new PdfName('FlateDecode')], [null, null]));
        self::assertSame(['raw', 'DCTDecode'], $this->filters()->decode('raw', [7, new PdfName('DCTDecode'), new PdfName('FlateDecode')], null));
        self::assertSame(['raw', null], $this->filters()->decode('raw', [7, 'x'], null));
    }

    public function testTheTotalOfEveryStreamIsBounded(): void
    {
        $filters = new PdfFilters(100, 10);
        $filters->decode('3132333435', new PdfName('AHx'), null);
        $filters->decode('3132333435', new PdfName('AHx'), null);

        $this->expectExceptionObject(LimitExceeded::because('The streams of the PDF are larger than 10 bytes once decoded.'));
        $filters->decode('31', new PdfName('AHx'), null);
    }

    private function filters(): PdfFilters
    {
        return new PdfFilters(1_000_000, 10_000_000);
    }

    /**
     * LZW as PDF writes it, its codes growing one code early and its table cleared once full.
     */
    private function lzw(string $text, bool $end = true): string
    {
        $codes = [256];
        /** @var array<string, int> $dictionary */
        $dictionary = [];
        $reset = static function () use (&$dictionary): void {
            $dictionary = [];

            for ($byte = 0; $byte < 256; ++$byte) {
                $dictionary[\chr($byte)] = $byte;
            }
        };
        $reset();
        $next = 258;
        $word = '';

        foreach (str_split($text) as $char) {
            if (isset($dictionary[$word.$char])) {
                $word .= $char;

                continue;
            }

            $codes[] = $dictionary[$word];

            if ($next < 4096) {
                $dictionary[$word.$char] = $next++;
            } else {
                $codes[] = 256;
                $reset();
                $next = 258;
            }

            $word = $char;
        }

        if ('' !== $word) {
            $codes[] = $dictionary[$word];
        }

        if ($end) {
            $codes[] = 257;
        }

        /** @var list<int> $codes the dictionary, filled by reference, holds integers */
        return $this->codes($codes);
    }

    /**
     * Codes packed at the width a PDF reader expects for each, the table growing with each code after the first.
     *
     * @param list<int> $codes
     */
    private function codes(array $codes): string
    {
        $bits = '';
        $width = 9;
        $size = 258;
        $first = true;

        foreach ($codes as $code) {
            $bits .= str_pad(decbin($code), $width, '0', \STR_PAD_LEFT);

            if (256 === $code) {
                [$width, $size, $first] = [9, 258, true];

                continue;
            }

            if (!$first && $size < 4096) {
                ++$size;
            }

            $first = false;
            $width = min(12, $size + 1 >= (1 << $width) ? $width + 1 : $width);
        }

        $bits = str_pad($bits, (int) ceil(\strlen($bits) / 8) * 8, '0');

        return implode('', array_map(static fn (string $byte): string => \chr((int) bindec($byte) & 0xFF), str_split($bits, 8)));
    }
}

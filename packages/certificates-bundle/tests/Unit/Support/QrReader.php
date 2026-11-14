<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use LogicException;

/**
 * Reads back the QR code a PDF draws, the way a phone's camera would read the printed page: the modules from the
 * filled rectangles inside the white square of the code, then the format, the mask, the codewords and the segments of
 * ISO/IEC 18004. It reads versions 1 to 9 at the error correction level M, what the certificate draws, and trusts the
 * codewords rather than correcting them: a module drawn wrong makes the text read wrong, which the test sees.
 */
final readonly class QrReader
{
    /**
     * The error correction codewords of each block and the data codewords of each block, at level M.
     */
    private const array BLOCKS = [
        1 => [10, [16]],
        2 => [16, [28]],
        3 => [26, [44]],
        4 => [18, [32, 32]],
        5 => [24, [43, 43]],
        6 => [16, [27, 27, 27, 27]],
        7 => [18, [31, 31, 31, 31]],
        8 => [22, [38, 38, 39, 39]],
        9 => [22, [36, 36, 36, 37, 37]],
    ];

    private const array ALIGNMENT = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46]];

    private const string ALPHANUMERIC = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /**
     * @param list<list<bool>> $modules the dark modules, row by row from the top
     */
    private function __construct(
        private array $modules,
    ) {
    }

    /**
     * The modules of the QR code drawn in the decompressed streams of a PDF, as `PdfStreams` gives them.
     */
    public static function fromStreams(string $streams): self
    {
        if (1 !== preg_match('/ 1 g ([\d.]+) ([\d.]+) ([\d.]+) \3 re f /', $streams, $square)) {
            throw new LogicException('No white square holds a QR code.');
        }

        [$left, $bottom, $side] = [(float) $square[1], (float) $square[2], (float) $square[3]];
        preg_match_all('/ 0 g ([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+) re f /', $streams, $rectangles, \PREG_SET_ORDER);
        $inside = array_values(array_filter($rectangles, static fn (array $r): bool => (float) $r[1] >= $left && (float) $r[1] < $left + $side && (float) $r[2] >= $bottom && (float) $r[2] < $bottom + $side));

        if ([] === $inside) {
            throw new LogicException('The white square holds no module.');
        }

        $module = $side / (int) round($side / min(array_map(static fn (array $r): float => (float) $r[4], $inside)));
        $size = (int) round($side / $module) - 8;
        $dark = [];

        foreach ($inside as [, $x, $y, $width]) {
            $row = (int) round(($bottom + $side - (float) $y) / $module) - 5;
            $column = (int) round(((float) $x - $left) / $module) - 4;

            for ($i = 0, $run = (int) round((float) $width / $module); $i < $run; ++$i) {
                $dark[$row.':'.($column + $i)] = true;
            }
        }

        return new self(self::grid($size, static fn (int $x, int $y): bool => isset($dark[$y.':'.$x])));
    }

    public function text(): string
    {
        $size = \count($this->modules);
        $version = intdiv($size - 17, 4);

        if (!isset(self::BLOCKS[$version]) || 17 + 4 * $version !== $size) {
            throw new LogicException(\sprintf('A QR code of %d modules is not one of versions 1 to 9.', $size));
        }

        [$level, $mask] = $this->format();

        if (0 !== $level) {
            throw new LogicException('The QR code is not at the error correction level M.');
        }

        return $this->segments($this->dataCodewords($version, $this->codewords($version, $mask)));
    }

    /**
     * @return array{int, int} the bits of the error correction level, and the mask
     */
    private function format(): array
    {
        $bits = 0;
        $positions = [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]];

        foreach ($positions as $i => [$x, $y]) {
            $bits |= ($this->modules[$y][$x] ? 1 : 0) << $i;
        }

        $data = ($bits ^ 0x5412) >> 10;

        return [$data >> 3, $data & 7];
    }

    /**
     * The codewords in the order the modules hold them: two columns at a time from the right, up then down.
     *
     * @return list<int>
     */
    private function codewords(int $version, int $mask): array
    {
        $size = \count($this->modules);
        $function = $this->functionModules($version);
        $bits = [];

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if (6 === $right) {
                $right = 5;
            }

            for ($vertical = 0; $vertical < $size; ++$vertical) {
                for ($j = 0; $j < 2; ++$j) {
                    $x = $right - $j;
                    $y = 0 === (($right + 1) & 2) ? $size - 1 - $vertical : $vertical;

                    if (!$function[$y][$x]) {
                        $bits[] = $this->modules[$y][$x] !== $this->masked($mask, $x, $y);
                    }
                }
            }
        }

        $codewords = [];

        foreach (array_chunk($bits, 8) as $byte) {
            if (8 === \count($byte)) {
                $codewords[] = array_reduce($byte, static fn (int $carry, bool $bit): int => ($carry << 1) | ($bit ? 1 : 0), 0);
            }
        }

        return $codewords;
    }

    /**
     * The data codewords, read back from their interleaving across the blocks; the error correction ones are left.
     *
     * @param list<int> $codewords
     *
     * @return list<int>
     */
    private function dataCodewords(int $version, array $codewords): array
    {
        $lengths = self::BLOCKS[$version][1];
        $blocks = array_fill(0, \count($lengths), []);
        $next = 0;

        for ($i = 0; $i < max($lengths); ++$i) {
            foreach ($lengths as $block => $length) {
                if ($i < $length) {
                    $blocks[$block][] = $codewords[$next++];
                }
            }
        }

        return array_merge(...$blocks);
    }

    /**
     * @param list<int> $data
     */
    private function segments(array $data): string
    {
        $bits = implode('', array_map(static fn (int $byte): string => str_pad(decbin($byte), 8, '0', \STR_PAD_LEFT), $data));
        $at = 0;
        $read = static function (int $count) use ($bits, &$at): int {
            $value = bindec(substr($bits, $at, $count));
            $at += $count;

            return (int) $value;
        };
        $text = '';

        while ($at + 4 <= \strlen($bits)) {
            $mode = $read(4);

            if (0 === $mode) {
                break;
            }

            if (1 === $mode) {
                $count = $read(10);

                for (; $count >= 3; $count -= 3) {
                    $text .= str_pad((string) $read(10), 3, '0', \STR_PAD_LEFT);
                }

                $text .= match ($count) {
                    2 => str_pad((string) $read(7), 2, '0', \STR_PAD_LEFT),
                    1 => (string) $read(4),
                    default => '',
                };
            } elseif (2 === $mode) {
                $count = $read(9);

                for (; $count >= 2; $count -= 2) {
                    $pair = $read(11);
                    $text .= self::ALPHANUMERIC[intdiv($pair, 45)].self::ALPHANUMERIC[$pair % 45];
                }

                $text .= 1 === $count ? self::ALPHANUMERIC[$read(6)] : '';
            } elseif (4 === $mode) {
                for ($count = $read(8); $count > 0; --$count) {
                    $text .= \chr($read(8) & 0xFF);
                }
            } else {
                throw new LogicException(\sprintf('The QR code holds a segment of mode %d, which this reader does not read.', $mode));
            }
        }

        return $text;
    }

    private function masked(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => 0 === ($x + $y) % 2,
            1 => 0 === $y % 2,
            2 => 0 === $x % 3,
            3 => 0 === ($x + $y) % 3,
            4 => 0 === (intdiv($x, 3) + intdiv($y, 2)) % 2,
            5 => 0 === $x * $y % 2 + $x * $y % 3,
            6 => 0 === ($x * $y % 2 + $x * $y % 3) % 2,
            default => 0 === (($x + $y) % 2 + $x * $y % 3) % 2,
        };
    }

    /**
     * The modules that carry no data: finder patterns and their separators with the format, timing patterns, alignment
     * patterns and, from version 7, the version.
     *
     * @return list<list<bool>>
     */
    private function functionModules(int $version): array
    {
        $size = \count($this->modules);
        $alignments = $this->alignmentCentres($version);

        return self::grid($size, static fn (int $x, int $y): bool => 6 === $x || 6 === $y
            || ($x <= 8 && ($y <= 8 || $y >= $size - 8))
            || ($x >= $size - 8 && $y <= 8)
            || ($version >= 7 && (($x >= $size - 11 && $x <= $size - 9 && $y <= 5) || ($y >= $size - 11 && $y <= $size - 9 && $x <= 5)))
            || [] !== array_filter($alignments, static fn (array $centre): bool => abs($x - $centre[0]) <= 2 && abs($y - $centre[1]) <= 2));
    }

    /**
     * The centres of the alignment patterns: every pair of the version's positions but the three under a finder.
     *
     * @return list<array{int, int}>
     */
    private function alignmentCentres(int $version): array
    {
        $positions = self::ALIGNMENT[$version];
        $first = $positions[0] ?? null;
        $last = $positions[\count($positions) - 1] ?? null;
        $centres = [];

        foreach ($positions as $y) {
            foreach ($positions as $x) {
                if (!(($x === $first && $y === $first) || ($x === $last && $y === $first) || ($x === $first && $y === $last))) {
                    $centres[] = [$x, $y];
                }
            }
        }

        return $centres;
    }

    /**
     * @param callable(int, int): bool $module whether the module at column x and row y is set
     *
     * @return list<list<bool>>
     */
    private static function grid(int $size, callable $module): array
    {
        $grid = [];

        for ($y = 0; $y < $size; ++$y) {
            $row = [];

            for ($x = 0; $x < $size; ++$x) {
                $row[] = $module($x, $y);
            }

            $grid[] = $row;
        }

        return $grid;
    }
}

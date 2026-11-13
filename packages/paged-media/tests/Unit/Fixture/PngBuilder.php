<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Fixture;

/**
 * Writes PNG files byte by byte, so that the tests choose the colour type, the depth, each row's filter and the chunks.
 */
final class PngBuilder
{
    public const string SIGNATURE = "\x89PNG\r\n\x1A\n";

    /**
     * @param list<string> $rows    each row's unfiltered bytes
     * @param list<int>    $filters each row's filter, applied here; 0 when none is given
     */
    public static function build(int $width, int $height, int $colorType, int $depth, array $rows, array $filters = [], int $interlace = 0, string $palette = ''): string
    {
        $channels = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$colorType] ?? 1;
        $pixelBytes = max(1, intdiv($channels * $depth, 8));
        $raw = '';
        $previous = str_repeat("\0", \strlen($rows[0] ?? ''));

        foreach ($rows as $index => $row) {
            $filter = $filters[$index] ?? 0;
            $raw .= pack('C', $filter).self::filter($filter, $row, $previous, $pixelBytes);
            $previous = $row;
        }

        return self::SIGNATURE
            .self::chunk('IHDR', pack('NNCCCCC', $width, $height, $depth, $colorType, 0, 0, $interlace))
            .('' === $palette ? '' : self::chunk('PLTE', $palette))
            .self::chunk('tEXt', "Comment\0test")
            .self::chunk('IDAT', substr((string) gzcompress($raw), 0, 10))
            .self::chunk('IDAT', substr((string) gzcompress($raw), 10))
            .self::chunk('IEND', '');
    }

    public static function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    private static function filter(int $filter, string $row, string $previous, int $pixelBytes): string
    {
        $out = '';

        for ($index = 0, $length = \strlen($row); $index < $length; ++$index) {
            $left = $index >= $pixelBytes ? \ord($row[$index - $pixelBytes]) : 0;
            $up = \ord($previous[$index]);
            $upLeft = $index >= $pixelBytes ? \ord($previous[$index - $pixelBytes]) : 0;
            $predicted = match ($filter) {
                1 => $left,
                2 => $up,
                3 => intdiv($left + $up, 2),
                4 => self::paeth($left, $up, $upLeft),
                default => 0,
            };
            $out .= pack('C', (\ord($row[$index]) - $predicted) & 0xFF);
        }

        return $out;
    }

    private static function paeth(int $left, int $up, int $upLeft): int
    {
        $estimate = $left + $up - $upLeft;
        $distances = [abs($estimate - $left), abs($estimate - $up), abs($estimate - $upLeft)];

        if ($distances[0] <= $distances[1] && $distances[0] <= $distances[2]) {
            return $left;
        }

        return $distances[1] <= $distances[2] ? $up : $upLeft;
    }
}

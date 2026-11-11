<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit\Fixture;

/**
 * The test font of `FontWriter` written to the temporary directory as a TrueType file, and the same font repackaged
 * as a collection and as an uncompressed WOFF file.
 */
final class Fonts
{
    public static function trueType(): string
    {
        return self::write('block-test.ttf', FontWriter::bytes());
    }

    public static function collection(): string
    {
        $font = self::bytes();
        $tables = self::tables($font);
        $header = 'ttcf'.pack('nnN', 1, 0, 1).pack('N', 16);

        return self::write('collection.ttc', $header.self::shifted($font, $tables, 16));
    }

    public static function woff(): string
    {
        $font = self::bytes();
        $tables = self::tables($font);
        $directory = '';
        $data = '';
        $offset = 44 + 20 * \count($tables);

        foreach ($tables as [$tag, $checksum, $start, $length]) {
            $bytes = substr($font, $start, $length);
            $padded = str_pad($bytes, (int) ceil($length / 4) * 4, "\0");
            $directory .= $tag.pack('NNNN', $offset + \strlen($data), $length, $length, $checksum);
            $data .= $padded;
        }

        $total = $offset + \strlen($data);
        $header = 'wOFF'.substr($font, 0, 4).pack('NnnN', $total, \count($tables), 0, \strlen($font)).pack('nnNNNNN', 1, 0, 0, 0, 0, 0, 0);

        return self::write('font.woff', $header.$directory.$data);
    }

    private static function bytes(): string
    {
        return FontWriter::bytes();
    }

    /**
     * @return list<array{string, int, int, int}>
     */
    private static function tables(string $font): array
    {
        $count = unpack('n', $font, 4)[1] ?? 0;
        $tables = [];

        for ($index = 0; $index < $count; ++$index) {
            $record = unpack('Nchecksum/Noffset/Nlength', $font, 16 + 16 * $index);
            \assert(false !== $record && \is_int($record['checksum']) && \is_int($record['offset']) && \is_int($record['length']));
            $tables[] = [substr($font, 12 + 16 * $index, 4), $record['checksum'], $record['offset'], $record['length']];
        }

        return $tables;
    }

    /**
     * The font with the offsets of its table records moved by the given number of bytes.
     *
     * @param list<array{string, int, int, int}> $tables
     */
    private static function shifted(string $font, array $tables, int $by): string
    {
        foreach ($tables as $index => [, , $offset]) {
            $font = substr_replace($font, pack('N', $offset + $by), 20 + 16 * $index, 4);
        }

        return $font;
    }

    private static function write(string $name, string $bytes): string
    {
        $directory = sys_get_temp_dir().'/phil-print-compositor';

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($directory.'/'.$name, $bytes);
        $path = realpath($directory.'/'.$name);
        \assert(false !== $path);

        return $path;
    }
}

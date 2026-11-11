<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding\MaxMind;

/**
 * Writes small MaxMind DB files for the tests, from the format specification: a search tree of the networks given,
 * their records, and the metadata. Networks are inserted in the order given and never overlap.
 */
final class MmdbWriter
{
    public const string MARKER = "\xAB\xCD\xEFMaxMind.com";

    private string $data = '';

    /**
     * Adds a value to the data section and gives its offset there.
     */
    public function add(mixed $value): int
    {
        $offset = \strlen($this->data);
        $this->data .= self::encode($value);

        return $offset;
    }

    /**
     * @param array<string, mixed> $networks CIDR => record, or an offset already added as `MmdbOffset`
     * @param array<mixed>         $metadata replaces the metadata the writer computes
     */
    public function write(string $path, array $networks, int $ipVersion = 6, int $recordSize = 28, array $metadata = []): void
    {
        $nodes = [[null, null]];

        foreach ($networks as $cidr => $record) {
            [$address, $prefix] = explode('/', $cidr);
            $packed = (string) inet_pton($address);
            $bits = implode('', array_map(static fn (string $byte): string => str_pad(decbin(\ord($byte[0])), 8, '0', \STR_PAD_LEFT), str_split($packed)));
            $prefix = (int) $prefix;

            if (6 === $ipVersion && 4 === \strlen($packed)) {
                $bits = str_repeat('0', 96).$bits;
                $prefix += 96;
            }

            $leaf = $record instanceof MmdbOffset ? $record : new MmdbOffset($this->add($record));
            $node = 0;

            for ($i = 0; $i < $prefix - 1; ++$i) {
                $bit = (int) $bits[$i];

                if (!\is_int($nodes[$node][$bit])) {
                    $nodes[] = [null, null];
                    $nodes[$node][$bit] = \count($nodes) - 1;
                }

                $node = $nodes[$node][$bit];
            }

            $nodes[$node][(int) $bits[$prefix - 1]] = $leaf;
        }

        $count = \count($nodes);
        $value = static fn (int|MmdbOffset|null $child): int => match (true) {
            null === $child => $count,
            $child instanceof MmdbOffset => $count + 16 + $child->offset,
            default => $child,
        };
        $tree = implode('', array_map(static fn (array $node): string => self::node($value($node[0]), $value($node[1]), $recordSize), $nodes));

        file_put_contents($path, $tree.str_repeat("\0", 16).$this->data.self::MARKER.self::encode($metadata + [
            'binary_format_major_version' => 2,
            'binary_format_minor_version' => 0,
            'build_epoch' => 1_762_214_400,
            'database_type' => 'GeoLite2-City',
            'description' => ['en' => 'Test database'],
            'ip_version' => $ipVersion,
            'languages' => ['en', 'fr'],
            'node_count' => $count,
            'record_size' => $recordSize,
        ]));
    }

    public static function node(int $left, int $right, int $recordSize): string
    {
        return match ($recordSize) {
            24 => substr(pack('N', $left), 1).substr(pack('N', $right), 1),
            28 => substr(pack('N', $left), 1).\chr((($left >> 24) & 0xF) << 4 | (($right >> 24) & 0xF)).substr(pack('N', $right), 1),
            default => pack('N', $left).pack('N', $right),
        };
    }

    public static function encode(mixed $value): string
    {
        return match (true) {
            $value instanceof MmdbRaw => $value->bytes,
            \is_string($value) => self::control(2, \strlen($value)).$value,
            \is_float($value) => self::control(3, 8).pack('E', $value),
            \is_bool($value) => self::control(14, (int) $value),
            \is_int($value) && $value < 0 => self::control(8, 4).pack('N', $value & 0xFFFF_FFFF),
            \is_int($value) && $value > 0xFFFF_FFFF => self::control(9, 8).pack('J', $value),
            \is_int($value) => self::control(6, 4).pack('N', $value),
            \is_array($value) && array_is_list($value) => self::control(11, \count($value)).implode('', array_map(self::encode(...), $value)),
            \is_array($value) => self::control(7, \count($value)).implode('', array_map(static fn (int|string $key, mixed $item): string => self::encode((string) $key).self::encode($item), array_keys($value), $value)),
            default => '',
        };
    }

    public static function control(int $type, int $size): string
    {
        [$bits, $extra] = match (true) {
            $size < 29 => [$size, ''],
            $size < 285 => [29, pack('C', $size - 29)],
            $size < 65_821 => [30, pack('n', $size - 285)],
            default => [31, substr(pack('N', $size - 65_821), 1)],
        };

        return $type <= 7 ? pack('C', ($type << 5) | $bits).$extra : pack('CC', $bits, $type - 7).$extra;
    }

    /**
     * A pointer to an offset of the data section, in one of its four sizes.
     */
    public static function pointer(int $offset, int $size): MmdbRaw
    {
        return new MmdbRaw(match ($size) {
            0 => \chr(0x20 | (($offset >> 8) & 0x7)).\chr($offset & 0xFF),
            1 => \chr(0x28 | ((($offset - 2_048) >> 16) & 0x7)).substr(pack('N', $offset - 2_048), 2),
            2 => \chr(0x30 | ((($offset - 526_336) >> 24) & 0x7)).substr(pack('N', $offset - 526_336), 1),
            default => \chr(0x38).pack('N', $offset),
        });
    }
}

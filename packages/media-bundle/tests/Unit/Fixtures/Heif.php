<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures;

use PHPUnit\Framework\Assert;

/**
 * A HEIF file written field by field, and read back by a reader that shares nothing with the writer under test: the
 * tests vary the widths of each field, which a rendering by an encoder never does.
 *
 * Layout: `ftyp`, the `mdat` of the extents stored before the `meta` box, `meta`, then the `mdat` of the extents stored
 * after it. An extent is `['data' => bytes, 'where' => 'before'|'after']`, or `['offset' => int, 'length' => int]` as it
 * is written, for an item whose bytes are not in the file.
 *
 * @phpstan-type Extent array{data: string, where: string}|array{offset: int, length: int}
 * @phpstan-type ItemSpec array{id: int, type?: string, method?: int, reference?: int, extents: list<Extent>, infe?: bool, iloc?: bool}
 * @phpstan-type References array{version: int, references: list<array{string, int, list<int>}>}
 * @phpstan-type Options array{
 *     iloc?: int, offset?: int, length?: int, base?: int, index?: int, iinf?: int, infe?: int, pitm?: int,
 *     primary?: int, items?: list<ItemSpec>, iref?: References|null, order?: list<string>, entries?: int,
 *     raw?: array<string, string>, mdat?: bool
 * }
 * @phpstan-type Resolved array{
 *     iloc: int, offset: int, length: int, base: int, index: int, iinf: int, infe: int, pitm: int,
 *     primary: int, items: array<int, ItemSpec>, iref: References|null, order: list<string>, entries: int,
 *     raw: array<string, string>, mdat: bool
 * }
 * @phpstan-type Location array{method: int, reference: int, base: int, extents: list<array{int, int, int}>}
 * @phpstan-type Sizes array{offset: int, length: int, base: int, index: int}
 * @phpstan-type Read array{
 *     primary: int, order: list<int>, types: array<int, string>, data: array<int, string>, protection: array<int, int>,
 *     content: array<int, string>, locations: array<int, Location>, references: list<array{string, int, list<int>}>,
 *     versions: array{iloc: int, iinf: int, pitm: int, iref: int|null}, sizes: Sizes, count: int, metaEnd: int
 * }
 */
final class Heif
{
    public const string PACKET = '<x:xmpmeta>packet</x:xmpmeta>';

    /**
     * @param Options $options
     */
    public static function build(array $options = []): string
    {
        $resolved = self::resolve($options);
        $ftyp = self::box('ftyp', 'avif'."\0\0\0\0".'avifmif1');
        $before = '';
        $after = '';
        $placed = [];

        foreach ($resolved['items'] as $number => $item) {
            foreach ($item['extents'] as $index => $extent) {
                if (!isset($extent['data'])) {
                    continue;
                }

                if ('before' === $extent['where']) {
                    $placed[$number][$index] = [true, \strlen($before)];
                    $before .= $extent['data'];
                } else {
                    $placed[$number][$index] = [false, \strlen($after)];
                    $after .= $extent['data'];
                }
            }
        }

        $beforeBox = '' === $before ? '' : self::box('mdat', $before);
        $metaLength = \strlen(self::meta($resolved, $placed, 0, 0));
        $beforeStart = \strlen($ftyp) + ('' === $before ? 0 : 8);
        $afterStart = \strlen($ftyp) + \strlen($beforeBox) + $metaLength + 8;

        return $ftyp.$beforeBox.self::meta($resolved, $placed, $beforeStart, $afterStart).($resolved['mdat'] ? self::box('mdat', $after) : '');
    }

    /**
     * What the reader finds: the primary item, each item's type and bytes, its location fields and the references.
     *
     * @return Read
     */
    public static function read(string $file): array
    {
        $meta = null;

        foreach (self::boxes($file, 0, \strlen($file)) as [$type, , $payload, $end]) {
            if ('meta' === $type) {
                $meta = [$payload, $end];
            }
        }

        Assert::assertNotNull($meta);
        $children = [];

        foreach (self::boxes($file, $meta[0] + 4, $meta[1]) as [$type, , $payload, $end]) {
            $children[$type] = [$payload, $end];
        }

        $pitm = $children['pitm'][0];
        $pitmVersion = self::uint($file, $pitm, 1);
        $entries = self::entries($file, $children['iinf'][0], $children['iinf'][1]);
        $locations = self::locations($file, $children['iloc'][0], $children['iloc'][1]);
        $references = isset($children['iref']) ? self::references($file, $children['iref'][0], $children['iref'][1]) : [null, []];

        return [
            'primary' => self::uint($file, $pitm + 4, 0 === $pitmVersion ? 2 : 4),
            'order' => $entries['order'],
            'types' => $entries['types'],
            'data' => $locations['data'],
            'protection' => $entries['protection'],
            'content' => $entries['content'],
            'locations' => $locations['locations'],
            'references' => $references[1],
            'versions' => ['iloc' => $locations['version'], 'iinf' => $entries['version'], 'pitm' => $pitmVersion, 'iref' => $references[0]],
            'sizes' => $locations['sizes'],
            'count' => $entries['count'],
            'metaEnd' => $meta[1],
        ];
    }

    public static function box(string $type, string $payload): string
    {
        return pack('N', 8 + \strlen($payload)).$type.$payload;
    }

    /**
     * The bytes of an unsigned integer in `$size` bytes, 0, 2, 4 or 8.
     */
    public static function bytes(int $value, int $size): string
    {
        return match ($size) {
            0 => '',
            2 => pack('n', $value),
            4 => pack('N', $value),
            default => pack('J', $value),
        };
    }

    /**
     * @param Options $options
     *
     * @return Resolved
     */
    private static function resolve(array $options): array
    {
        $defaults = [
            'iloc' => 0, 'offset' => 4, 'length' => 4, 'base' => 0, 'index' => 0, 'iinf' => 0, 'infe' => 2, 'pitm' => 0,
            'primary' => 1, 'iref' => null, 'order' => ['hdlr', 'pitm', 'iinf', 'iloc', 'iref'], 'entries' => 0, 'raw' => [],
            'mdat' => true, 'items' => [['id' => 1, 'extents' => [['data' => 'primary image', 'where' => 'after']]]],
        ];

        return [...$defaults, ...$options];
    }

    /**
     * @param Resolved                                 $options
     * @param array<int, array<int, array{bool, int}>> $placed  where each extent's bytes lie: before or after, and where
     */
    private static function meta(array $options, array $placed, int $beforeStart, int $afterStart): string
    {
        $children = [
            'hdlr' => self::box('hdlr', "\0\0\0\0\0\0\0\0pict".str_repeat("\0", 13)),
            'pitm' => self::box('pitm', \chr($options['pitm'] & 0xFF)."\0\0\0".self::bytes($options['primary'], 0 === $options['pitm'] ? 2 : 4)),
            'iinf' => self::iinf($options),
            'iloc' => self::iloc($options, $placed, $beforeStart, $afterStart),
        ];

        if (null !== $options['iref']) {
            $children['iref'] = self::iref($options['iref']);
        }

        foreach ($options['raw'] as $type => $payload) {
            $children[$type] = self::box($type, $payload);
        }

        $body = "\0\0\0\0";

        foreach ($options['order'] as $type) {
            $body .= $children[$type] ?? '';
        }

        return self::box('meta', $body);
    }

    /**
     * @param Resolved $options
     */
    private static function iinf(array $options): string
    {
        $listed = array_filter($options['items'], static fn (array $item): bool => $item['infe'] ?? true);
        $count = \count($listed) + $options['entries'];
        $body = \chr($options['iinf'] & 0xFF)."\0\0\0".self::bytes($count, 0 === $options['iinf'] ? 2 : 4);

        foreach ($listed as $item) {
            $body .= self::infe($options['infe'], $item['id'], $item['type'] ?? 'av01');
        }

        return self::box('iinf', $body.str_repeat(self::infe($options['infe'], 1, 'av01'), $options['entries']));
    }

    private static function infe(int $version, int $id, string $type): string
    {
        return self::box('infe', \chr($version & 0xFF)."\0\0\0".self::bytes($id, $version < 3 ? 2 : 4).pack('n', 0).$type."\0");
    }

    /**
     * @param Resolved                                 $options
     * @param array<int, array<int, array{bool, int}>> $placed
     */
    private static function iloc(array $options, array $placed, int $beforeStart, int $afterStart): string
    {
        $version = $options['iloc'];
        $items = array_filter($options['items'], static fn (array $item): bool => $item['iloc'] ?? true);
        $sizes = \chr(($options['offset'] << 4 | $options['length']) & 0xFF).\chr(($options['base'] << 4 | (0 === $version ? 0 : $options['index'])) & 0xFF);
        $body = \chr($version & 0xFF)."\0\0\0".$sizes.self::bytes(\count($items), 2 === $version ? 4 : 2);

        foreach ($items as $number => $item) {
            $absolute = [];

            foreach ($item['extents'] as $index => $extent) {
                if (isset($extent['data'])) {
                    [$isBefore, $inside] = $placed[$number][$index];
                    $absolute[$index] = ($isBefore ? $beforeStart : $afterStart) + $inside;
                }
            }

            $base = $options['base'] > 0 && [] !== $absolute ? max(0, min($absolute) - 5) : 0;
            $body .= self::bytes($item['id'], 2 === $version ? 4 : 2).($version > 0 ? pack('n', 0xF000 | ($item['method'] ?? 0)) : '');
            $body .= pack('n', $item['reference'] ?? 0).self::bytes($base, $options['base']).pack('n', \count($item['extents']));

            foreach ($item['extents'] as $index => $extent) {
                $body .= self::extent($options, $extent, ($absolute[$index] ?? 0) - $base);
            }
        }

        return self::box('iloc', $body);
    }

    /**
     * @param Resolved $options
     * @param Extent   $extent
     */
    private static function extent(array $options, array $extent, int $inside): string
    {
        $index = self::bytes($options['iloc'] > 0 ? 7 : 0, $options['iloc'] > 0 ? $options['index'] : 0);

        if (isset($extent['data'])) {
            return $index.self::bytes($inside, $options['offset']).self::bytes(\strlen($extent['data']), $options['length']);
        }

        return $index.self::bytes($extent['offset'], $options['offset']).self::bytes($extent['length'], $options['length']);
    }

    /**
     * @param References $iref
     */
    private static function iref(array $iref): string
    {
        $width = 0 === $iref['version'] ? 2 : 4;
        $body = \chr($iref['version'] & 0xFF)."\0\0\0";

        foreach ($iref['references'] as [$type, $from, $targets]) {
            $payload = self::bytes($from, $width).pack('n', \count($targets));

            foreach ($targets as $target) {
                $payload .= self::bytes($target, $width);
            }

            $body .= self::box($type, $payload);
        }

        return self::box('iref', $body);
    }

    /**
     * @return array{version: int, count: int, order: list<int>, types: array<int, string>, protection: array<int, int>, content: array<int, string>}
     */
    private static function entries(string $file, int $payload, int $end): array
    {
        $version = self::uint($file, $payload, 1);
        $read = ['version' => $version, 'count' => self::uint($file, $payload + 4, 0 === $version ? 2 : 4), 'order' => [], 'types' => [], 'protection' => [], 'content' => []];

        foreach (self::boxes($file, $payload + (0 === $version ? 6 : 8), $end) as [, , $infe, $stop]) {
            $width = self::uint($file, $infe, 1) < 3 ? 2 : 4;
            $id = self::uint($file, $infe + 4, $width);
            $names = explode("\0", substr($file, $infe + 10 + $width, $stop - $infe - 10 - $width));
            $read['order'][] = $id;
            $read['protection'][$id] = self::uint($file, $infe + 4 + $width, 2);
            $read['types'][$id] = substr($file, $infe + 6 + $width, 4);
            $read['content'][$id] = $names[1] ?? '';
        }

        return $read;
    }

    /**
     * @return array{version: int, sizes: Sizes, locations: array<int, Location>, data: array<int, string>}
     */
    private static function locations(string $file, int $payload, int $end): array
    {
        $version = self::uint($file, $payload, 1);
        $sizes = [
            'offset' => self::uint($file, $payload + 4, 1) >> 4,
            'length' => self::uint($file, $payload + 4, 1) & 15,
            'base' => self::uint($file, $payload + 5, 1) >> 4,
            'index' => $version > 0 ? self::uint($file, $payload + 5, 1) & 15 : 0,
        ];
        $wide = 2 === $version ? 4 : 2;
        $items = self::uint($file, $payload + 6, $wide);
        $cursor = $payload + 6 + $wide;
        $read = ['version' => $version, 'sizes' => $sizes, 'locations' => [], 'data' => []];

        for ($item = 0; $item < $items; ++$item) {
            $id = self::uint($file, $cursor, $wide);
            $cursor += $wide;
            $method = $version > 0 ? self::uint($file, $cursor, 2) & 15 : 0;
            $cursor += $version > 0 ? 2 : 0;
            $reference = self::uint($file, $cursor, 2);
            $base = self::uint($file, $cursor + 2, $sizes['base']);
            $extentCount = self::uint($file, $cursor + 2 + $sizes['base'], 2);
            $cursor += 4 + $sizes['base'];
            $extents = [];
            $bytes = '';

            for ($extent = 0; $extent < $extentCount; ++$extent) {
                $offset = self::uint($file, $cursor + $sizes['index'], $sizes['offset']);
                $length = self::uint($file, $cursor + $sizes['index'] + $sizes['offset'], $sizes['length']);
                $extents[] = [self::uint($file, $cursor, $sizes['index']), $offset, $length];
                $bytes .= substr($file, $base + $offset, $length);
                $cursor += $sizes['index'] + $sizes['offset'] + $sizes['length'];
            }

            $read['locations'][$id] = ['method' => $method, 'reference' => $reference, 'base' => $base, 'extents' => $extents];
            $read['data'][$id] = $bytes;
        }

        Assert::assertSame($end, $cursor);

        return $read;
    }

    /**
     * @return array{int, list<array{string, int, list<int>}>} the version of the box and its references
     */
    private static function references(string $file, int $payload, int $end): array
    {
        $version = self::uint($file, $payload, 1);
        $width = 0 === $version ? 2 : 4;
        $references = [];

        foreach (self::boxes($file, $payload + 4, $end) as [$type, , $reference, $stop]) {
            $total = self::uint($file, $reference + $width, 2);
            $targets = [];

            for ($target = 0; $target < $total; ++$target) {
                $targets[] = self::uint($file, $reference + $width + 2 + $target * $width, $width);
            }

            Assert::assertSame($stop, $reference + $width + 2 + $total * $width);
            $references[] = [$type, self::uint($file, $reference, $width), $targets];
        }

        return [$version, $references];
    }

    /**
     * @return list<array{string, int, int, int}>
     */
    private static function boxes(string $file, int $from, int $to): array
    {
        $boxes = [];

        for ($offset = $from; $offset < $to;) {
            $size = self::uint($file, $offset, 4);

            if ($size < 8) {
                Assert::fail('A box is shorter than its header.');
            }

            $boxes[] = [substr($file, $offset + 4, 4), $offset, $offset + 8, $offset + $size];
            $offset += $size;
        }

        return $boxes;
    }

    private static function uint(string $file, int $offset, int $size): int
    {
        if ($offset + $size > \strlen($file)) {
            Assert::fail('A field is read past the end of the file.');
        }

        $value = 0;

        for ($index = 0; $index < $size; ++$index) {
            $value = $value * 256 + \ord($file[$offset + $index]);
        }

        return $value;
    }
}

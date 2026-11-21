<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

/**
 * The `iloc` box of a HEIF file: where the bytes of each item lie, read, and written again with the extents stored after
 * the `meta` box moved by its growth and one more item.
 *
 * @internal
 *
 * @phpstan-type Item array{id: int, method: int, reference: int, base: int, extents: list<array{int, int, int}>}
 * @phpstan-type Sizes array{offset: int, length: int, base: int, index: int}
 * @phpstan-type Locations array{version: int, head: string, sizes: Sizes, items: list<Item>}
 */
final class ItemLocations
{
    /** The largest value of each field size, in bytes. */
    private const array MAX = [0 => 0, 4 => 0xFFFFFFFF, 8 => \PHP_INT_MAX];

    /**
     * @param array{string, int, int, int} $iloc
     *
     * @return Locations|null
     */
    public static function read(string $image, array $iloc): ?array
    {
        [, , $payload, $end] = $iloc;

        $version = Bytes::bigEndian($image, $payload, 1);
        $indexed = 1 === $version || 2 === $version;
        $sizes = [
            'offset' => Bytes::bigEndian($image, $payload + 4, 1) >> 4,
            'length' => Bytes::bigEndian($image, $payload + 4, 1) & 0xF,
            'base' => Bytes::bigEndian($image, $payload + 5, 1) >> 4,
            'index' => $indexed ? Bytes::bigEndian($image, $payload + 5, 1) & 0xF : 0,
        ];

        if ($version > 2 || array_any($sizes, static fn (int $size): bool => !isset(self::MAX[$size]))) {
            return null;
        }

        $wide = 2 === $version ? 4 : 2;
        $offset = $payload + 6;
        $count = IsoBoxes::uint($image, $offset, $wide, $end);
        $offset += $wide;
        $items = [];

        for ($item = 0; null !== $count && $item < $count; ++$item) {
            $id = IsoBoxes::uint($image, $offset, $wide, $end);
            $offset += $wide;
            $method = $indexed ? IsoBoxes::uint($image, $offset, 2, $end) : 0;
            $offset += $indexed ? 2 : 0;
            $reference = IsoBoxes::uint($image, $offset, 2, $end);
            $base = IsoBoxes::uint($image, $offset + 2, $sizes['base'], $end);
            $offset += 2 + $sizes['base'];
            $extentCount = IsoBoxes::uint($image, $offset, 2, $end);
            $offset += 2;

            if (\in_array(null, [$id, $method, $reference, $base, $extentCount], true)) {
                return null;
            }

            $extents = [];

            for ($extent = 0; $extent < $extentCount; ++$extent) {
                $index = IsoBoxes::uint($image, $offset, $sizes['index'], $end);
                $at = IsoBoxes::uint($image, $offset + $sizes['index'], $sizes['offset'], $end);
                $size = IsoBoxes::uint($image, $offset + $sizes['index'] + $sizes['offset'], $sizes['length'], $end);
                $offset += $sizes['index'] + $sizes['offset'] + $sizes['length'];

                if (\in_array(null, [$index, $at, $size], true)) {
                    return null;
                }

                $extents[] = [$index, $at, $size];
            }

            $items[] = ['id' => $id, 'method' => $method & 0xF, 'reference' => $reference, 'base' => $base, 'extents' => $extents];
        }

        return null === $count || $offset !== $end ? null : ['version' => $version, 'head' => substr($image, $payload, 6), 'sizes' => $sizes, 'items' => $items];
    }

    /**
     * The `iloc` box with the extents stored after the `meta` box moved, and the new item's single extent.
     *
     * @param Locations $locations
     */
    public static function write(array $locations, int $metaEnd, int $delta, int $at, int $id, int $length): ?string
    {
        $sizes = $locations['sizes'];

        if (0 === $sizes['offset'] || 0 === $sizes['length']) {
            return null;
        }

        $items = $locations['items'];
        $items[] = ['id' => $id, 'method' => 0, 'reference' => 0, 'base' => 0, 'extents' => [[0, $at, $length]]];
        $wide = 2 === $locations['version'] ? 'N' : 'n';
        $body = $locations['head'].pack($wide, \count($items));

        foreach ($items as $number => $item) {
            $item = $number === \count($items) - 1 ? $item : self::moved($item, $metaEnd, $delta, $sizes);

            if (null === $item) {
                return null;
            }

            $body .= pack($wide, $item['id']).(1 === $locations['version'] || 2 === $locations['version'] ? pack('n', $item['method']) : '')
                .pack('n', $item['reference']).IsoBoxes::bytes($item['base'], $sizes['base']).pack('n', \count($item['extents']));

            foreach ($item['extents'] as [$index, $offset, $size]) {
                $body .= IsoBoxes::bytes($index, $sizes['index']).IsoBoxes::bytes($offset, $sizes['offset']).IsoBoxes::bytes($size, $sizes['length']);
            }
        }

        return pack('N', 8 + \strlen($body)).'iloc'.$body;
    }

    /**
     * An item whose extents in this file lie after the `meta` box, moved by the growth of the box.
     *
     * @param array{id: int, method: int, reference: int, base: int, extents: list<array{int, int, int}>} $item
     * @param array{offset: int, length: int, base: int, index: int}                                      $sizes
     *
     * @return array{id: int, method: int, reference: int, base: int, extents: list<array{int, int, int}>}|null
     */
    private static function moved(array $item, int $metaEnd, int $delta, array $sizes): ?array
    {
        if (0 !== $item['method'] || 0 !== $item['reference']) {
            return $item;
        }

        $after = array_map(static fn (array $extent): bool => $item['base'] + $extent[1] >= $metaEnd, $item['extents']);

        if (array_any($item['extents'], static fn (array $extent): bool => 0 === $extent[2])) {
            return null;
        }

        if ([] !== $after && !\in_array(false, $after, true) && $sizes['base'] > 0) {
            $item['base'] += $delta;

            return $item['base'] > self::MAX[$sizes['base']] ? null : $item;
        }

        foreach ($item['extents'] as $index => $extent) {
            if ($after[$index]) {
                $item['extents'][$index][1] += $delta;

                if ($item['extents'][$index][1] > self::MAX[$sizes['offset']]) {
                    return null;
                }
            }
        }

        return $item;
    }
}

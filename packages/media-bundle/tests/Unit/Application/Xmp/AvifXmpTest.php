<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application\Xmp;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use Phil\MediaBundle\Application\Xmp\AvifXmp;
use Phil\MediaBundle\Application\Xmp\Bytes;
use Phil\MediaBundle\Application\Xmp\IsoBoxes;
use Phil\MediaBundle\Application\Xmp\ItemLocations;
use Phil\MediaBundle\Tests\Unit\Fixtures\Heif;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The AVIF writer on files written field by field: every width of every field of the item boxes, and the limits of
 * the identifiers and the counts.
 *
 * @phpstan-import-type Options from Heif
 * @phpstan-import-type ItemSpec from Heif
 * @phpstan-import-type Location from Heif
 */
#[CoversClass(AvifXmp::class)]
#[CoversClass(ItemLocations::class)]
#[CoversClass(IsoBoxes::class)]
#[CoversClass(Bytes::class)]
final class AvifXmpTest extends TestCase
{
    /**
     * @return list<ItemSpec>
     */
    private static function items(): array
    {
        return [
            ['id' => 258, 'extents' => [['data' => 'primary image', 'where' => 'after']]],
            ['id' => 259, 'extents' => [['data' => 'part one', 'where' => 'before'], ['data' => 'part two', 'where' => 'after']]],
            ['id' => 260, 'extents' => [['data' => 'only before', 'where' => 'before']]],
            ['id' => 261, 'extents' => [['data' => 'two after a', 'where' => 'after'], ['data' => 'two after b', 'where' => 'after']]],
            ['id' => 262, 'method' => 1, 'extents' => [['offset' => 5, 'length' => 3]]],
            ['id' => 263, 'reference' => 1, 'extents' => [['offset' => 9, 'length' => 4]]],
            ['id' => 264, 'extents' => []],
        ];
    }

    /**
     * @return iterable<string, array{Options}>
     */
    public static function layouts(): iterable
    {
        $base = ['primary' => 258, 'items' => self::items()];

        yield 'iloc 0, 4-byte offsets and lengths' => [$base + ['iloc' => 0, 'offset' => 4, 'length' => 4, 'base' => 0]];
        yield 'iloc 0, 8-byte offsets, 4-byte base' => [$base + ['iloc' => 0, 'offset' => 8, 'length' => 4, 'base' => 4]];
        yield 'iloc 0, 4-byte base, references first' => [$base + ['iloc' => 0, 'offset' => 4, 'length' => 8, 'base' => 4, 'order' => ['iref', 'iloc', 'iinf', 'pitm', 'hdlr'], 'iref' => ['version' => 0, 'references' => [['auxl', 259, [258]], ['thmb', 260, [258, 259]]]]]];
        yield 'iloc 1, 8-byte fields, indexes of 4' => [$base + ['iloc' => 1, 'offset' => 8, 'length' => 8, 'base' => 8, 'index' => 4]];
        yield 'iloc 1, 8-byte indexes, wide references' => [$base + ['iloc' => 1, 'offset' => 8, 'length' => 4, 'base' => 4, 'index' => 8, 'iref' => ['version' => 1, 'references' => [['auxl', 259, [258]]]]]];
        yield 'iloc 1, no base, no index' => [$base + ['iloc' => 1, 'offset' => 4, 'length' => 4, 'base' => 0, 'index' => 0]];
        yield 'iloc 2, wide identifiers' => [$base + ['iloc' => 2, 'offset' => 4, 'length' => 4, 'base' => 4, 'index' => 4, 'iinf' => 1, 'infe' => 3, 'pitm' => 1]];
        yield 'iloc 2, 8-byte fields, no base' => [$base + ['iloc' => 2, 'offset' => 8, 'length' => 8, 'base' => 0, 'index' => 8, 'iinf' => 1, 'infe' => 3, 'pitm' => 1, 'iref' => ['version' => 0, 'references' => [['auxl', 259, [258]]]]]];
        yield 'iloc 0, wide iinf and primary, infe 3' => [$base + ['iloc' => 0, 'iinf' => 1, 'infe' => 3, 'pitm' => 1, 'iref' => ['version' => 1, 'references' => []]]];
    }

    /**
     * @param Options $options
     */
    #[DataProvider('layouts')]
    public function testTheItemIsAddedAndEveryOtherItemKeepsItsBytes(array $options): void
    {
        $file = Heif::build($options);
        $before = Heif::read($file);
        $written = AvifXmp::write($file, Heif::PACKET);

        self::assertNotNull($written);
        $after = Heif::read($written);
        $delta = $after['metaEnd'] - $before['metaEnd'];
        $id = 265;

        self::assertGreaterThan(0, $delta);
        self::assertSame($before['primary'], $after['primary']);
        self::assertSame([...$before['versions'], 'iref' => $before['versions']['iref'] ?? 0], $after['versions']);
        self::assertSame($before['sizes'], $after['sizes']);
        self::assertSame([...$before['order'], $id], $after['order']);
        self::assertSame(\count($before['order']) + 1, $after['count']);
        self::assertSame([...$before['references'], ['cdsc', $id, [$before['primary']]]], $after['references']);

        foreach ($before['data'] as $old => $bytes) {
            self::assertSame($bytes, $after['data'][$old], 'item '.$old);
            self::assertSame($before['types'][$old], $after['types'][$old]);
            self::assertSame($before['locations'][$old]['method'], $after['locations'][$old]['method']);
            self::assertSame($before['locations'][$old]['reference'], $after['locations'][$old]['reference']);
            self::assertSame($this->moved($before['locations'][$old], $before['sizes']['base'], $before['metaEnd'], $delta), $after['locations'][$old], 'item '.$old);
        }

        self::assertSame(Heif::PACKET, $after['data'][$id]);
        self::assertSame('mime', $after['types'][$id]);
        self::assertSame('application/rdf+xml', $after['content'][$id]);
        self::assertSame(0, $after['protection'][$id]);
        self::assertSame(['method' => 0, 'reference' => 0, 'base' => 0, 'extents' => [[0, \strlen($written) - \strlen(Heif::PACKET), \strlen(Heif::PACKET)]]], $after['locations'][$id]);
        self::assertStringEndsWith(pack('N', 8 + \strlen(Heif::PACKET)).'mdat'.Heif::PACKET, $written);
        self::assertSame(\strlen($file) + $delta + 8 + \strlen(Heif::PACKET), \strlen($written));
    }

    /**
     * The location of an item after the `meta` box grew by `$delta`: the base moves when every extent lies after the box
     * and the base is stored, the extents that lie after it otherwise.
     *
     * @param Location $location
     *
     * @return Location
     */
    private function moved(array $location, int $baseSize, int $metaEnd, int $delta): array
    {
        if (0 !== $location['method'] || 0 !== $location['reference']) {
            return $location;
        }

        $after = array_map(static fn (array $extent): bool => $location['base'] + $extent[1] >= $metaEnd, $location['extents']);

        if ([] !== $after && !\in_array(false, $after, true) && $baseSize > 0) {
            $location['base'] += $delta;

            return $location;
        }

        $location['extents'] = array_map(static fn (array $extent, bool $isAfter): array => [$extent[0], $isAfter ? $extent[1] + $delta : $extent[1], $extent[2]], $location['extents'], $after);

        return $location;
    }

    /**
     * @return iterable<string, array{Options, int}>
     */
    public static function identifiers(): iterable
    {
        $item = static fn (int $id, array $flags = []): array => ['id' => $id, 'extents' => [['data' => 'data '.$id, 'where' => 'after']]] + $flags;

        yield 'the primary item is the highest' => [['primary' => 9, 'items' => [$item(1)]], 10];
        yield 'the highest of the entries is not the first' => [['primary' => 1, 'items' => [$item(1), $item(9, ['iloc' => false]), $item(2)]], 10];
        yield 'the highest of the locations is not the first' => [['primary' => 1, 'items' => [$item(1), $item(2), $item(9, ['infe' => false])]], 10];
        yield 'the highest of the entries comes first' => [['primary' => 1, 'items' => [$item(9, ['iloc' => false]), $item(1), $item(2)]], 10];
        yield 'the highest of the locations comes first' => [['primary' => 1, 'items' => [$item(9, ['infe' => false]), $item(1), $item(2)]], 10];
        yield 'the last identifier that fits in 16 bits' => [['primary' => 1, 'items' => [$item(1), $item(0xFFFE)]], 0xFFFF];
        yield 'wide identifiers of an entry' => [['primary' => 1, 'iloc' => 2, 'iinf' => 1, 'infe' => 3, 'items' => [$item(1), $item(300, ['iloc' => false])]], 301];
        yield 'wide identifiers of a primary item' => [['primary' => 400, 'iloc' => 2, 'iinf' => 1, 'infe' => 3, 'pitm' => 1, 'items' => [$item(1)]], 401];
    }

    /**
     * @param Options $options
     */
    #[DataProvider('identifiers')]
    public function testTheNewItemTakesTheIdentifierAfterTheHighest(array $options, int $id): void
    {
        $file = Heif::build($options);
        $written = AvifXmp::write($file, Heif::PACKET);

        self::assertNotNull($written);
        $after = Heif::read($written);
        self::assertSame(Heif::PACKET, $after['data'][$id]);
        self::assertSame($id, array_last($after['order']));
        self::assertSame([['cdsc', $id, [$after['primary']]]], $after['references']);
    }

    public function testTheIdentifiersAreExhaustedAfterSixteenBits(): void
    {
        $file = Heif::build(['primary' => 1, 'iloc' => 2, 'iinf' => 1, 'infe' => 3, 'items' => [
            ['id' => 1, 'extents' => [['data' => 'x', 'where' => 'after']]],
            ['id' => 0xFFFF, 'extents' => [['data' => 'y', 'where' => 'after']]],
        ]]);

        self::assertNull(AvifXmp::write($file, Heif::PACKET));
    }

    /**
     * @return iterable<string, array{string, int, int, bool}>
     */
    public static function counts(): iterable
    {
        yield 'a 16-bit entry count one below the limit' => ['entries', 0xFFFE, 0, true];
        yield 'a 16-bit entry count at the limit' => ['entries', 0xFFFF, 0, false];
        yield 'a 32-bit entry count at the limit' => ['entries', 0xFFFF, 1, true];
        yield 'a 16-bit location count one below the limit' => ['locations', 0xFFFE, 0, true];
        yield 'a 16-bit location count at the limit, iloc 0' => ['locations', 0xFFFF, 0, false];
        yield 'a 16-bit location count at the limit, iloc 1' => ['locations', 0xFFFF, 1, false];
        yield 'a 32-bit location count at the limit' => ['locations', 0xFFFF, 2, true];
    }

    /**
     * The entries count in 16 bits of version 0 of the entry box, the locations in 16 bits below version 2 of the
     * location box.
     */
    #[DataProvider('counts')]
    #[MaximumDuration(3000)]
    public function testTheCountsOfEntriesAndLocationsStayInTheirFields(string $field, int $total, int $version, bool $written): void
    {
        if ('entries' === $field) {
            $options = ['primary' => 1, 'iinf' => $version, 'entries' => $total - 1, 'items' => [['id' => 1, 'extents' => [['data' => 'x', 'where' => 'after']]]]];
        } else {
            $items = [];

            for ($id = 1; $id <= $total; ++$id) {
                $items[] = ['id' => 1 + $id % 100, 'infe' => 1 === $id, 'extents' => [['offset' => 1, 'length' => 1]]];
            }

            $options = ['primary' => 1, 'iloc' => $version, 'iinf' => 1, 'items' => $items];
        }

        $file = Heif::build($options);
        $result = AvifXmp::write($file, Heif::PACKET);

        if (!$written) {
            self::assertNull($result);

            return;
        }

        self::assertNotNull($result);
        self::assertStringEndsWith('mdat'.Heif::PACKET, $result);
        self::assertGreaterThan(\strlen($file), \strlen($result));
        $after = Heif::read($result);
        self::assertSame(Heif::PACKET, $after['data'][(int) array_last($after['order'])] ?? null);
    }

    /**
     * @return iterable<string, array{Options}>
     */
    public static function unextendable(): iterable
    {
        $one = ['id' => 1, 'extents' => [['data' => 'x', 'where' => 'after']]];

        yield 'a reference box shorter than its header, at the end of the file' => [['primary' => 1, 'items' => [$one], 'mdat' => false, 'raw' => ['iref' => "\0\0"]]];
        yield 'an entry box shorter than its header' => [['primary' => 1, 'items' => [$one], 'raw' => ['iinf' => "\0\0\0"]]];
        yield 'a wide entry box shorter than its header' => [['primary' => 1, 'items' => [$one], 'raw' => ['iinf' => "\1\0\0\0\0\0\0"]]];
        yield 'an entry box that holds no entry box' => [['primary' => 1, 'items' => [$one], 'raw' => ['iinf' => "\0\0\0\0\0\1\0\0\0\4junk"]]];
        yield 'a primary item box without payload at the end of the file' => [['primary' => 1, 'items' => [$one], 'mdat' => false, 'order' => ['hdlr', 'iinf', 'iloc', 'pitm'], 'raw' => ['pitm' => '']]];
        yield 'a primary item box too short for its identifier' => [['primary' => 1, 'items' => [$one], 'raw' => ['pitm' => "\0\0\0\0\0"]]];
        yield 'a wide primary item box one byte short' => [['primary' => 1, 'items' => [$one], 'raw' => ['pitm' => "\1\0\0\0\0\0\0"]]];
        yield 'a location box without payload at the end of the file' => [['primary' => 1, 'items' => [$one], 'mdat' => false, 'order' => ['hdlr', 'pitm', 'iinf', 'iloc'], 'raw' => ['iloc' => '']]];
        yield 'a location box shorter than its header' => [['primary' => 1, 'items' => [$one], 'raw' => ['iloc' => "\0\0\0\0\x44\0\0"]]];
        yield 'a location box with a byte left over' => [['primary' => 1, 'items' => [$one], 'raw' => ['iloc' => "\0\0\0\0\x44\0\0\0\0"]]];
        yield 'a location box with an offset of 0 and a length of 4' => [['primary' => 1, 'offset' => 0, 'length' => 4, 'items' => [['id' => 1, 'extents' => [['offset' => 0, 'length' => 4]]]]]];
        yield 'a location box with an offset of 4 and a length of 0' => [['primary' => 1, 'offset' => 4, 'length' => 0, 'items' => [['id' => 1, 'extents' => [['offset' => 6, 'length' => 0]]]]]];
        yield 'a location box with a length of 5' => [['primary' => 1, 'offset' => 4, 'length' => 5, 'items' => []]];
        yield 'a location box with an index of 5' => [['primary' => 1, 'iloc' => 1, 'offset' => 4, 'length' => 4, 'index' => 5, 'items' => []]];
        yield 'a location box with a base of 5' => [['primary' => 1, 'offset' => 4, 'length' => 4, 'base' => 5, 'items' => []]];
        yield 'an extent of no length' => [['primary' => 1, 'items' => [['id' => 1, 'extents' => [['offset' => 40, 'length' => 0]]]]]];
        yield 'an extent of no length among others' => [['primary' => 1, 'items' => [['id' => 1, 'extents' => [['data' => 'ab', 'where' => 'after'], ['offset' => 40, 'length' => 0]]]]]];
    }

    /**
     * @param Options $options
     */
    #[DataProvider('unextendable')]
    public function testAFileTheWriterCannotExtendIsRefused(array $options): void
    {
        self::assertNull(AvifXmp::write(Heif::build($options), Heif::PACKET));
    }

    public function testAFileWhoseFirstBoxIsNotFtypIsRefused(): void
    {
        $file = Heif::build();

        self::assertNotNull(AvifXmp::write($file, Heif::PACKET));
        self::assertNull(AvifXmp::write(str_replace('ftyp', 'free', $file), Heif::PACKET));
    }

    public function testTheFlagsOfAVersionZeroBoxAreNotItsVersion(): void
    {
        $file = Heif::build(['primary' => 258, 'items' => [['id' => 258, 'extents' => [['data' => 'x', 'where' => 'after']]]]]);

        foreach (['pitm', 'iinf'] as $type) {
            $file[(int) strpos($file, $type) + 5] = "\x01";
        }

        $written = AvifXmp::write($file, Heif::PACKET);

        self::assertNotNull($written);
        $after = Heif::read($written);
        self::assertSame([258, 259], $after['order']);
        self::assertSame(Heif::PACKET, $after['data'][259]);
        self::assertSame([['cdsc', 259, [258]]], $after['references']);
    }

    public function testAnEmptyEntryBoxIsExtended(): void
    {
        $written = AvifXmp::write(Heif::build(['primary' => 1, 'items' => [['id' => 1, 'infe' => false, 'extents' => [['data' => 'x', 'where' => 'after']]]]]), Heif::PACKET);

        self::assertNotNull($written);
        self::assertSame([2], Heif::read($written)['order']);
    }

    public function testALocationBoxWithoutItemsIsExtended(): void
    {
        $written = AvifXmp::write(Heif::build(['primary' => 1, 'items' => [['id' => 1, 'iloc' => false, 'extents' => []]]]), Heif::PACKET);

        self::assertNotNull($written);
        self::assertSame(Heif::PACKET, Heif::read($written)['data'][2]);
    }

    public function testAFileTheWriterExtendsKeepsItsTextBeforeTheMetaBox(): void
    {
        $file = Heif::build(['items' => [['id' => 1, 'extents' => [['data' => 'before', 'where' => 'before'], ['data' => 'after', 'where' => 'after']]]]]);
        $start = (int) strpos($file, 'meta') - 4;
        $written = AvifXmp::write($file, Heif::PACKET);

        self::assertNotNull($written);
        self::assertSame(substr($file, 0, $start), substr($written, 0, $start));
        $before = Heif::read($file);
        $after = Heif::read($written);
        self::assertSame(substr($file, $before['metaEnd']), substr($written, $after['metaEnd'], \strlen($file) - $before['metaEnd']));
    }
}

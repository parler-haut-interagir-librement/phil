<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

/**
 * An XMP packet in an AVIF image, as HEIF carries metadata: an item of type `mime` and content type
 * `application/rdf+xml` that describes the primary image (`cdsc`), its bytes in an `mdat` box appended to the file. The
 * `meta` box grows, so each extent stored after it moves by as many bytes.
 *
 * The image is returned unchanged — `null` — when its structure is not one the writer extends safely: no single `meta`
 * box of a picture, a box sized to the end of the file, extents of unstated length or without an offset field, or more
 * items than the boxes count.
 *
 * @internal
 */
final class AvifXmp
{
    private const string CONTENT_TYPE = 'application/rdf+xml';

    /**
     * The image with the packet, or `null` when the writer cannot extend it.
     */
    public static function write(string $image, string $packet): ?string
    {
        $meta = self::meta($image);

        if (null === $meta) {
            return null;
        }

        [$metaStart, $metaPayload, $metaEnd, $children] = $meta;
        $primary = self::primary($image, $children['pitm']);
        $infos = self::infos($image, $children['iinf']);

        if (null === $primary || null === $infos) {
            return null;
        }

        $locations = ItemLocations::read($image, $children['iloc']);

        if (null === $locations) {
            return null;
        }

        $id = max([$primary, ...$infos['ids'], ...array_column($locations['items'], 'id')]) + 1;
        $iref = self::iref($image, $children['iref'] ?? null, $id, $primary);

        if ($id > 0xFFFF || null === $iref || (0 === $infos['version'] && \count($infos['entries']) >= 0xFFFF) || ($locations['version'] < 2 && \count($locations['items']) >= 0xFFFF)) {
            return null;
        }

        $replaced = ['iinf' => self::iinf($infos, $id).(isset($children['iref']) ? '' : $iref), 'iref' => $iref];
        // The box as it will be written, to measure how much `meta` grows: its length does not depend on the offsets. A box
        // that cannot be written is refused by the second writing.
        $measured = ItemLocations::write($locations, $metaEnd, 0, 0, $id, \strlen($packet)) ?? '';
        $delta = \strlen(self::assemble($image, $metaPayload, $children, [...$replaced, 'iloc' => $measured])) - ($metaEnd - $metaStart);
        $iloc = ItemLocations::write($locations, $metaEnd, $delta, \strlen($image) + $delta + 8, $id, \strlen($packet));

        if (null === $iloc) {
            return null;
        }

        return substr($image, 0, $metaStart).self::assemble($image, $metaPayload, $children, [...$replaced, 'iloc' => $iloc]).substr($image, $metaEnd).pack('N', 8 + \strlen($packet)).'mdat'.$packet;
    }

    /**
     * The single `meta` box of a picture after the file's `ftyp`: its start, payload start and end, and its boxes by
     * type, each type once, `hdlr`, `pitm`, `iinf` and `iloc` among them.
     *
     * @return array{int, int, int, array<string, array{string, int, int, int}>}|null
     */
    private static function meta(string $image): ?array
    {
        $top = IsoBoxes::read($image, 0, \strlen($image));

        if (null === $top || 'ftyp' !== ($top[0][0] ?? null)) {
            return null;
        }

        $metas = array_values(array_filter($top, static fn (array $box): bool => 'meta' === $box[0]));

        if (1 !== \count($metas)) {
            return null;
        }

        [, $start, $payload, $end] = $metas[0];
        $children = [];

        foreach (IsoBoxes::read($image, $payload + 4, $end) ?? [] as $child) {
            if (isset($children[$child[0]])) {
                return null;
            }

            $children[$child[0]] = $child;
        }

        if (!isset($children['hdlr'], $children['pitm'], $children['iinf'], $children['iloc']) || 'pict' !== substr($image, $children['hdlr'][2] + 8, 4)) {
            return null;
        }

        return [$start, $payload, $end, $children];
    }

    /**
     * The identifier of the primary item, from `pitm`.
     *
     * @param array{string, int, int, int} $pitm
     */
    private static function primary(string $image, array $pitm): ?int
    {
        [, , $payload, $end] = $pitm;

        return IsoBoxes::uint($image, $payload + 4, 0 === Bytes::bigEndian($image, $payload, 1) ? 2 : 4, $end);
    }

    /**
     * The `meta` box again, with its `iinf`, `iref` and `iloc` replaced.
     *
     * @param array<string, array{string, int, int, int}> $children
     * @param array<string, string>                       $replaced by type
     */
    private static function assemble(string $image, int $payload, array $children, array $replaced): string
    {
        $body = substr($image, $payload, 4);

        foreach ($children as $type => [, $start, , $end]) {
            $body .= $replaced[$type] ?? substr($image, $start, $end - $start);
        }

        return pack('N', 8 + \strlen($body)).'meta'.$body;
    }

    /**
     * @param array{string, int, int, int} $iinf
     *
     * @return array{version: int, head: string, entries: list<string>, ids: list<int>}|null
     */
    private static function infos(string $image, array $iinf): ?array
    {
        [, , $payload, $end] = $iinf;
        $version = Bytes::bigEndian($image, $payload, 1);
        $first = $payload + (0 === $version ? 6 : 8);
        $entries = $first > $end ? null : IsoBoxes::read($image, $first, $end);

        if (null === $entries) {
            return null;
        }

        $raw = [];
        $ids = [];

        foreach ($entries as [$type, $start, $content, $stop]) {
            $id = 'infe' === $type ? IsoBoxes::uint($image, $content + 4, Bytes::bigEndian($image, $content, 1) < 3 ? 2 : 4, $stop) : null;

            if (null === $id) {
                return null;
            }

            $raw[] = substr($image, $start, $stop - $start);
            $ids[] = $id;
        }

        return ['version' => $version, 'head' => substr($image, $payload, 4), 'entries' => $raw, 'ids' => $ids];
    }

    /**
     * @param array{version: int, head: string, entries: list<string>, ids: list<int>} $infos
     */
    private static function iinf(array $infos, int $id): string
    {
        $count = \count($infos['entries']) + 1;
        $infe = "\x02\0\0\0".pack('nn', $id, 0).'mime'."\0".self::CONTENT_TYPE."\0";
        $body = $infos['head'].pack(0 === $infos['version'] ? 'n' : 'N', $count).implode('', $infos['entries']).pack('N', 8 + \strlen($infe)).'infe'.$infe;

        return pack('N', 8 + \strlen($body)).'iinf'.$body;
    }

    /**
     * The `iref` box with the new item describing the primary image, added to the references already there. The
     * primary item's identifier is below the new one's, which fits in 16 bits.
     *
     * @param array{string, int, int, int}|null $iref
     */
    private static function iref(string $image, ?array $iref, int $id, int $primary): ?string
    {
        $head = null === $iref ? "\0\0\0\0" : substr($image, $iref[2], 4);

        if (4 !== \strlen($head)) {
            return null;
        }

        $wide = 0 !== \ord($head[0]);

        $reference = $wide ? pack('NnN', $id, 1, $primary) : pack('nnn', $id, 1, $primary);
        $body = $head.(null === $iref ? '' : substr($image, $iref[2] + 4, $iref[3] - $iref[2] - 4)).pack('N', 8 + \strlen($reference)).'cdsc'.$reference;

        return pack('N', 8 + \strlen($body)).'iref'.$body;
    }
}

<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures;

use DOMDocument;
use DOMElement;
use Phil\MediaBundle\Application\Xmp\Bytes;

/**
 * What the tests read of the metadata of an image: its XMP packets and their properties, and the segments, chunks or
 * boxes that could carry more.
 */
final class Metadata
{
    public const string URI = 'http://cv.iptc.org/newscodes/digitalsourcetype/';

    /**
     * Each XMP packet in the file.
     *
     * @return list<string>
     */
    public static function packets(string $image): array
    {
        preg_match_all('{<x:xmpmeta\b.*?</x:xmpmeta>}s', $image, $matches);

        return $matches[0];
    }

    /**
     * The properties of a packet, attributes and elements of each description, by qualified name.
     *
     * @return array<string, string>
     */
    public static function properties(string $packet): array
    {
        $document = new DOMDocument();

        if ('' === $packet || !$document->loadXML($packet, \LIBXML_NONET)) {
            return [];
        }

        $properties = [];

        foreach ($document->getElementsByTagNameNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'Description') as $description) {
            foreach ($description->attributes ?? [] as $attribute) {
                if ('rdf:about' !== $attribute->nodeName) {
                    $properties[$attribute->nodeName] = (string) $attribute->nodeValue;
                }
            }

            foreach ($description->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $properties[$child->nodeName] = $child->textContent;
                }
            }
        }

        return $properties;
    }

    /**
     * The markers of the segments of a JPEG image before its scan.
     *
     * @return list<int>
     */
    public static function jpegSegments(string $jpeg): array
    {
        $markers = [];
        $offset = 2;

        while ($offset + 4 <= \strlen($jpeg) && 0xDA !== \ord($jpeg[$offset + 1])) {
            $markers[] = \ord($jpeg[$offset + 1]);
            $offset += 2 + Bytes::bigEndian($jpeg, $offset + 2, 2);
        }

        return $markers;
    }

    /**
     * The types of the chunks of a PNG image.
     *
     * @return list<string>
     */
    public static function pngChunks(string $png): array
    {
        $types = [];

        for ($offset = 8; $offset + 12 <= \strlen($png); $offset += 12 + Bytes::bigEndian($png, $offset, 4)) {
            $types[] = substr($png, $offset + 4, 4);
        }

        return $types;
    }

    /**
     * The types of the chunks of a WebP image.
     *
     * @return list<string>
     */
    public static function webpChunks(string $webp): array
    {
        $types = [];

        for ($offset = 12; $offset + 8 <= \strlen($webp); $offset += 8 + $size + ($size & 1)) {
            $size = Bytes::littleEndian($webp, $offset + 4, 4);
            $types[] = substr($webp, $offset, 4);
        }

        return $types;
    }

    /**
     * A PNG chunk.
     */
    public static function pngChunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    /**
     * A WebP chunk.
     */
    public static function webpChunk(string $type, string $data): string
    {
        return $type.pack('V', \strlen($data)).$data.(1 === (\strlen($data) & 1) ? "\0" : '');
    }

    /**
     * A JPEG segment.
     *
     * @param int<0, 255> $marker
     */
    public static function jpegSegment(int $marker, string $data): string
    {
        return "\xFF".\chr($marker).pack('n', \strlen($data) + 2).$data;
    }

    /**
     * The `APP1` segment of EXIF that holds a GPS position: a TIFF directory pointing at a GPS directory with a latitude
     * reference.
     */
    public static function exifWithGps(): string
    {
        $tiff = "MM\0*\0\0\0\x08"
            ."\0\x01".pack('nnNN', 0x8825, 4, 1, 26)."\0\0\0\0"
            ."\0\x01".pack('nnN', 0x0001, 2, 2)."N\0\0\0\0\0\0\0";

        return self::jpegSegment(0xE1, "Exif\0\0".$tiff);
    }
}

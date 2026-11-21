<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

/**
 * An XMP packet in a JPEG image: an `APP1` segment after the `JFIF` header, the `APP1` segments already there — EXIF
 * and XMP —, the `APP13` segment of IPTC and the comments dropped. The other segments, the ICC profile of `APP2`
 * among them, are kept as they are.
 *
 * @internal
 */
final class JpegXmp
{
    /** What opens the `APP1` segment of an XMP packet. */
    private const string HEADER = "http://ns.adobe.com/xap/1.0/\0";

    /** The segments dropped: `APP1` (EXIF, XMP), `APP13` (IPTC) and `COM`. */
    private const array DROPPED = [0xE1, 0xED, 0xFE];

    /**
     * The image with the packet, or `null` when it is not a JPEG image the writer reads or the packet does not fit in a
     * segment.
     */
    public static function write(string $image, string $packet): ?string
    {
        $segment = self::HEADER.$packet;

        if (!str_starts_with($image, "\xFF\xD8") || \strlen($segment) > 0xFFFD) {
            return null;
        }

        $head = '';
        $kept = '';
        $offset = 2;
        $length = \strlen($image);

        while ($offset + 2 <= $length && "\xFF" === $image[$offset]) {
            $marker = \ord($image[$offset + 1]);

            if (0xDA === $marker || 0xD9 === $marker) {
                // The scan, or the end: the rest is the image itself.
                return "\xFF\xD8".$head."\xFF\xE1".pack('n', \strlen($segment) + 2).$segment.$kept.substr($image, $offset);
            }

            // A size below 2, or one that runs past the file, leaves the offset where no marker starts or past the end:
            // the loop stops and the image is refused.
            $size = Bytes::bigEndian($image, $offset + 2, 2);
            $end = $offset + 2 + $size;
            $bytes = substr($image, $offset, $end - $offset);

            if (0xE0 === $marker && '' === $kept) {
                $head .= $bytes;
            } elseif (!\in_array($marker, self::DROPPED, true)) {
                $kept .= $bytes;
            }

            $offset = $end;
        }

        return null;
    }
}

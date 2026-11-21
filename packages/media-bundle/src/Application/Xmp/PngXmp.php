<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

/**
 * An XMP packet in a PNG image: an uncompressed `iTXt` chunk of keyword `XML:com.adobe.xmp` right after the `IHDR`
 * header; the chunks of metadata already there — `eXIf`, `tIME` and the text chunks `tEXt`, `zTXt` and `iTXt` —
 * dropped. The other chunks, the colour profile of `iCCP` among them, are kept as they are.
 *
 * @internal
 */
final class PngXmp
{
    private const string SIGNATURE = "\x89PNG\r\n\x1A\n";

    private const string KEYWORD = 'XML:com.adobe.xmp';

    private const array DROPPED = ['eXIf', 'tIME', 'tEXt', 'zTXt', 'iTXt'];

    /**
     * The image with the packet, or `null` when it is not a PNG image the writer reads.
     */
    public static function write(string $image, string $packet): ?string
    {
        if (!str_starts_with($image, self::SIGNATURE)) {
            return null;
        }

        $written = self::SIGNATURE;
        $offset = 8;
        $length = \strlen($image);

        while ($offset + 12 <= $length) {
            $size = Bytes::bigEndian($image, $offset, 4);
            $type = substr($image, $offset + 4, 4);
            $end = $offset + 12 + $size;

            if ($end > $length) {
                return null;
            }

            $chunk = substr($image, $offset, $end - $offset);

            if (8 === $offset) {
                if ('IHDR' !== $type) {
                    return null;
                }

                $chunk .= self::chunk('iTXt', self::KEYWORD."\0\0\0\0\0".$packet);
            } elseif ('IEND' === $type) {
                return $written.$chunk;
            } elseif (\in_array($type, self::DROPPED, true)) {
                $chunk = '';
            }

            $written .= $chunk;
            $offset = $end;
        }

        return null;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}

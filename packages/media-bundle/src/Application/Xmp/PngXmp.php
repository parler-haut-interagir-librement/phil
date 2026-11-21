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
        $rest = substr($image, 8);
        $first = true;

        // Each chunk is cut off the rest of the file: a chunk that runs past it, or a file that ends before `IEND`, is refused.
        while ('' !== $rest) {
            $length = 12 + Bytes::bigEndian($rest, 0, 4);
            $type = substr($rest, 4, 4);

            if ($length > \strlen($rest)) {
                return null;
            }

            $chunk = substr($rest, 0, $length);
            $rest = substr($rest, $length);

            if ($first) {
                if ('IHDR' !== $type) {
                    return null;
                }

                $chunk .= self::chunk('iTXt', self::KEYWORD."\0\0\0\0\0".$packet);
            } elseif ('IEND' === $type) {
                return $written.$chunk;
            } elseif (\in_array($type, self::DROPPED, true)) {
                $chunk = '';
            }

            $first = false;
            $written .= $chunk;
        }

        return null;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}

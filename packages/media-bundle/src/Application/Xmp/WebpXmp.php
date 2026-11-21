<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

/**
 * An XMP packet in a WebP image: an `XMP ` chunk after the image data, in the extended format, which a simple image —
 * lossy `VP8 ` or lossless `VP8L` — is given with its canvas size and its alpha; the `EXIF` and `XMP ` chunks already
 * there dropped.
 *
 * @internal
 */
final class WebpXmp
{
    /** The flags of `VP8X`: an ICC profile, alpha, EXIF, XMP, animation. */
    private const int ALPHA = 0x10;

    private const int EXIF = 0x08;

    private const int XMP = 0x04;

    /**
     * The image with the packet, or `null` when it is not a WebP image the writer reads.
     */
    public static function write(string $image, string $packet): ?string
    {
        $chunks = self::chunks($image);

        if (null === $chunks || [] === $chunks) {
            return null;
        }

        [$first, $data] = $chunks[0];
        $header = match ($first) {
            'VP8X' => self::extended($data),
            'VP8 ' => self::lossy($data),
            'VP8L' => self::lossless($data),
            default => null,
        };

        if (null === $header) {
            return null;
        }

        $body = self::chunk('VP8X', $header);

        foreach ('VP8X' === $first ? \array_slice($chunks, 1) : $chunks as [$type, $bytes]) {
            if ('EXIF' !== $type && 'XMP ' !== $type) {
                $body .= self::chunk($type, $bytes);
            }
        }

        $body .= self::chunk('XMP ', $packet);

        return 'RIFF'.pack('V', 4 + \strlen($body)).'WEBP'.$body;
    }

    /**
     * @return list<array{string, string}>|null each chunk's type and data, or `null` when the file is not a WebP image
     */
    private static function chunks(string $image): ?array
    {
        $length = Bytes::littleEndian($image, 4, 4) + 8;

        if (!str_starts_with($image, 'RIFF') || 'WEBP' !== substr($image, 8, 4) || $length > \strlen($image)) {
            return null;
        }

        $chunks = [];
        $offset = 12;

        while ($offset + 8 <= $length) {
            $size = Bytes::littleEndian($image, $offset + 4, 4);

            if ($offset + 8 + $size > $length) {
                return null;
            }

            $chunks[] = [substr($image, $offset, 4), substr($image, $offset + 8, $size)];
            $offset += 8 + $size + ($size & 1);
        }

        return $chunks;
    }

    private static function chunk(string $type, string $data): string
    {
        return $type.pack('V', \strlen($data)).$data.(1 === (\strlen($data) & 1) ? "\0" : '');
    }

    /**
     * The `VP8X` header with the XMP flag set and the EXIF flag cleared.
     */
    private static function extended(string $data): ?string
    {
        return 10 === \strlen($data) ? \chr((\ord($data[0]) & ~self::EXIF) | self::XMP).substr($data, 1) : null;
    }

    private static function lossy(string $data): ?string
    {
        if (\strlen($data) < 10 || "\x9D\x01\x2A" !== substr($data, 3, 3)) {
            return null;
        }

        return self::header(Bytes::littleEndian($data, 6, 2) & 0x3FFF, Bytes::littleEndian($data, 8, 2) & 0x3FFF, false);
    }

    private static function lossless(string $data): ?string
    {
        if (\strlen($data) < 5 || "\x2F" !== $data[0]) {
            return null;
        }

        $bits = Bytes::littleEndian($data, 1, 4);

        return self::header(($bits & 0x3FFF) + 1, ($bits >> 14 & 0x3FFF) + 1, 1 === ($bits >> 28 & 1));
    }

    private static function header(int $width, int $height, bool $alpha): ?string
    {
        if ($width < 1 || $height < 1) {
            return null;
        }

        return \chr(self::XMP | ($alpha ? self::ALPHA : 0))."\0\0\0".substr(pack('V', $width - 1), 0, 3).substr(pack('V', $height - 1), 0, 3);
    }
}

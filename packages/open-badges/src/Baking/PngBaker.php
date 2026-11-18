<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Baking;

use Phil\OpenBadges\Exception\InvalidImage;

/**
 * Bakes a credential into a PNG image, and reads it back: the credential — the JSON of a document secured with a
 * Data Integrity proof, or a compact JSON Web Token — is the text of an uncompressed `iTXt` chunk whose keyword is
 * `openbadgecredential`, written before the image's end. The image keeps a single one: baking again replaces it.
 *
 * Each chunk is checked — its length, its type, its CRC — and so is the structure of the image: a single `IHDR` of 13
 * bytes first, at most one `PLTE` before the image data, `IDAT` chunks that follow each other, an empty `IEND` last and
 * nothing after it. A malformed file is refused rather than misread, on reading as on baking, and an image larger than
 * `MAX_BYTES` is refused before it is read.
 */
final class PngBaker
{
    public const string KEYWORD = 'openbadgecredential';

    public const int MAX_BYTES = 16_777_216;

    private const string SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const string LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private function __construct()
    {
    }

    /**
     * The image with the credential baked in.
     *
     * @param array<string, mixed>|string $credential a secured document, or a compact JSON Web Token
     *
     * @throws InvalidImage
     */
    public static function bake(string $png, array|string $credential): string
    {
        $text = Baked::text($credential);
        $baked = self::SIGNATURE;

        foreach (self::chunks($png) as [$type, $data]) {
            if ('iTXt' === $type && self::isCredential($data)) {
                continue;
            }

            if ('IEND' === $type) {
                $baked .= self::chunk('iTXt', self::KEYWORD."\0\0\0\0\0".$text);
            }

            $baked .= self::chunk($type, $data);
        }

        return $baked;
    }

    /**
     * The credential baked into the image, or null when it holds none.
     *
     * @throws InvalidImage
     */
    public static function extract(string $png): ?string
    {
        $found = null;

        foreach (self::chunks($png) as [$type, $data]) {
            if ('iTXt' !== $type || !self::isCredential($data)) {
                continue;
            }

            if (null !== $found) {
                throw InvalidImage::because('The image holds more than one baked credential.');
            }

            $found = self::text($data);
        }

        return $found;
    }

    private static function isCredential(string $data): bool
    {
        return str_starts_with($data, self::KEYWORD."\0");
    }

    /**
     * The text of an `iTXt` chunk holding a credential: uncompressed, after its language tag and translated keyword.
     */
    private static function text(string $data): string
    {
        $rest = substr($data, \strlen(self::KEYWORD) + 1);

        if (!str_starts_with($rest, "\0\0")) {
            throw InvalidImage::because('A baked credential is not compressed.');
        }

        $parts = explode("\0", substr($rest, 2), 3);

        if (3 !== \count($parts) || !mb_check_encoding($parts[2], 'UTF-8')) {
            throw InvalidImage::because('The text chunk of the baked credential is malformed.');
        }

        return Baked::check($parts[2]);
    }

    /**
     * The chunks of the image, up to its end, each checked, and their order.
     *
     * @return list<array{string, string}> the type and the data of each chunk
     */
    private static function chunks(string $png): array
    {
        if (\strlen($png) > self::MAX_BYTES || !str_starts_with($png, self::SIGNATURE)) {
            throw InvalidImage::because(\sprintf('The image is not a PNG of at most %d bytes.', self::MAX_BYTES));
        }

        $chunks = [];
        $offset = 8;
        // Where the image data stands: not yet, under way, or over.
        $data = 'before';

        do {
            $header = substr($png, $offset, 8);

            if (8 !== \strlen($header)) {
                throw InvalidImage::because('The PNG image ends before its IEND chunk.');
            }

            // A chunk of 16 MiB or more cannot fit in an image of at most 16 MiB: its first byte of length is zero.
            $length = (\ord($header[1]) << 16) | (\ord($header[2]) << 8) | \ord($header[3]);
            $type = substr($header, 4);
            $content = substr($png, $offset + 8, $length);

            // A chunk cut short has no CRC of its data after it.
            if ("\0" !== $header[0] || 4 !== strspn($type, self::LETTERS) || pack('N', crc32($type.$content)) !== substr($png, $offset + 8 + $length, 4)) {
                throw InvalidImage::because(\sprintf('The PNG image has a malformed chunk at byte %d.', $offset));
            }

            $data = self::order($chunks, $type, $length, $data);
            $chunks[] = [$type, $content];
            $offset += 12 + $length;
        } while ('IEND' !== $type);

        if (\strlen($png) !== $offset) {
            throw InvalidImage::because('The PNG image holds bytes after its IEND chunk.');
        }

        return $chunks;
    }

    /**
     * Checks that a chunk stands where a PNG image allows it, and returns where the image data stands after it.
     *
     * @param list<array{string, string}> $before the chunks before it
     * @param 'before'|'within'|'after'   $data   where the image data stands before it
     *
     * @return 'before'|'within'|'after'
     */
    private static function order(array $before, string $type, int $length, string $data): string
    {
        if (([] === $before) !== ('IHDR' === $type) || ('IHDR' === $type && 13 !== $length)) {
            throw InvalidImage::because('A PNG image starts with its IHDR chunk of 13 bytes, and holds no other.');
        }

        if ('PLTE' === $type && ('before' !== $data || \in_array('PLTE', array_column($before, 0), true))) {
            throw InvalidImage::because('A PNG image holds at most one PLTE chunk, before its image data.');
        }

        if ('IDAT' === $type) {
            if ('after' === $data) {
                throw InvalidImage::because('The IDAT chunks of a PNG image follow each other.');
            }

            return 'within';
        }

        if ('IEND' === $type && ('before' === $data || 0 !== $length)) {
            throw InvalidImage::because('A PNG image ends with an empty IEND chunk after its image data.');
        }

        return 'before' === $data ? 'before' : 'after';
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}

<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Exception\InvalidCardTemplate;

/**
 * An image a card template draws — its background or its logo —: a PNG, JPEG, GIF or WebP file of 10 MiB, 8,000
 * pixels a side and 16,777,216 pixels in all at most, kept with its size and its SHA-256. The size is read in the
 * header of the file before GD decodes it: a small file that claims a large image never reaches GD, which would hold
 * four bytes a pixel.
 */
final readonly class CardImage
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    public const int MAX_SIDE = 8000;

    /** The most pixels an image holds, 4,096 × 4,096: 64 MiB once GD decodes it. */
    public const int MAX_PIXELS = 16_777_216;

    private const array TYPES = [\IMAGETYPE_PNG, \IMAGETYPE_JPEG, \IMAGETYPE_GIF, \IMAGETYPE_WEBP];

    /**
     * @param int<1, 8000> $width
     * @param int<1, 8000> $height
     */
    private function __construct(
        public string $bytes,
        public string $sha256,
        public int $width,
        public int $height,
    ) {
    }

    public static function fromBytes(string $bytes, string $name = 'image'): self
    {
        $size = \strlen($bytes) > self::MAX_BYTES ? false : @getimagesizefromstring($bytes);

        if (false === $size || !\in_array($size[2], self::TYPES, true)) {
            throw InvalidCardTemplate::because(\sprintf('The %s is not a PNG, JPEG, GIF or WebP image of %d bytes at most.', $name, self::MAX_BYTES));
        }

        [$width, $height] = $size;

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw InvalidCardTemplate::because(\sprintf('The %s is %d×%d pixels; a card draws images of 1 to %d pixels a side.', $name, $width, $height, self::MAX_SIDE));
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw InvalidCardTemplate::because(\sprintf('The %s is %d×%d pixels; a card draws images of 1 to %d pixels a side and %d pixels in all.', $name, $width, $height, self::MAX_SIDE, self::MAX_PIXELS));
        }

        /** @var int<1, 8000> $width an image GD reads has one pixel at least */
        /** @var int<1, 8000> $height */
        return new self($bytes, hash('sha256', $bytes), $width, $height);
    }

    public static function fromFile(string $path): self
    {
        $size = is_file($path) && is_readable($path) ? filesize($path) : false;
        $bytes = false === $size || $size > self::MAX_BYTES ? false : file_get_contents($path);

        if (false === $bytes) {
            throw InvalidCardTemplate::because(\sprintf('The image "%s" cannot be read, or is larger than %d bytes.', $path, self::MAX_BYTES));
        }

        return self::fromBytes($bytes, \sprintf('image "%s"', $path));
    }
}

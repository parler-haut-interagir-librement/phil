<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\ValueObject;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * What is known of a file: its name, its media type, its size in bytes, and its dimensions when it is an image.
 */
final readonly class FileInfo
{
    /**
     * @param int<0, max>      $size
     * @param int<1, max>|null $width
     * @param int<1, max>|null $height
     */
    private function __construct(
        public string $filename,
        public string $mimeType,
        public int $size,
        public ?int $width,
        public ?int $height,
    ) {
    }

    public static function of(string $filename, string $mimeType, int $size, ?int $width = null, ?int $height = null): self
    {
        $filename = trim($filename);

        if ('' === $filename || mb_strlen($filename) > 255 || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw InvalidMedia::because(\sprintf('A file name is 1 to 255 characters, without a slash; "%s" is not.', $filename));
        }

        if (1 !== preg_match('{^[a-z]+/[a-z0-9.+-]+$}D', $mimeType)) {
            throw InvalidMedia::because(\sprintf('"%s" is not a media type.', $mimeType));
        }

        if ($size < 0 || (null !== $width && $width < 1) || (null !== $height && $height < 1)) {
            throw InvalidMedia::because('A size is 0 or more, and dimensions 1 or more.');
        }

        return new self($filename, $mimeType, $size, $width, $height);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    /**
     * The name reduced to letters, digits, `.`, `_` and `-`, for a storage key.
     */
    public function safeFilename(): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $this->filename) ?? '';
        $safe = trim($safe, '-.');

        return '' === $safe ? 'file' : $safe;
    }
}

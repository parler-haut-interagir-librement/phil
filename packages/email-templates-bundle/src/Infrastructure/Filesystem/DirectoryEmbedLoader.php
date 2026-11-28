<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Filesystem;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\EmbedLoader;
use Phil\EmailTemplatesBundle\Application\Rendering\EmbeddedImage;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;

/**
 * Reads the embedded images under one directory of the application: a path that leaves it, through `..` or a symbolic
 * link, is refused, as is a file larger than `maxBytes` or one that is not a PNG, a JPEG or a GIF — the formats every
 * email client shows —, recognized by its first bytes, whatever its name.
 */
final readonly class DirectoryEmbedLoader implements EmbedLoader
{
    /** The signature of each format, and its media type. */
    private const array SIGNATURES = [
        "\x89PNG\r\n\x1a\n" => 'image/png',
        "\xFF\xD8\xFF" => 'image/jpeg',
        'GIF87a' => 'image/gif',
        'GIF89a' => 'image/gif',
    ];

    /**
     * @param int<1, max> $maxBytes
     */
    public function __construct(
        private string $directory,
        private int $maxBytes = 1_048_576,
    ) {
    }

    #[Override]
    public function load(string $name, string $path): EmbeddedImage
    {
        $root = realpath($this->directory);
        $file = false === $root ? false : realpath($root.\DIRECTORY_SEPARATOR.$path);

        if (false === $root || false === $file || !str_starts_with($file, $root.\DIRECTORY_SEPARATOR) || !is_file($file)) {
            throw RenderingFailed::because(\sprintf('The image "%s" is not a file of the images directory.', $name));
        }

        $size = filesize($file);

        if (false === $size || $size > $this->maxBytes) {
            throw RenderingFailed::because(\sprintf('The image "%s" is larger than %d KiB.', $name, intdiv($this->maxBytes, 1024)));
        }

        $body = file_get_contents($file);

        if (false === $body) {
            throw RenderingFailed::because(\sprintf('The image "%s" cannot be read.', $name));
        }

        foreach (self::SIGNATURES as $signature => $type) {
            if (str_starts_with($body, $signature)) {
                return new EmbeddedImage($name, $body, $type);
            }
        }

        throw RenderingFailed::because(\sprintf('The image "%s" is not a PNG, a JPEG or a GIF.', $name));
    }
}

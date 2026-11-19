<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * An image taken out of an imported document: its path in the source, its media type read from its bytes, its bytes,
 * and the text alternative the author gave it.
 */
final readonly class Media
{
    /** The image types a browser shows, which become image blocks. */
    public const array WEB_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml', 'image/bmp'];

    /** The extensions a file of each web type may carry, the one added first. */
    private const array EXTENSIONS = [
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg', 'jpe'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'image/svg+xml' => ['svg'],
        'image/bmp' => ['bmp'],
    ];

    public string $mediaType;

    public function __construct(
        public string $path,
        public string $contents,
        public string $description = '',
    ) {
        $this->mediaType = self::mediaTypeOf($contents);
    }

    /**
     * The media type of an image from its first bytes; `application/octet-stream` when they are not those of an
     * image this library knows.
     */
    public static function mediaTypeOf(string $contents): string
    {
        $start = ltrim(substr($contents, 0, 1024));

        return match (true) {
            str_starts_with($contents, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($contents, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($contents, 'GIF8') => 'image/gif',
            str_starts_with($contents, 'RIFF') && 'WEBP' === substr($contents, 8, 4) => 'image/webp',
            \in_array(substr($contents, 4, 8), ['ftypavif', 'ftypavis'], true) => 'image/avif',
            str_starts_with($contents, 'BM') => 'image/bmp',
            str_starts_with($contents, "II*\0"), str_starts_with($contents, "MM\0*") => 'image/tiff',
            ' EMF' === substr($contents, 40, 4) => 'image/emf',
            str_starts_with($contents, "\xD7\xCD\xC6\x9A") => 'image/wmf',
            str_starts_with($start, '<svg'), str_starts_with($start, '<?xml') && str_contains($start, '<svg') => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }

    /**
     * Whether a browser shows the image: PNG, JPEG, GIF, WebP, AVIF, SVG or BMP.
     */
    public function isWebImage(): bool
    {
        return \in_array($this->mediaType, self::WEB_TYPES, true);
    }

    /**
     * The last segment of the path, with every character other than a letter, a digit, `.`, `_` or `-` replaced by
     * `-`, and ending with an extension of the type its bytes show: `chart.html` holding a PNG image is named
     * `chart.html.png`, so that a server never serves an image under the type its name in the document claims.
     */
    public function filename(): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9._-]/', '-', basename($this->path));
        $extensions = self::EXTENSIONS[$this->mediaType] ?? null;

        if (null === $extensions || \in_array(strtolower(pathinfo($name, \PATHINFO_EXTENSION)), $extensions, true)) {
            return $name;
        }

        return $name.'.'.$extensions[0];
    }

    /**
     * The same image under its path numbered, `media/image1-2.png` for `media/image1.png`, to tell it apart from
     * another image of the same name.
     */
    public function numbered(int $number): self
    {
        $directory = \dirname($this->path);
        $name = pathinfo($this->path, \PATHINFO_FILENAME).'-'.$number;
        $extension = pathinfo($this->path, \PATHINFO_EXTENSION);
        $path = ('.' === $directory ? '' : $directory.'/').$name.('' === $extension ? '' : '.'.$extension);

        return new self($path, $this->contents, $this->description);
    }
}

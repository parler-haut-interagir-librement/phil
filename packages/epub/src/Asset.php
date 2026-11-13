<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Internal\Paths;

/**
 * A file of the book other than a content document: a stylesheet, an image, a font, an audio or a video file, or a
 * WebVTT text track, at a path relative to the package directory, such as `css/book.css` or `audio/chapter-1.mp3`.
 *
 * The media type of an image, a font, an audio or a video file is read from its first bytes when not given. Images,
 * fonts, stylesheets and text tracks are in the EPUB core media types. An audio file outside them — Ogg Vorbis, FLAC,
 * WAV — needs a fallback, another audio asset the manifest names and reading systems play instead, whose chain ends in
 * a core type; a video file may have one, video being exempt from that rule. A font can be obfuscated with the IDPF
 * algorithm, keyed by the book's unique identifier.
 */
final readonly class Asset
{
    /**
     * @var array<string, list<string>>
     */
    private const array CORE = [
        'stylesheet' => ['text/css'],
        'image' => ['image/gif', 'image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'],
        'font' => ['font/ttf', 'font/otf', 'font/woff', 'font/woff2', 'application/font-sfnt', 'application/vnd.ms-opentype', 'application/font-woff'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/ogg; codecs=opus'],
        'video' => [],
        'text track' => ['text/vtt'],
    ];

    /** The media types an audio or a video asset may have outside the core media types. */
    private const string FOREIGN = '#^(audio|video)/[a-z0-9][a-z0-9.+-]*(; ?codecs="?[a-z0-9.,+-]+"?)?$#D';

    /**
     * @throws InvalidBook
     */
    private function __construct(
        public string $path,
        public string $bytes,
        public string $mediaType,
        public AssetKind $kind,
        public bool $obfuscated = false,
        public ?self $fallback = null,
    ) {
        Paths::check($path);
        $core = \in_array($mediaType, self::CORE[$kind->value], true);

        if (!$core && !$this->foreign($kind, $mediaType)) {
            throw InvalidBook::because(\sprintf('"%s" is not an EPUB core media type for a %s (%s).', $mediaType, $kind->value, $path));
        }

        if ($fallback instanceof self && ($fallback->kind !== $kind || $fallback->path === $path)) {
            throw InvalidBook::because(\sprintf('The fallback of "%s" is another %s file; "%s" is a %s.', $path, $kind->value, $fallback->path, $fallback->kind->value));
        }

        if (AssetKind::Audio === $kind && !$this->reachesCore()) {
            throw InvalidBook::because(\sprintf('The audio file "%s" is a "%s" file, outside the EPUB core media types: it needs a fallback chain that ends in MP3, AAC or Opus.', $path, $mediaType));
        }
    }

    /**
     * @throws InvalidBook
     */
    public static function stylesheet(string $path, string $css): self
    {
        return new self($path, $css, 'text/css', AssetKind::Stylesheet);
    }

    /**
     * @throws InvalidBook
     */
    public static function image(string $path, string $bytes, ?string $mediaType = null): self
    {
        return new self($path, $bytes, $mediaType ?? self::imageType($bytes, $path), AssetKind::Image);
    }

    /**
     * @throws InvalidBook
     */
    public static function font(string $path, string $bytes, bool $obfuscate = false, ?string $mediaType = null): self
    {
        return new self($path, $bytes, $mediaType ?? self::fontType($bytes, $path), AssetKind::Font, $obfuscate);
    }

    /**
     * An audio file — MP3, AAC in MP4 or Opus in Ogg, or another type with a fallback — played by an `<audio>`
     * element or read by a media overlay.
     *
     * @throws InvalidBook
     */
    public static function audio(string $path, string $bytes, ?string $mediaType = null, ?self $fallback = null): self
    {
        return new self($path, $bytes, $mediaType ?? self::audioType($bytes, $path), AssetKind::Audio, fallback: $fallback);
    }

    /**
     * A video file — MP4, WebM or Ogg — played by a `<video>` element, with a fallback when wanted.
     *
     * @throws InvalidBook
     */
    public static function video(string $path, string $bytes, ?string $mediaType = null, ?self $fallback = null): self
    {
        return new self($path, $bytes, $mediaType ?? self::videoType($bytes, $path), AssetKind::Video, fallback: $fallback);
    }

    /**
     * A WebVTT text track — captions, subtitles, descriptions, chapters — named by the `<track>` element of a video or
     * an audio element.
     *
     * @throws InvalidBook
     */
    public static function textTrack(string $path, string $vtt): self
    {
        if (!str_starts_with(ltrim($vtt, "\u{FEFF}"), 'WEBVTT')) {
            throw InvalidBook::because(\sprintf('The text track "%s" is not a WebVTT file: it starts with "WEBVTT".', $path));
        }

        return new self($path, $vtt, 'text/vtt', AssetKind::TextTrack);
    }

    /**
     * Whether the media type is an EPUB core media type.
     */
    public function isCore(): bool
    {
        return \in_array($this->mediaType, self::CORE[$this->kind->value], true);
    }

    private function foreign(AssetKind $kind, string $mediaType): bool
    {
        return \in_array($kind, [AssetKind::Audio, AssetKind::Video], true)
            && str_starts_with($mediaType, $kind->value.'/')
            && 1 === preg_match(self::FOREIGN, $mediaType);
    }

    private function reachesCore(): bool
    {
        $asset = $this;

        while ($asset instanceof self) {
            if ($asset->isCore()) {
                return true;
            }

            $asset = $asset->fallback;
        }

        return false;
    }

    /**
     * @throws InvalidBook
     */
    private static function imageType(string $bytes, string $path): string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => 'image/gif',
            str_starts_with($bytes, 'RIFF') && 'WEBP' === substr($bytes, 8, 4) => 'image/webp',
            1 === preg_match('/^\s*(<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*)*+(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/s', $bytes) => 'image/svg+xml',
            default => throw InvalidBook::because(\sprintf('The image "%s" is not a PNG, JPEG, GIF, WebP or SVG image.', $path)),
        };
    }

    /**
     * @throws InvalidBook
     */
    private static function fontType(string $bytes, string $path): string
    {
        return match (substr($bytes, 0, 4)) {
            "\0\1\0\0", 'true' => 'font/ttf',
            'OTTO' => 'font/otf',
            'wOFF' => 'font/woff',
            'wOF2' => 'font/woff2',
            default => throw InvalidBook::because(\sprintf('The font "%s" is not a TrueType, OpenType, WOFF or WOFF2 font.', $path)),
        };
    }

    /**
     * @throws InvalidBook
     */
    private static function audioType(string $bytes, string $path): string
    {
        return match (true) {
            str_starts_with($bytes, 'ID3'), 1 === preg_match('/^\xFF[\xE0-\xFF]/', $bytes) => 'audio/mpeg',
            'ftyp' === substr($bytes, 4, 4) => 'audio/mp4',
            str_starts_with($bytes, 'OggS') => str_contains(substr($bytes, 0, 64), 'OpusHead') ? 'audio/ogg; codecs=opus' : 'audio/ogg',
            str_starts_with($bytes, 'fLaC') => 'audio/flac',
            str_starts_with($bytes, 'RIFF') && 'WAVE' === substr($bytes, 8, 4) => 'audio/wav',
            default => throw InvalidBook::because(\sprintf('The audio file "%s" is not an MP3, MP4, Ogg, FLAC or WAV file.', $path)),
        };
    }

    /**
     * @throws InvalidBook
     */
    private static function videoType(string $bytes, string $path): string
    {
        return match (true) {
            'ftyp' === substr($bytes, 4, 4) => 'video/mp4',
            str_starts_with($bytes, "\x1A\x45\xDF\xA3") => 'video/webm',
            str_starts_with($bytes, 'OggS') => 'video/ogg',
            default => throw InvalidBook::because(\sprintf('The video file "%s" is not an MP4, WebM or Ogg file.', $path)),
        };
    }
}

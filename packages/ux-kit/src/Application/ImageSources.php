<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

use InvalidArgumentException;
use Symfony\Component\AssetMapper\AssetMapperInterface;

/**
 * The props of a `Picture` from the files of one image among the assets of AssetMapper, found by a convention of names:
 * `images/logo.avif`, `images/logo@2x.avif`, `images/logo.webp`, `images/logo.png`… The URLs are AssetMapper's,
 * versioned; nothing is resized or converted, the files are those the site produced.
 *
 * For each format, the file without suffix is the `1x` candidate, `@2x` and `@3x` the denser ones; when widths are
 * asked, `@480w` and the others make a set of widths instead. The first fallback format found gives `src`, its
 * `srcset` when it has other candidates, and the width and height of its file without suffix when PHP can read them; the
 * other formats, in the order asked, give the `sources`.
 */
final readonly class ImageSources
{
    /** The types of the formats, by extension. */
    private const array TYPES = [
        'avif' => 'image/avif',
        'jxl' => 'image/jxl',
        'webp' => 'image/webp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];

    private const array DENSITIES = [2, 3];

    private const array FORMATS = ['avif', 'webp'];

    private const array FALLBACKS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

    public function __construct(private AssetMapperInterface $assets)
    {
    }

    /**
     * @param string                                                                                             $path    the logical path of the image without extension, `images/logo`
     * @param array{densities?: list<int>, widths?: list<int>, formats?: list<string>, fallbacks?: list<string>} $options the densities beyond `1x`, `[2, 3]`; the widths, none; the formats of the sources, `['avif', 'webp']`; the formats of the fallback, in order
     *
     * @return array{src: string, srcset?: string, sources: list<array{type: string, srcset: string}>, width?: int, height?: int}
     *
     * @throws InvalidArgumentException when the path is not that of an image without extension, or no fallback is found
     */
    public function of(string $path, array $options = []): array
    {
        // Segments of letters, digits, `_`, `-`, `@` and dots, none starting with a dot, the last without one.
        if (1 !== preg_match('#^(?:[\w@-][\w@.-]*/)*[\w@-]+$#D', $path)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the logical path of an image without its extension, as "images/logo".', $path));
        }

        $densities = $options['densities'] ?? self::DENSITIES;
        $widths = $options['widths'] ?? [];
        $fallback = null;

        foreach ($options['fallbacks'] ?? self::FALLBACKS as $format) {
            $candidates = $this->candidates($path, $format, $densities, $widths);

            if (null !== $candidates) {
                $fallback = [$format, $candidates];

                break;
            }
        }

        if (null === $fallback) {
            throw new InvalidArgumentException(\sprintf('No fallback of "%s" among the assets: %s.', $path, implode(', ', array_map(static fn (string $format): string => $path.'.'.$format, $options['fallbacks'] ?? self::FALLBACKS))));
        }

        [$format, [$src, $set]] = $fallback;
        $props = ['src' => $src];

        if ($set !== $src) {
            $props['srcset'] = $set;
        }

        $props['sources'] = [];

        foreach ($options['formats'] ?? self::FORMATS as $source) {
            $candidates = $source === $format ? null : $this->candidates($path, $source, $densities, $widths);

            if (null !== $candidates && isset(self::TYPES[$source])) {
                $props['sources'][] = ['type' => self::TYPES[$source], 'srcset' => $candidates[1]];
            }
        }

        return [...$props, ...$this->size($path.'.'.$format)];
    }

    /**
     * The URL of the file of one format without suffix, or of its first candidate, and its candidates as a `srcset`, or
     * that URL alone when it is the only one; null when the format has no file.
     *
     * @param list<int> $densities
     * @param list<int> $widths
     *
     * @return array{string, string}|null
     */
    private function candidates(string $path, string $format, array $densities, array $widths): ?array
    {
        $base = $this->assets->getPublicPath($path.'.'.$format);
        $descriptor = [] === $widths ? 'x' : 'w';
        $candidates = null === $base || 'w' === $descriptor ? [] : [$base.' 1x'];

        foreach ('x' === $descriptor ? $densities : $widths as $size) {
            $url = $this->assets->getPublicPath(\sprintf('%s@%d%s.%s', $path, $size, $descriptor, $format));

            if (null !== $url) {
                $candidates[] = $url.' '.$size.$descriptor;
            }
        }

        // The file without suffix alone, in a set of densities or beside no width, is written without descriptor.
        if (null !== $base && \count($candidates) <= ('x' === $descriptor ? 1 : 0)) {
            return [$base, $base];
        }

        return [] === $candidates ? null : [$base ?? explode(' ', $candidates[0])[0], implode(', ', $candidates)];
    }

    /**
     * The width and height of the file of an asset, when PHP reads them: never for SVG.
     *
     * @return array{width?: int, height?: int}
     */
    private function size(string $logicalPath): array
    {
        $file = $this->assets->getAsset($logicalPath)?->sourcePath;
        $size = null !== $file && is_file($file) ? getimagesize($file) : false;

        return false === $size ? [] : ['width' => $size[0], 'height' => $size[1]];
    }
}

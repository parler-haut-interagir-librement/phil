<?php

declare(strict_types=1);

namespace Phil\SvgChart\Raster;

use Override;
use Phil\Fonts\Exception\FontsException;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;
use Phil\SvgChart\Contract\Rasterizer;
use Phil\SvgChart\Exception\InvalidRasterizer;
use Phil\SvgChart\Exception\RasterizationFailed;
use Phil\SvgChart\Exception\RasterizerUnavailable;
use Phil\SvgChart\Internal\Contract\RasterEngine;
use Phil\SvgChart\Internal\ResvgEngine;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;

/**
 * Draws SVG as PNG through the `resvg` PHP extension, in the process, for images that may come from anyone.
 *
 * Every image goes through phil/svg-sanitizer first, without the raster images of `data:` addresses; the renderer
 * reads no font of the system, only the files given, and confines the images an SVG names to an empty directory, so
 * that it reads no file at all. An image larger than the limits, in bytes or in pixels at the scale asked, is refused
 * before it is drawn. The renderer holds its fonts: build the rasterizer once per worker and keep it.
 */
final readonly class ResvgRasterizer implements Rasterizer
{
    /** The PHP extension the rasterizer needs. */
    public const string EXTENSION = ResvgEngine::EXTENSION;

    /** The largest limit of bytes, the extension's own default. */
    public const int MAX_BYTES = 16_777_216;

    /** The largest limit of pixels, the extension's own default: 8192 × 8192. */
    public const int MAX_PIXELS = 67_108_864;

    /** The largest scale. */
    public const int MAX_SCALE = 4;

    /** The directory the images an SVG names are confined to: it holds no image. */
    public const string CONFINED_DIRECTORY = __DIR__.'/../../resources/confined';

    private SvgSanitizer $sanitizer;

    private RasterEngine $engine;

    /**
     * @param non-empty-list<string> $fontFiles TrueType or OpenType files; the family of the first one draws every text whose
     *                                          own families are not among them, generic families included
     * @param int                    $maxBytes  the largest SVG drawn, in bytes, from 1 to `MAX_BYTES`
     * @param int                    $maxPixels the largest PNG drawn, width times height at the scale asked, from 1 to `MAX_PIXELS`
     * @param RasterEngine|null      $engine    the extension's renderer by default; another one in the tests of the library alone
     *
     * @throws InvalidRasterizer     when a limit is out of range, or a font is missing, unreadable or of another format
     * @throws RasterizerUnavailable when the extension is not loaded
     */
    public function __construct(
        array $fontFiles,
        private int $maxBytes = 1_048_576,
        private int $maxPixels = 16_777_216,
        ?RasterEngine $engine = null,
    ) {
        if ($maxBytes < 1 || $maxBytes > self::MAX_BYTES) {
            throw InvalidRasterizer::because(\sprintf('a limit of 1 to %d bytes is expected, %d given', self::MAX_BYTES, $maxBytes));
        }

        if ($maxPixels < 1 || $maxPixels > self::MAX_PIXELS) {
            throw InvalidRasterizer::because(\sprintf('a limit of 1 to %d pixels is expected, %d given', self::MAX_PIXELS, $maxPixels));
        }

        $this->sanitizer = new SvgSanitizer(new Policy(dataImages: false, maxBytes: $maxBytes));
        $family = $this->family($fontFiles);
        $this->engine = $engine ?? new ResvgEngine($fontFiles, $family, self::CONFINED_DIRECTORY);
    }

    /**
     * Whether the extension is loaded, so that a PNG may be offered at all.
     */
    public static function isAvailable(): bool
    {
        return \extension_loaded(self::EXTENSION);
    }

    #[Override]
    public function png(string $svg, int $scale = 1): string
    {
        if ($scale < 1 || $scale > self::MAX_SCALE) {
            throw RasterizationFailed::because(\sprintf('a scale of 1 to %d is expected, %d given', self::MAX_SCALE, $scale));
        }

        if (\strlen($svg) > $this->maxBytes) {
            throw RasterizationFailed::because(\sprintf('it has %d bytes, more than the %d allowed', \strlen($svg), $this->maxBytes));
        }

        $sanitized = $this->sanitizer->sanitize($svg);

        if ($sanitized->isRefused()) {
            throw RasterizationFailed::because(\sprintf('the sanitizer refused it, %s', $sanitized->removals[0]->reason->value));
        }

        ['width' => $width, 'height' => $height] = $this->engine->measure($sanitized->svg, $scale);

        if ($width * $height > $this->maxPixels) {
            throw RasterizationFailed::because(\sprintf('it would have %d × %d pixels, more than the %d allowed', $width, $height, $this->maxPixels));
        }

        return $this->engine->render($sanitized->svg, $scale);
    }

    /**
     * The family of the first font, once every font is read and found to be TrueType or OpenType.
     *
     * @param list<string> $fontFiles
     */
    private function family(array $fontFiles): string
    {
        $families = [];

        foreach ($fontFiles as $file) {
            try {
                $font = FontFile::fromFile($file);
            } catch (FontsException $fontsException) {
                throw InvalidRasterizer::because(\sprintf('the font file "%s" cannot be read', $file), $fontsException);
            }

            if (\in_array($font->format, [Format::Woff, Format::Woff2], true)) {
                throw InvalidRasterizer::because(\sprintf('the font file "%s" is %s, and the renderer reads TrueType and OpenType files', $file, strtoupper($font->format->value)));
            }

            $families[] = $font->face()->family();
        }

        return $families[0] ?? throw InvalidRasterizer::because('at least one font file is expected');
    }
}

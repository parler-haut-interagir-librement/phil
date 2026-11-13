<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint;

use InvalidArgumentException;

/**
 * What the PDF the engine writes is made of: its standard, its output intent, its tags and forms, its images.
 *
 * Each option is one argument of the command, checked here, so that nothing a document holds reaches the command line.
 */
final readonly class PdfOptions
{
    /** The output intents WeasyPrint knows by name; any other is the name of a document's `@color-profile`. */
    private const array NAMED_INTENTS = ['srgb', 'device-cmyk'];

    private const int MAX_DPI = 2_400;

    private const int MAX_JPEG_QUALITY = 95;

    /**
     * @param PdfVariant|null $variant        the standard of the PDF; none by default
     * @param string|null     $outputIntent   `srgb`, `device-cmyk`, or the dashed name of a `@color-profile` of the
     *                                        document, such as `--press`; a PDF/X variant needs such a profile
     * @param bool            $tags           whether the PDF is tagged for accessibility
     * @param bool            $forms          whether the form fields of the document become PDF form fields
     * @param int|null        $maxImageDpi    the highest resolution an image keeps, from 1 to 2400
     * @param int|null        $jpegQuality    the quality of the JPEG images written again, from 0 to 95
     * @param bool            $optimizeImages whether images are made smaller without losing quality
     */
    public function __construct(
        public ?PdfVariant $variant = null,
        public ?string $outputIntent = null,
        public bool $tags = false,
        public bool $forms = false,
        public ?int $maxImageDpi = null,
        public ?int $jpegQuality = null,
        public bool $optimizeImages = false,
    ) {
        if (null !== $outputIntent && !\in_array($outputIntent, self::NAMED_INTENTS, true) && 1 !== preg_match('/^--[A-Za-z_][A-Za-z0-9_-]*$/D', $outputIntent)) {
            throw new InvalidArgumentException(\sprintf('The output intent "%s" is neither srgb, device-cmyk nor the dashed name of a colour profile.', $outputIntent));
        }

        if (true === $variant?->isPdfX() && (null === $outputIntent || \in_array($outputIntent, self::NAMED_INTENTS, true))) {
            throw new InvalidArgumentException('A PDF/X variant needs the colour profile of its printing condition as its output intent.');
        }

        if (null !== $maxImageDpi && ($maxImageDpi < 1 || $maxImageDpi > self::MAX_DPI)) {
            throw new InvalidArgumentException(\sprintf('The resolution of images is between 1 and %d dots per inch.', self::MAX_DPI));
        }

        if (null !== $jpegQuality && ($jpegQuality < 0 || $jpegQuality > self::MAX_JPEG_QUALITY)) {
            throw new InvalidArgumentException(\sprintf('The quality of JPEG images is between 0 and %d.', self::MAX_JPEG_QUALITY));
        }
    }

    /**
     * The arguments of the command, in a fixed order.
     *
     * @return list<string>
     */
    public function arguments(): array
    {
        $arguments = [];

        if ($this->variant instanceof PdfVariant) {
            $arguments[] = '--pdf-variant='.$this->variant->value;
        }

        if (null !== $this->outputIntent) {
            $arguments[] = '--output-intent='.$this->outputIntent;
        }

        if ($this->tags) {
            $arguments[] = '--pdf-tags';
        }

        if ($this->forms) {
            $arguments[] = '--pdf-forms';
        }

        if (null !== $this->maxImageDpi) {
            $arguments[] = '--dpi='.$this->maxImageDpi;
        }

        if (null !== $this->jpegQuality) {
            $arguments[] = '--jpeg-quality='.$this->jpegQuality;
        }

        if ($this->optimizeImages) {
            $arguments[] = '--optimize-images';
        }

        return $arguments;
    }
}

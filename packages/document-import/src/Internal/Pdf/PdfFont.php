<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Codepage;

/**
 * A font of a page, as far as reading its text goes: how its strings split into codes, the text of each code — its
 * `ToUnicode` CMap first, then its encoding and differences for a simple font —, the width of each code, and whether
 * it is bold or italic, from its name and descriptor.
 *
 * @internal
 */
final class PdfFont
{
    public readonly bool $bold;

    public readonly bool $italic;

    /** Whether the font is composite, its codes of two bytes unless its CMap says otherwise. */
    private readonly bool $composite;

    private ?PdfCMap $cmap = null;

    /** @var array<int, string> the text of the codes its differences rename */
    private array $differences = [];

    private string $encoding = 'WinAnsiEncoding';

    /** @var array<int, float> */
    private array $widths = [];

    private readonly float $defaultWidth;

    /**
     * @throws LimitExceeded
     */
    public function __construct(PdfDictionary $font, PdfDocument $document)
    {
        $this->composite = 'Type0' === $font->name('Subtype');
        $name = (string) preg_replace('/^[A-Z]{6}\+/', '', $font->name('BaseFont') ?? '');
        $descendants = $document->resolve($font->get('DescendantFonts'));
        $descendant = $this->composite ? $document->dictionary(\is_array($descendants) ? $descendants[0] ?? null : null) : $font;
        $descriptor = $document->dictionary($descendant->get('FontDescriptor'));
        $flags = $document->resolve($descriptor->get('Flags'));
        $weight = $document->resolve($descriptor->get('FontWeight'));
        $this->bold = 1 === preg_match('/bold|black|heavy|semibold|demi/i', $name) || (\is_int($flags) && 0 !== ($flags & (1 << 18))) || (is_numeric($weight) && $weight >= 600);
        $this->italic = 1 === preg_match('/italic|oblique/i', $name) || (\is_int($flags) && 0 !== ($flags & 64));
        $this->defaultWidth = $this->composite ? $this->number($document->resolve($descendant->get('DW')), 1000.0) : 500.0;
        $toUnicode = $document->resolve($font->get('ToUnicode'));

        if ($toUnicode instanceof PdfStream) {
            $this->cmap = new PdfCMap($document->decode($toUnicode)[0]);
        }

        $this->composite ? $this->compositeWidths($document->resolve($descendant->get('W')), $document) : $this->simple($font, $document);
    }

    /**
     * The codes of a string, each with its text and its width in thousandths of the font size.
     *
     * @return list<array{string, float, bool}> the text, the width and whether the code is the single byte 32
     */
    public function glyphs(string $bytes): array
    {
        $glyphs = [];
        $at = 0;
        $length = \strlen($bytes);

        while ($at < $length) {
            $size = $this->codeLength($bytes, $at);
            $code = substr($bytes, $at, $size);
            $at += $size;
            $value = (int) hexdec(sodium_bin2hex($code));
            $glyphs[] = [$this->text($code, $value), $this->widths[$value] ?? $this->defaultWidth, 1 === $size && 32 === $value];
        }

        return $glyphs;
    }

    private function codeLength(string $bytes, int $at): int
    {
        $cmap = $this->cmap;

        if ($cmap instanceof PdfCMap) {
            foreach ($cmap->lengths() as $length) {
                if ($cmap->isCode(substr($bytes, $at, $length))) {
                    return $length;
                }
            }
        }

        return $this->composite ? 2 : 1;
    }

    private function text(string $code, int $value): string
    {
        $mapped = $this->cmap?->text($code);

        return match (true) {
            null !== $mapped => $mapped,
            isset($this->differences[$value]) => $this->differences[$value],
            $this->composite => 0 === $value ? '' : Codepage::character($value),
            $value < 32 => '',
            'MacRomanEncoding' === $this->encoding => Codepage::decode($code, Codepage::MAC_ROMAN),
            'StandardEncoding' === $this->encoding && 0x27 === $value => '’',
            'StandardEncoding' === $this->encoding && 0x60 === $value => '‘',
            default => Codepage::decode($code),
        };
    }

    /**
     * @throws LimitExceeded
     */
    private function simple(PdfDictionary $font, PdfDocument $document): void
    {
        $encoding = $document->resolve($font->get('Encoding'));
        $first = $document->resolve($font->get('FirstChar'));
        $widths = $document->resolve($font->get('Widths'));

        if ($encoding instanceof PdfName) {
            $this->encoding = $encoding->name;
        }

        if ($encoding instanceof PdfDictionary) {
            $this->encoding = $encoding->name('BaseEncoding') ?? 'StandardEncoding';
            $this->differences($document->resolve($encoding->get('Differences')));
        }

        if (\is_array($widths) && \is_int($first)) {
            $code = $first;

            foreach (\array_slice($widths, 0, 256) as $width) {
                $this->widths[$code++] = $this->number($document->resolve($width), 500.0);
            }
        }
    }

    private function differences(mixed $differences): void
    {
        $code = 0;

        foreach (\is_array($differences) ? \array_slice($differences, 0, 512) : [] as $item) {
            if (\is_int($item)) {
                $code = $item;

                continue;
            }

            if ($item instanceof PdfName) {
                $text = GlyphNames::text($item->name);

                if (null !== $text) {
                    $this->differences[$code] = $text;
                }

                ++$code;
            }
        }
    }

    /**
     * The widths of a composite font: `c [w1 w2 …]` gives consecutive codes from `c`, `c1 c2 w` a range.
     *
     * @throws LimitExceeded
     */
    private function compositeWidths(mixed $widths, PdfDocument $document): void
    {
        $items = \is_array($widths) ? \array_slice($widths, 0, 65_536) : [];
        $count = \count($items);

        for ($at = 0; $at < $count; ++$at) {
            $first = $items[$at];
            $next = $document->resolve($items[$at + 1] ?? null);

            if (!\is_int($first)) {
                continue;
            }

            if (\count($this->widths) > 65_536) {
                return;
            }

            if (\is_array($next)) {
                $code = $first;

                foreach (\array_slice($next, 0, 65_536) as $width) {
                    $this->widths[$code++] = $this->number($width, $this->defaultWidth);
                }

                ++$at;

                continue;
            }

            $last = $items[$at + 1] ?? null;
            $width = $items[$at + 2] ?? null;

            if (\is_int($last) && $last >= $first) {
                for ($code = $first; $code <= min($last, $first + 65_535); ++$code) {
                    $this->widths[$code] = $this->number($width, $this->defaultWidth);
                }

                $at += 2;
            }
        }
    }

    private function number(mixed $value, float $default): float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : $default;
    }
}

<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Form\CheckBox;
use Phil\PagedMedia\Pdf\Form\ChoiceField;
use Phil\PagedMedia\Pdf\Form\Fields;
use Phil\PagedMedia\Pdf\Form\FormWriter;
use Phil\PagedMedia\Pdf\Form\RadioGroup;
use Phil\PagedMedia\Pdf\Form\TextField;
use Phil\PagedMedia\Pdf\Form\Widget;

/**
 * The native PDF writer: pages of a given size, text in the standard fonts or in embedded TrueType fonts, images, lines
 * and rectangles, form fields a reader fills, compressed streams and an information dictionary. Coordinates are in
 * points from the bottom left corner of the page.
 *
 * A document written to a `Conformance` refuses what the standard forbids as soon as it is drawn: PDF/A-2u refuses the
 * standard fonts, which are not embedded, a character the font lacks, and CMYK images.
 *
 * The output is the same for the same calls: no date unless the metadata gives one, no random identifier.
 */
final class Document
{
    private const string HEADER = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";

    /** Glyphs per `beginbfchar` block, the most a CMap allows. */
    private const int CMAP_BLOCK = 100;

    /** The length under which an appearance stream is written uncompressed, in bytes. */
    private const int SMALL_STREAM = 256;

    /** @var list<array{PageSize, string}> each page's size and content stream */
    private array $pages = [];

    /** @var array<string, StandardFont|TrueTypeFont> each font used, by its resource name */
    private array $fonts = [];

    /** @var array<int|string, string> each font's resource name, by the standard font's name or the embedded font's object identifier */
    private array $fontNames = [];

    /** @var array<string, array<int, int>> each embedded font's glyphs used, glyph to the code point it prints */
    private array $glyphs = [];

    /** @var array<string, Image> each image placed, by its resource name */
    private array $images = [];

    /** @var array<int, string> each image's resource name, by object identifier */
    private array $imageNames = [];

    /** @var list<array{int, list<Widget>, string|null}> each field's page, its widgets, and the entries of a radio group's parent */
    private array $fields = [];

    /** @var array<string, true> the name of each field placed */
    private array $fieldNames = [];

    /** @var array<string, true> the embedded fonts a field types in, by resource name: embedded whole while the form is interactive */
    private array $formFonts = [];

    private readonly PageSize $size;

    /**
     * @param PageSize|null    $size        each page's size unless `addPage()` gives another; Letter when none is given
     * @param bool             $compress    whether content streams and font files are compressed
     * @param Conformance|null $conformance the standard the document conforms to, if any
     */
    public function __construct(
        ?PageSize $size = null,
        private readonly Metadata $metadata = new Metadata(),
        private readonly bool $compress = true,
        private readonly ?Conformance $conformance = null,
    ) {
        $this->size = $size ?? PageSize::letter();
    }

    public function addPage(?PageSize $size = null): void
    {
        $this->pages[] = [$size ?? $this->size, ''];
    }

    public function pageCount(): int
    {
        return \count($this->pages);
    }

    /**
     * Writes a line of text with its baseline starting at (x, y).
     */
    public function text(float $x, float $y, string $text, float $size = 10, StandardFont|TrueTypeFont $font = StandardFont::Helvetica, ?Color $color = null): void
    {
        if ($size <= 0) {
            throw InvalidDocument::because(\sprintf('A font size is above zero; %s is not.', $size));
        }

        $operand = $this->operand($font, $text);
        $shown = \sprintf('BT /%s %s Tf %s %s Td %s Tj ET', $this->fontName($font), Number::format($size), Number::format($x), Number::format($y), $operand);
        $this->draw($color instanceof Color ? 'q '.$color->operator(false).' '.$shown.' Q' : $shown);
    }

    /**
     * Writes a line of text ending at x: an amount in a column, a page number in a corner.
     */
    public function textRight(float $right, float $y, string $text, float $size = 10, StandardFont|TrueTypeFont $font = StandardFont::Helvetica, ?Color $color = null): void
    {
        $this->text($right - $font->width($text, $size), $y, $text, $size, $font, $color);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 1, ?Color $color = null): void
    {
        $this->draw(\sprintf('q %s w %s%s %s m %s %s l S Q', Number::format($width), $color instanceof Color ? $color->operator(true).' ' : '', Number::format($x1), Number::format($y1), Number::format($x2), Number::format($y2)));
    }

    /**
     * Draws a rectangle from its bottom left corner: filled, outlined, or both; outlined in black when neither is given.
     */
    public function rectangle(float $x, float $y, float $width, float $height, ?Color $fill = null, ?Color $stroke = null, float $lineWidth = 1): void
    {
        $operator = match (true) {
            $fill instanceof Color && $stroke instanceof Color => 'B',
            $fill instanceof Color => 'f',
            default => 'S',
        };
        $colours = implode('', array_map(static fn (string $colour): string => $colour.' ', array_filter([$fill?->operator(false), $stroke?->operator(true)], is_string(...))));

        $this->draw(\sprintf('q %s w %s%s %s %s %s re %s Q', Number::format($lineWidth), $colours, Number::format($x), Number::format($y), Number::format($width), Number::format($height), $operator));
    }

    /**
     * Places a form field on the last page: a text box, a check box, a radio group or a choice. Its appearance is drawn
     * with it, so that every reader shows it as it is; `render()` keeps it fillable, or flattens it into the page.
     */
    public function field(TextField|CheckBox|RadioGroup|ChoiceField $field): void
    {
        $page = array_key_last($this->pages) ?? throw InvalidDocument::because('Add a page before placing a field on it.');

        if (isset($this->fieldNames[$field->name])) {
            throw InvalidDocument::because(\sprintf('A field name is unique in a document; "%s" is already placed.', $field->name));
        }

        $font = null;
        $operand = static fn (string $text): string => '()';

        if ($field instanceof TextField || $field instanceof ChoiceField) {
            $typeface = $field->font;
            $operand = fn (string $text): string => $this->operand($typeface, $text);
            $operand('');
            $font = $this->fontName($typeface);

            if ($typeface instanceof TrueTypeFont) {
                $this->formFonts[$font] = true;
            }
        }

        [$widgets, $parent] = Fields::widgets($field, $font, $operand);
        $this->fieldNames[$field->name] = true;
        $this->fields[] = [$page, $widgets, $parent];
    }

    /**
     * Places an image with its bottom left corner at (x, y), stretched to the width and height given.
     */
    public function image(Image $image, float $x, float $y, float $width, float $height): void
    {
        if ('/DeviceCMYK' === $image->colorSpace && $this->conformance instanceof Conformance) {
            throw InvalidDocument::because(\sprintf('A %s document defines its colours in sRGB; a CMYK image cannot be placed.', $this->conformance->value));
        }

        $name = $this->imageNames[spl_object_id($image)] ??= 'Im'.(\count($this->images) + 1);
        $this->images[$name] = $image;
        $this->draw(\sprintf('q %s 0 0 %s %s %s cm /%s Do Q', Number::format($width), Number::format($height), Number::format($x), Number::format($y), $name));
    }

    /**
     * The text cut into lines no wider than the width, between words; a word wider than a line is cut where it
     * overflows. Line breaks and tabs count as spaces, and an empty text is one empty line.
     *
     * @return non-empty-list<string>
     */
    public static function wrap(string $text, float $width, float $size, StandardFont|TrueTypeFont $font = StandardFont::Helvetica): array
    {
        $lines = [];
        $line = '';
        $words = preg_split('/[ \t\r\n]+/', trim($text));

        foreach (false === $words ? [] : $words as $word) {
            $candidate = '' === $line ? $word : $line.' '.$word;

            if ($font->width($candidate, $size) <= $width) {
                $line = $candidate;

                continue;
            }

            if ('' !== $line) {
                $lines[] = $line;
            }

            $line = '';

            foreach (mb_str_split($word) as $character) {
                $candidate = $line.$character;

                if ('' !== $line && $font->width($candidate, $size) > $width) {
                    $lines[] = $line;
                    $candidate = $character;
                }

                $line = $candidate;
            }
        }

        $lines[] = $line;

        return $lines;
    }

    /**
     * @param bool $flattenForm whether the form fields are drawn into their pages, no longer fillable, rather than kept
     *                          as an interactive form
     */
    public function render(bool $flattenForm = false): string
    {
        $objects = new Objects();
        $catalog = $objects->reserve();
        $pages = $objects->reserve();
        $fonts = [];
        $images = [];

        foreach ($this->fonts as $name => $font) {
            $fonts[$name] = $font instanceof TrueTypeFont ? $this->embeddedFont($objects, $font, $this->glyphs[$name] ?? [], !$flattenForm && isset($this->formFonts[$name])) : $objects->add(\sprintf('<< /Type /Font /Subtype /Type1 /BaseFont /%s /Encoding /WinAnsiEncoding >>', $font->value));
        }

        foreach ($this->images as $name => $image) {
            $images[] = \sprintf('/%s %d 0 R', $name, $this->imageObject($objects, $image));
        }

        $form = new FormWriter($objects, $this->fields, $fonts, fn (string $entries, string $data): string => $this->stream($entries, $data, self::SMALL_STREAM), $flattenForm);
        [$appearances, $drawn] = $form->flattened();
        $fontResources = implode(' ', array_map(static fn (string $name, int $object): string => \sprintf('/%s %d 0 R', $name, $object), array_keys($fonts), $fonts));
        $xObjects = implode(' ', [...$images, ...$appearances]);
        $resources = '<< '.('' === $fontResources ? '' : '/Font << '.$fontResources.' >> ').('' === $xObjects ? '' : '/XObject << '.$xObjects.' >> ').'>>';
        $kids = [];

        foreach ($this->pages as $index => [$size, $content]) {
            $contents = $objects->add($this->stream('', $content.($drawn[$index] ?? '')));
            $page = \sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources %s /Contents %d 0 R', $pages, Number::format($size->width), Number::format($size->height), $resources, $contents);

            if (!$form->annotates($index)) {
                $kids[] = $objects->add($page.' >>').' 0 R';

                continue;
            }

            $number = $objects->reserve();
            $objects->set($number, $page.' /Annots ['.implode(' ', $form->annotations($index, $number)).'] >>');
            $kids[] = $number.' 0 R';
        }

        $objects->set($pages, \sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), \count($kids)));
        $objects->set($catalog, \sprintf('<< /Type /Catalog /Pages %d 0 R%s%s >>', $pages, $form->catalogEntry(), $this->conformance instanceof Conformance ? $this->archival($objects, $this->conformance) : ''));

        $info = $objects->add('<< '.implode(' ', array_map(static fn (string $key, string $value): string => '/'.$key.' '.$value, array_keys($this->metadata->entries()), $this->metadata->entries())).' >>');

        return $objects->write(self::HEADER, $catalog, $info, $this->conformance instanceof Conformance);
    }

    /**
     * The catalog entries of a document that conforms: its XMP metadata, written uncompressed so that any reader finds
     * it, and its sRGB output intent.
     */
    private function archival(Objects $objects, Conformance $conformance): string
    {
        $xmp = $this->metadata->xmp($conformance);
        $metadata = $objects->add(\sprintf("<< /Type /Metadata /Subtype /XML /Length %d >>\nstream\n%s\nendstream", \strlen($xmp), $xmp));
        $profile = $objects->add($this->stream('/N 3 ', SrgbProfile::bytes()));
        $intent = \sprintf('<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (sRGB IEC61966-2.1) /Info (%s) /DestOutputProfile %d 0 R >>', SrgbProfile::DESCRIPTION, $profile);

        return \sprintf(' /Metadata %d 0 R /OutputIntents [%s]', $metadata, $intent);
    }

    /**
     * A text as the operand of `Tj` in the font: a string in Windows-1252 for a standard font, glyph identifiers for an
     * embedded one.
     */
    private function operand(StandardFont|TrueTypeFont $font, string $text): string
    {
        if ($font instanceof StandardFont && $this->conformance instanceof Conformance) {
            throw InvalidDocument::because(\sprintf('A %s document embeds its fonts; %s cannot be used, a TrueType font can.', $this->conformance->value, $font->value));
        }

        return $font instanceof TrueTypeFont ? $this->glyphString($font, $text) : '('.$this->escape(StandardFont::encode($text)).')';
    }

    private function draw(string $operators): void
    {
        $page = array_key_last($this->pages) ?? throw InvalidDocument::because('Add a page before drawing on it.');
        $this->pages[$page][1] .= $operators."\n";
    }

    private function fontName(StandardFont|TrueTypeFont $font): string
    {
        $name = $this->fontNames[$font instanceof StandardFont ? $font->value : spl_object_id($font)] ??= 'F'.(\count($this->fonts) + 1);
        $this->fonts[$name] = $font;

        return $name;
    }

    private function glyphString(TrueTypeFont $font, string $text): string
    {
        $name = $this->fontName($font);
        $glyphs = $font->glyphs($text);
        $characters = mb_str_split($text);

        foreach ($glyphs as $index => $glyph) {
            if (0 === $glyph && $this->conformance instanceof Conformance) {
                throw InvalidDocument::because(\sprintf('The font "%s" has no glyph for "%s"; a %s document cannot print it.', $font->postScriptName(), $characters[$index], $this->conformance->value));
            }

            $this->glyphs[$name][$glyph] ??= mb_ord($characters[$index]);
        }

        return '<'.implode('', array_map(static fn (int $glyph): string => \sprintf('%04X', $glyph), $glyphs)).'>';
    }

    /**
     * @param array<int, int> $used  glyph to the code point it prints
     * @param bool            $whole whether every glyph the font maps is embedded, for a field a reader types in
     */
    private function embeddedFont(Objects $objects, TrueTypeFont $font, array $used, bool $whole): int
    {
        if ($whole) {
            foreach ($font->characters() as $codePoint => $glyph) {
                $used[$glyph] ??= $codePoint;
            }
        }

        ksort($used);
        $glyphs = array_keys($used);
        $tag = $font->allowsSubsetting() && !$whole ? strtr(substr(hash('xxh128', $font->postScriptName().implode(',', $glyphs)), 0, 6), '0123456789abcdef', 'ABCDEFGHIJKLMNOP').'+' : '';
        $baseFont = $tag.$font->postScriptName();
        $file = $font->subset($glyphs);
        $fontFile = $objects->add($this->stream('/Length1 '.\strlen($file).' ', $file));
        $descriptor = $font->descriptor();
        $flags = 32 | ($descriptor['fixedPitch'] ? 1 : 0) | (0.0 !== $descriptor['italicAngle'] ? 64 : 0);
        $descriptorObject = $objects->add(\sprintf(
            '<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox [%s] /ItalicAngle %s /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 %d 0 R >>',
            $baseFont,
            $flags,
            implode(' ', $descriptor['boundingBox']),
            Number::format($descriptor['italicAngle']),
            $descriptor['ascent'],
            $descriptor['descent'],
            $descriptor['capHeight'],
            $fontFile,
        ));
        $widths = implode(' ', array_map(static fn (int $glyph): string => $glyph.' ['.$font->advance($glyph).']', $glyphs));
        $cidFont = $objects->add(\sprintf('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /W [%s] /CIDToGIDMap /Identity >>', $baseFont, $descriptorObject, $widths));
        $toUnicode = $objects->add($this->stream('', $this->toUnicode($used)));

        return $objects->add(\sprintf('<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>', $baseFont, $cidFont, $toUnicode));
    }

    /**
     * @param array<int, int> $used glyph to code point, sorted by glyph
     */
    private function toUnicode(array $used): string
    {
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";

        foreach (array_chunk($used, self::CMAP_BLOCK, true) as $block) {
            $cmap .= \count($block)." beginbfchar\n";

            foreach ($block as $glyph => $codePoint) {
                $cmap .= \sprintf("<%04X> <%s>\n", $glyph, Bytes::hex(mb_convert_encoding(mb_chr($codePoint), 'UTF-16BE', 'UTF-8')));
            }

            $cmap .= "endbfchar\n";
        }

        return $cmap."endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
    }

    private function imageObject(Objects $objects, Image $image): int
    {
        $mask = null === $image->alpha ? '' : \sprintf('/SMask %d 0 R ', $objects->add(\sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent %d /Filter /FlateDecode /Length %d >>\nstream\n%s\nendstream", $image->width, $image->height, $image->bitsPerComponent, \strlen($image->alpha), $image->alpha)));

        return $objects->add(\sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent %d /Filter /%s %s%s%s/Length %d >>\nstream\n%s\nendstream",
            $image->width,
            $image->height,
            $image->colorSpace,
            $image->bitsPerComponent,
            $image->filter,
            null === $image->decodeParameters ? '' : '/DecodeParms '.$image->decodeParameters.' ',
            null === $image->decode ? '' : '/Decode '.$image->decode.' ',
            $mask,
            \strlen($image->data),
            $image->data,
        ));
    }

    /**
     * A stream object, compressed when the document compresses and the data has at least the length given: compressing
     * a few dozen bytes costs more time than it saves space.
     */
    private function stream(string $entries, string $data, int $smallest = 0): string
    {
        if ($this->compress && \strlen($data) >= $smallest) {
            $data = (string) gzcompress($data, 9);
            $entries .= '/Filter /FlateDecode ';
        }

        return \sprintf("<< %s/Length %d >>\nstream\n%s\nendstream", $entries, \strlen($data), $data);
    }

    private function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}

<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Codepage;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;

/**
 * The text and simple structure of a PDF read into blocks, without any external program: its pages found through the
 * page tree, their content interpreted into runs, the runs gathered into lines, the lines a running header or footer
 * repeats on most pages left out, link annotations put on the text they cover, and the lines written as headings,
 * paragraphs, list items and images. Other annotations and form fields are reported; the document information gives
 * the metadata. A scanned page without text gives no text.
 *
 * @internal
 */
final class PdfReader
{
    private const array COMMENTS = ['Text', 'FreeText', 'Highlight', 'Underline', 'StrikeOut', 'Squiggly', 'Caret', 'Ink', 'Line', 'Square', 'Circle', 'Polygon', 'PolyLine'];

    private const array METADATA = ['Title' => 'title', 'Author' => 'creator', 'Subject' => 'subject', 'Keywords' => 'keywords', 'CreationDate' => 'created', 'ModDate' => 'modified'];

    /** The share of the top and of the bottom of a page where running headers and footers stand. */
    private const float MARGIN = 0.12;

    private readonly PdfDocument $document;

    private readonly PdfBudget $budget;

    /** @var array<int, int> the nodes of the page tree already met, against a tree that loops */
    private array $visited = [];

    /**
     * @throws InvalidDocument   when the file is not a PDF
     * @throws LimitExceeded     when it declares more objects than the limits allow
     * @throws UnsupportedFormat when it is encrypted
     */
    public function __construct(
        string $pdf,
        private readonly Builder $out,
        private readonly Limits $limits,
    ) {
        $this->document = new PdfDocument($pdf, $limits);
        $this->budget = new PdfBudget($limits->operations);
    }

    /**
     * @return array<string, string>
     *
     * @throws LimitExceeded
     */
    public function read(): array
    {
        $catalog = $this->document->dictionary($this->document->trailer->get('Root'));

        if (null !== $catalog->get('AcroForm') && [] !== $this->document->dictionary($catalog->get('AcroForm'))->entries) {
            $this->out->report->add(Omitted::Annotation, 'form');
        }

        $content = new PdfContent($this->document, new PdfImages($this->document, $this->out), $this->out, $this->limits->depth, $this->budget);
        $pages = [];

        // A page without media box is a US letter page: from 0 to 792 points up.
        foreach ($this->pages($catalog->get('Pages'), new PdfDictionary(), [0.0, 792.0], 0) as $index => [$page, $resources, $span]) {
            $runs = $this->linked($content->page($this->content($page->get('Contents')), $resources), $page);
            $pages[] = [PdfLayout::lines($runs, $index), $span];
        }

        new PdfLayout($this->out)->write($this->withoutRunningLines($pages));

        return $this->metadata($catalog);
    }

    /**
     * The pages under a node of the page tree, in order, each with the resources it has or inherits and the bottom
     * and top of the media box it has or inherits.
     *
     * @param array{float, float} $span
     *
     * @return list<array{PdfDictionary, PdfDictionary, array{float, float}}>
     *
     * @throws LimitExceeded
     */
    private function pages(mixed $node, PdfDictionary $resources, array $span, int $level): array
    {
        if ($node instanceof PdfReference) {
            if (isset($this->visited[$node->number])) {
                return [];
            }

            $this->visited[$node->number] = $node->number;
        }

        if ($level > $this->limits->depth) {
            throw LimitExceeded::because(\sprintf('The page tree of the PDF is deeper than %d.', $this->limits->depth));
        }

        $dictionary = $this->document->dictionary($node);
        $own = $this->document->dictionary($dictionary->get('Resources'));
        $resources = [] === $own->entries ? $resources : $own;
        $box = $this->box($dictionary->get('MediaBox'));
        $span = null === $box ? $span : [$box[1], $box[3]];
        $kids = $this->document->resolve($dictionary->get('Kids'));

        if ('Pages' !== $dictionary->name('Type') && !\is_array($kids)) {
            return [] === $dictionary->entries ? [] : [[$dictionary, $resources, $span]];
        }

        $pages = [];

        foreach (\is_array($kids) ? $kids : [] as $kid) {
            array_push($pages, ...$this->pages($kid, $resources, $span, $level + 1));
        }

        return $pages;
    }

    /**
     * A media box as four numbers, its top above its bottom; `null` for anything else.
     *
     * @return list<float>|null
     *
     * @throws LimitExceeded
     */
    private function box(mixed $box): ?array
    {
        $numbers = $this->rectangle($box);

        return null !== $numbers && $numbers[3] > $numbers[1] ? $numbers : null;
    }

    /**
     * The four numbers of a rectangle, its corners in any order; `null` when it is no array of four.
     *
     * @return list<float>|null
     *
     * @throws LimitExceeded
     */
    private function rectangle(mixed $rectangle): ?array
    {
        $rectangle = $this->document->resolve($rectangle);

        if (!\is_array($rectangle) || 4 !== \count($rectangle)) {
            return null;
        }

        $numbers = [];

        foreach ($rectangle as $value) {
            $value = $this->document->resolve($value);
            $numbers[] = \is_int($value) || \is_float($value) ? (float) $value : 0.0;
        }

        return $numbers;
    }

    /**
     * The content of a page: its stream, or its streams joined.
     *
     * @throws LimitExceeded
     */
    private function content(mixed $contents): string
    {
        $contents = $this->document->resolve($contents);
        $streams = \is_array($contents) ? array_map($this->document->resolve(...), $contents) : [$contents];
        $parts = [];

        foreach ($streams as $stream) {
            if ($stream instanceof PdfStream) {
                $parts[] = $this->document->decode($stream)[0];
            }
        }

        // The streams of a page are one content, split anywhere between two tokens.
        return implode("\n", $parts);
    }

    /**
     * The runs of a page, those a link annotation covers linked to its address; other annotations reported.
     *
     * @param list<PdfRun> $runs
     *
     * @return list<PdfRun>
     *
     * @throws LimitExceeded
     */
    private function linked(array $runs, PdfDictionary $page): array
    {
        $annotations = $this->document->resolve($page->get('Annots'));

        foreach (\is_array($annotations) ? \array_slice($annotations, 0, 10_000) : [] as $annotation) {
            $annotation = $this->document->dictionary($annotation);
            $subtype = $annotation->name('Subtype') ?? '';

            match (true) {
                'Link' === $subtype => $runs = $this->link($runs, $annotation),
                'Popup' === $subtype => null,
                \in_array($subtype, self::COMMENTS, true) => $this->out->report->add(Omitted::Comment),
                default => $this->out->report->add(Omitted::Annotation, strtolower($subtype)),
            };
        }

        return $runs;
    }

    /**
     * @param list<PdfRun> $runs
     *
     * @return list<PdfRun>
     *
     * @throws LimitExceeded
     */
    private function link(array $runs, PdfDictionary $annotation): array
    {
        $action = $this->document->dictionary($annotation->get('A'));
        $address = $this->document->resolve($action->get('URI'));
        $rectangle = $this->rectangle($annotation->get('Rect'));

        $web = \is_string($address) && 1 === preg_match('/^(https?:\/\/|mailto:)\S+$/iD', $address);

        if (!$web && ('GoTo' === $action->name('S') || null !== $annotation->get('Dest'))) {
            $this->out->report->add(Omitted::InternalLink);
        }

        if (!$web || null === $rectangle) {
            return $runs;
        }

        $this->budget->spend(\count($runs));
        [$left, $bottom, $right, $top] = [min($rectangle[0], $rectangle[2]), min($rectangle[1], $rectangle[3]), max($rectangle[0], $rectangle[2]), max($rectangle[1], $rectangle[3])];

        return array_map(static function (PdfRun $run) use ($left, $bottom, $right, $top, $address): PdfRun {
            $middle = ($run->x + $run->end) / 2;
            $height = $run->y + $run->size * 0.3;

            return $middle >= $left && $middle <= $right && $height >= $bottom && $height <= $top ? $run->withLink($address) : $run;
        }, $runs);
    }

    /**
     * The lines of every page, without those a running header or footer repeats near the top or the bottom of most
     * pages — their digits, page numbers, aside.
     *
     * @param list<array{list<PdfLine>, array{float, float}}> $pages
     *
     * @return list<PdfLine>
     */
    private function withoutRunningLines(array $pages): array
    {
        $counts = [];

        foreach ($pages as [$lines, $span]) {
            $keys = [];

            foreach ($lines as $line) {
                $key = $this->runningKey($line, $span);

                if (null !== $key) {
                    $keys[$key] = $key;
                }
            }

            foreach ($keys as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        $threshold = max(2, (int) ceil(\count($pages) / 2));
        $kept = [];
        $reported = [];

        foreach ($pages as [$lines, $span]) {
            foreach ($lines as $line) {
                $key = $this->runningKey($line, $span);

                if (null !== $key && ($counts[$key] ?? 0) >= $threshold) {
                    if (!isset($reported[$key])) {
                        $reported[$key] = $key;
                        $this->out->report->add(Omitted::HeaderFooter, 'repeated line');
                    }

                    continue;
                }

                $kept[] = $line;
            }
        }

        return $kept;
    }

    /**
     * The text of a line near the top or the bottom of its page, its digits as `#`; `null` for any other line.
     *
     * @param array{float, float} $span the bottom and top of the page
     */
    private function runningKey(PdfLine $line, array $span): ?string
    {
        [$bottom, $top] = $span;
        $height = $top - $bottom;
        $near = $line->y >= $top - self::MARGIN * $height || $line->y <= $bottom + self::MARGIN * $height;

        return $near && !$line->image instanceof Media ? (string) preg_replace('/\d+/', '#', trim($line->text())) : null;
    }

    /**
     * The document information as metadata, its dates in ISO 8601, and the language of the catalog.
     *
     * @return array<string, string>
     *
     * @throws LimitExceeded
     */
    private function metadata(PdfDictionary $catalog): array
    {
        $information = $this->document->dictionary($this->document->trailer->get('Info'));
        $metadata = [];

        foreach (self::METADATA as $key => $name) {
            $value = $this->document->resolve($information->get($key));
            $text = \is_string($value) ? trim($this->text($value)) : '';

            if ('' !== $text) {
                $metadata[$name] = \in_array($name, ['created', 'modified'], true) ? $this->date($text) : $text;
            }
        }

        $language = $this->document->resolve($catalog->get('Lang'));

        if (\is_string($language) && '' !== trim($language)) {
            $metadata['language'] = trim($this->text($language));
        }

        return $metadata;
    }

    /**
     * A text string of the PDF: UTF-16 or UTF-8 after their byte order mark, PDFDocEncoding read as Windows-1252
     * otherwise.
     */
    private function text(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\xFE\xFF") => mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE'),
            str_starts_with($bytes, "\xEF\xBB\xBF") => mb_scrub(substr($bytes, 3), 'UTF-8'),
            default => Codepage::decode($bytes),
        };
    }

    /**
     * A PDF date, `D:20261106093015-05'00'`, in ISO 8601; the text as it is when it is not one.
     */
    private function date(string $date): string
    {
        if (1 !== preg_match("/^D?:?(\\d{4})(\\d{2})?(\\d{2})?(\\d{2})?(\\d{2})?(\\d{2})?([Zz+\\-])?(\\d{2})?'?(\\d{2})?/", $date, $match)) {
            return $date;
        }

        $part = static fn (int $index, string $default): string => '' === ($match[$index] ?? '') ? $default : $match[$index];
        $iso = $match[1].'-'.$part(2, '01').'-'.$part(3, '01').'T'.$part(4, '00').':'.$part(5, '00').':'.$part(6, '00');

        return $iso.match ($part(7, '')) {
            '' => '',
            'Z', 'z' => 'Z',
            default => $part(7, '').$part(8, '00').':'.$part(9, '00'),
        };
    }
}

<?php

declare(strict_types=1);

namespace Phil\Ujo\Text;

use Closure;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\Rendering;

/**
 * A document as plain text — for a search index, an excerpt, an e-mail, a screen reader's summary —: each block's
 * text without its HTML, blocks separated by a blank line. Lists keep their markers and their indentation, tables
 * their cells separated by ` | `, a quote its caption after a dash; images and frames give their caption — an image
 * without one its alternative text —, links their title and address. Page markers, decorations, charts and QR codes give nothing.
 *
 * `with()` replaces the conversion of one type, or adds one for a new type; a type with none gives nothing.
 * `passages()` gives the text by language, as the `language` tunes of the blocks say.
 */
final readonly class PlainText
{
    private InlineSanitizer $sanitizer;

    /**
     * @param array<string, Closure(Block, PlainText): string> $converters each type, to the conversion that replaces its default one
     */
    public function __construct(private array $converters = [])
    {
        $this->sanitizer = new InlineSanitizer();
    }

    /**
     * @param Closure(Block, PlainText): string $converter
     */
    public function with(string $type, Closure $converter): self
    {
        return new self([...$this->converters, $type => $converter]);
    }

    public function convert(Document $document): string
    {
        return $this->blocks($document->blocks);
    }

    /**
     * The text of a document by language, for an index that reads each passage in its own language: each block at the
     * top of the document goes to the language of its `language` tune, in lower case, or to the document's language;
     * the languages in the order of their first block, those whose blocks give no text left out.
     *
     * @param string $language the language of the document, for the blocks without a `language` tune
     *
     * @return array<string, string> the text of each language, blocks separated by a blank line
     */
    public function passages(Document $document, string $language): array
    {
        $blocks = [];

        foreach ($document->blocks as $block) {
            $tag = Rendering::array($block->tunes['language'] ?? null)['language'] ?? null;
            $blocks[strtolower(\is_string($tag) ? $tag : $language)][] = $block;
        }

        return array_filter(array_map($this->blocks(...), $blocks), static fn (string $text): bool => '' !== $text);
    }

    /**
     * The text of blocks, separated by a blank line; empty ones left out.
     */
    public function blocks(mixed $blocks): string
    {
        $texts = [];

        foreach (\is_array($blocks) ? $blocks : [] as $block) {
            $text = $block instanceof Block ? trim($this->block($block)) : '';

            if ('' !== $text) {
                $texts[] = $text;
            }
        }

        return implode("\n\n", $texts);
    }

    public function block(Block $block): string
    {
        $converter = $this->converters[$block->type] ?? null;

        return null === $converter ? $this->default($block) : $converter($block, $this);
    }

    /**
     * The text of an HTML value; nothing when the value is not a text.
     */
    public function text(mixed $html): string
    {
        return \is_string($html) ? $this->sanitizer->toText($html) : '';
    }

    private function default(Block $block): string
    {
        $data = $block->data;

        return match ($block->type) {
            'paragraph', 'header', 'alert', 'runningFooter', 'footnote' => $this->text($data['text'] ?? null),
            'banner', 'watermark' => self::plain($data['text'] ?? null),
            'quote' => self::join(' — ', $this->text($data['text'] ?? null), $this->text($data['caption'] ?? null)),
            'warning' => self::join(' ', $this->text($data['title'] ?? null), $this->text($data['message'] ?? null)),
            'credit' => self::join(' — ', $this->text($data['title'] ?? null), $this->text($data['text'] ?? null)),
            'code', 'mermaid' => self::plain($data['code'] ?? null),
            'math' => \is_string($data['latex'] ?? null) ? $data['latex'] : $this->text($data['mathml'] ?? null),
            'raw' => $this->text($data['html'] ?? null),
            'delimiter' => '***',
            'list' => $this->items($data['items'] ?? null, Rendering::text($data['style'] ?? null), 0),
            'definitionList' => $this->definitions($data['items'] ?? null),
            'table' => self::join("\n", $this->text($data['caption'] ?? null), implode("\n", array_map(fn (mixed $row): string => implode(' | ', array_map($this->cell(...), Rendering::array($row))), Rendering::array($data['content'] ?? null)))),
            'index' => self::join("\n", self::plain($data['title'] ?? null), $this->entries($data['entries'] ?? null, 0)),
            'image' => array_find([$this->text($data['caption'] ?? null), self::plain($data['alt'] ?? null)], static fn (string $text): bool => '' !== $text) ?? '',
            'embed', 'video', 'audio' => self::join("\n", self::plain($data['title'] ?? null), $this->text($data['caption'] ?? null)),
            'gallery' => self::join("\n", ...array_map(fn (array $file): string => $this->text($file['caption'] ?? null), Rendering::rows($data['files'] ?? null))),
            'linkTool' => self::join("\n", self::plain(Rendering::array($data['meta'] ?? null)['title'] ?? null), self::plain($data['link'] ?? null)),
            'attaches' => array_find([self::plain($data['title'] ?? null), self::plain(Rendering::array($data['file'] ?? null)['name'] ?? null)], static fn (string $label): bool => '' !== $label) ?? '',
            'personality' => self::join(' — ', self::plain($data['name'] ?? null), self::plain($data['description'] ?? null)),
            'button' => self::join(' ', self::plain($data['text'] ?? null), '' === self::plain($data['link'] ?? null) ? '' : '('.self::plain($data['link']).')'),
            'toggle' => self::join("\n\n", $this->text($data['text'] ?? null), $this->blocks($data['blocks'] ?? null)),
            'note', 'descriptive' => self::join("\n\n", $this->text($data['title'] ?? null), $this->blocks($data['blocks'] ?? null)),
            'wide', 'columnFlow', 'container' => $this->blocks($data['blocks'] ?? null),
            'columns' => self::join("\n\n", ...array_map(fn (array $column): string => $this->blocks($column['blocks'] ?? null), Rendering::rows($data['cols'] ?? null))),
            'statblock' => $this->statblock($data),
            default => '',
        };
    }

    private function items(mixed $items, string $style, int $depth): string
    {
        $lines = [];

        foreach (Rendering::rows($items) as $number => $item) {
            $marker = match ($style) {
                'ordered' => ($number + 1).'.',
                'checklist' => true === (Rendering::array($item['meta'] ?? null)['checked'] ?? false) ? '[x]' : '[ ]',
                default => '-',
            };
            $lines[] = str_repeat('  ', $depth).$marker.' '.$this->text($item['content'] ?? null);
            $lines[] = $this->items($item['items'] ?? null, $style, $depth + 1);
        }

        return self::join("\n", ...$lines);
    }

    private function definitions(mixed $items): string
    {
        $lines = [];

        foreach (Rendering::rows($items) as $item) {
            $lines[] = $this->text($item['term'] ?? null);

            foreach (Rendering::array($item['definitions'] ?? null) as $definition) {
                $lines[] = '  '.$this->text($definition);
            }
        }

        return implode("\n", $lines);
    }

    private function entries(mixed $entries, int $depth): string
    {
        $lines = [];

        foreach (Rendering::rows($entries) as $entry) {
            $lines[] = str_repeat('  ', $depth).self::join(', ', self::plain($entry['term'] ?? null), self::plain($entry['pages'] ?? null));
            $lines[] = $this->entries($entry['children'] ?? null, $depth + 1);
        }

        return self::join("\n", ...$lines);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function statblock(array $data): string
    {
        $parts = [self::plain($data['name'] ?? null), $this->text($data['subtitle'] ?? null)];

        foreach (Rendering::rows($data['sections'] ?? null) as $section) {
            $parts[] = self::plain($section['title'] ?? null);

            foreach (Rendering::rows($section['properties'] ?? null) as $property) {
                $parts[] = self::plain($property['label'] ?? null).': '.$this->text($property['value'] ?? null);
            }

            $parts[] = implode(' ', array_map(static fn (array $ability): string => self::join(' ', self::plain($ability['label'] ?? null), self::plain($ability['score'] ?? null), '' === self::plain($ability['modifier'] ?? null) ? '' : '('.self::plain($ability['modifier']).')'), Rendering::rows($section['abilities'] ?? null)));
            $parts[] = $this->blocks($section['blocks'] ?? null);
        }

        return self::join("\n", ...$parts);
    }

    /**
     * The text of a cell of a table, a text or an object of the rich form.
     */
    private function cell(mixed $cell): string
    {
        return $this->text(\is_array($cell) ? $cell['text'] ?? null : $cell);
    }

    private static function plain(mixed $value): string
    {
        return \is_string($value) || \is_int($value) ? (string) $value : '';
    }

    private static function join(string $glue, string ...$parts): string
    {
        return implode($glue, array_filter($parts, static fn (string $part): bool => '' !== $part));
    }
}

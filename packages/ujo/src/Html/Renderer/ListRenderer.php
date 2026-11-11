<?php

declare(strict_types=1);

namespace Phil\Ujo\Html\Renderer;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Type\TableGrid;

/**
 * The structured blocks: lists, nested and checklists included, definition lists, tables, indexes, tables of contents
 * and lists of abbreviations. A table's caption is its `<caption>`; a cell of the rich form keeps its spans, a header
 * cell is a `<th>` whose `scope` is its column in the head and its row in the body, and an alignment, the cell's or
 * else its column's, is a class `ujo-align-…`.
 */
final readonly class ListRenderer implements BlockRenderer
{
    public const array TYPES = ['list', 'definitionList', 'table', 'index', 'toc', 'abbreviations'];

    /** The `type` attribute of an ordered list, for each counter. */
    private const array COUNTERS = ['lower-roman' => 'i', 'upper-roman' => 'I', 'lower-alpha' => 'a', 'upper-alpha' => 'A'];

    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        return match ($block->type) {
            'list' => $this->list($block, $rendering),
            'definitionList' => $rendering->element('dl', $block, $this->definitions($block->data, $rendering)),
            'table' => $this->table($block, $rendering),
            'index' => $this->index($block, $rendering),
            'toc' => $this->toc($block, $rendering),
            'abbreviations' => $this->abbreviations($block, $rendering),
            default => throw UnknownBlockType::renderer($block->type, 'HTML'),
        };
    }

    private function list(Block $block, Rendering $rendering): string
    {
        $style = Rendering::text($block->data['style'] ?? 'unordered');
        $meta = Rendering::array($block->data['meta'] ?? null);
        $start = $meta['start'] ?? null;
        $counter = $meta['counterType'] ?? null;
        $ordered = 'ordered' === $style;

        return $rendering->element($ordered ? 'ol' : 'ul', $block, $this->items($block->data['items'] ?? null, $ordered, 'checklist' === $style, $rendering), [
            'start' => $ordered && \is_int($start) && 1 !== $start ? $start : null,
            'type' => $ordered && \is_string($counter) ? self::COUNTERS[$counter] ?? null : null,
        ], ['ujo-list--'.$style]);
    }

    private function items(mixed $items, bool $ordered, bool $checklist, Rendering $rendering): string
    {
        $html = '';

        foreach (Rendering::rows($items) as $item) {
            $nested = Rendering::rows($item['items'] ?? null);
            $html .= Rendering::tag('li', [
                'data-checked' => $checklist ? (true === (Rendering::array($item['meta'] ?? null)['checked'] ?? false) ? 'true' : 'false') : null,
            ], $rendering->inline($item['content'] ?? null).([] === $nested ? '' : Rendering::tag($ordered ? 'ol' : 'ul', [], $this->items($nested, $ordered, $checklist, $rendering))));
        }

        return $html;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function definitions(array $data, Rendering $rendering): string
    {
        $html = '';

        foreach (Rendering::rows($data['items'] ?? null) as $item) {
            $html .= '<dt>'.$rendering->inline($item['term'] ?? null).'</dt>';

            foreach (Rendering::array($item['definitions'] ?? null) as $definition) {
                $html .= '<dd>'.$rendering->inline($definition).'</dd>';
            }
        }

        return $html;
    }

    private function table(Block $block, Rendering $rendering): string
    {
        $rows = array_values(array_map(Rendering::array(...), Rendering::array($block->data['content'] ?? null)));
        $layout = TableGrid::layout($rows);
        $columns = Rendering::array($block->data['align'] ?? null);
        $headed = true === ($block->data['withHeadings'] ?? false) && [] !== $rows;
        $html = '';

        foreach ($rows as $index => $row) {
            $head = $headed && 0 === $index;
            $cells = '';

            foreach (array_values($row) as $position => $cell) {
                $cells .= $this->cell($cell, $head, $columns[$layout[$index][1][$position]] ?? null, $rendering);
            }

            $html .= ($head ? '<thead>' : '').'<tr>'.$cells.'</tr>'.($head ? '</thead><tbody>' : '');
        }

        return $rendering->element(
            'table',
            $block,
            TextRenderer::optional('caption', 'ujo-table__caption', $rendering->inline($block->data['caption'] ?? null)).($headed ? '' : '<tbody>').$html.'</tbody>',
            [],
            true === ($block->data['stretched'] ?? false) ? ['ujo-table--stretched'] : [],
        );
    }

    /**
     * A cell: its text, or the object of the rich form, in the head or the body, with its column's alignment.
     */
    private function cell(mixed $cell, bool $head, mixed $column, Rendering $rendering): string
    {
        $rich = Rendering::array($cell);
        $header = $head || true === ($rich['header'] ?? false);
        $align = \is_string($rich['align'] ?? null) ? $rich['align'] : $column;
        $colspan = TableGrid::span($cell, 'colspan');
        $rowspan = TableGrid::span($cell, 'rowspan');

        return Rendering::tag($header ? 'th' : 'td', [
            'colspan' => $colspan > 1 ? $colspan : null,
            'rowspan' => $rowspan > 1 ? $rowspan : null,
            'scope' => match (true) {
                !$header || [] === $rich => null,
                $head => $colspan > 1 ? 'colgroup' : 'col',
                default => $rowspan > 1 ? 'rowgroup' : 'row',
            },
            'class' => \is_string($align) && '' !== $align ? 'ujo-align-'.$align : null,
        ], $rendering->inline([] === $rich ? $cell : $rich['text'] ?? null));
    }

    /**
     * The abbreviations of the document, each once with the first meaning it is given, sorted by their letters in
     * lower case; read once per rendering.
     */
    private function abbreviations(Block $block, Rendering $rendering): string
    {
        $html = '';

        foreach ($rendering->remember('ujo.abbreviations', static fn (): array => self::found($rendering)) as $abbreviation => $meaning) {
            $html .= '<dt>'.Rendering::tag('abbr', ['title' => $meaning], Rendering::text($abbreviation)).'</dt><dd>'.Rendering::text($meaning).'</dd>';
        }

        return $rendering->element('section', $block, TextRenderer::optional('p', 'ujo-abbreviations__title', Rendering::text($block->data['title'] ?? null)).('' === $html ? '' : '<dl>'.$html.'</dl>'));
    }

    /**
     * @return array<string, string> each abbreviation of the document, to its first meaning, in order
     */
    private static function found(Rendering $rendering): array
    {
        $found = [];

        foreach ($rendering->document->walk() as $holder) {
            self::collect($holder->data, $rendering, $found);
        }

        uksort($found, static fn (string $a, string $b): int => [mb_strtolower($a), $a] <=> [mb_strtolower($b), $b]);

        return $found;
    }

    /**
     * The abbreviations written in the texts of a block's data, nested blocks left to their own turn.
     *
     * @param array<string, string> $found
     */
    private static function collect(mixed $value, Rendering $rendering, array &$found): void
    {
        if (\is_string($value) && str_contains($value, '<abbr ')) {
            preg_match_all('~<abbr title="([^"]*)">(.*?)</abbr>~s', $rendering->inline($value), $matches, \PREG_SET_ORDER);

            foreach ($matches as [, $title, $content]) {
                $abbreviation = trim($rendering->plain($content));

                if ('' !== $abbreviation) {
                    $found[$abbreviation] ??= html_entity_decode($title, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                }
            }
        } elseif (\is_array($value)) {
            foreach ($value as $item) {
                self::collect($item, $rendering, $found);
            }
        }
    }

    private function index(Block $block, Rendering $rendering): string
    {
        return $rendering->element(
            'section',
            $block,
            TextRenderer::optional('h2', 'ujo-index__title', Rendering::text($block->data['title'] ?? null)).$this->entries($block->data['entries'] ?? null),
            [],
            ['ujo-columns-'.Rendering::text($block->data['columns'] ?? 2)],
        );
    }

    private function entries(mixed $entries): string
    {
        $html = '';

        foreach (Rendering::rows($entries) as $entry) {
            $html .= '<li><span class="ujo-index__term">'.Rendering::text($entry['term'] ?? null).'</span>'
                .TextRenderer::optional('span', 'ujo-index__pages', Rendering::text($entry['pages'] ?? null))
                .$this->entries($entry['children'] ?? null).'</li>';
        }

        return '' === $html ? '' : '<ul>'.$html.'</ul>';
    }

    private function toc(Block $block, Rendering $rendering): string
    {
        $depth = $block->data['depth'] ?? 3;
        $html = '';

        foreach ($rendering->remember('ujo.toc.headings', static fn (): array => self::headings($rendering)) as [$level, $item]) {
            if (\is_int($depth) && $level <= $depth) {
                $html .= $item;
            }
        }

        return $rendering->element(
            'nav',
            $block,
            TextRenderer::optional('p', 'ujo-toc__title', Rendering::text($block->data['title'] ?? null)).'<ol>'.$html.'</ol>',
            [],
            true === ($block->data['wide'] ?? false) ? ['ujo-wide'] : [],
        );
    }

    /**
     * The headings of the document its `toc` tune does not leave out, each with its level and its item; read once per
     * rendering.
     *
     * @return list<array{int, string}>
     */
    private static function headings(Rendering $rendering): array
    {
        $headings = [];

        foreach ($rendering->document->walk() as $heading) {
            $level = $heading->data['level'] ?? 2;
            $toc = Rendering::array($heading->tune('toc'));

            if ('header' === $heading->type && \is_int($level) && false !== ($toc['include'] ?? true)) {
                $anchor = Rendering::array($heading->tune('anchor'))['anchor'] ?? $heading->id;
                $headings[] = [$level, '<li class="ujo-toc__level-'.$level.'"><a href="#'.Rendering::text($anchor).'">'.Rendering::text($rendering->plain($heading->data['text'] ?? null)).'</a></li>'];
            }
        }

        return $headings;
    }
}

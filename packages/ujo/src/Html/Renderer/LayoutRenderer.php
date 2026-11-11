<?php

declare(strict_types=1);

namespace Phil\Ujo\Html\Renderer;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * The blocks of layout: columns, toggles, boxed notes and read-aloud text, spanning, flowing and styled containers,
 * page and column breaks, page styles, spacers, page numbers, decorations and stat blocks. The page markers are empty
 * elements the look's print styles act on.
 */
final readonly class LayoutRenderer implements BlockRenderer
{
    public const array TYPES = ['columns', 'toggle', 'note', 'descriptive', 'wide', 'columnFlow', 'container', 'pageBreak', 'columnBreak', 'pageStyle', 'spacer', 'pageNumber', 'decoration', 'statblock'];

    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        $data = $block->data;

        return match ($block->type) {
            'columns' => $this->columns($block, $rendering),
            'toggle' => $rendering->element('details', $block, '<summary>'.$rendering->inline($data['text'] ?? null).'</summary>'.$rendering->blocks($data['blocks'] ?? null), ['open' => true === ($data['open'] ?? false)]),
            'note', 'descriptive' => $rendering->element('aside', $block, TextRenderer::optional('p', 'ujo-box__title', $rendering->inline($data['title'] ?? null)).$rendering->blocks($data['blocks'] ?? null)),
            'wide' => $rendering->element('div', $block, $rendering->blocks($data['blocks'] ?? null)),
            'columnFlow' => $rendering->element('div', $block, $rendering->blocks($data['blocks'] ?? null), [], ['ujo-column-flow--'.Rendering::text($data['count'] ?? 2)]),
            'container' => $rendering->element('div', $block, $rendering->blocks($data['blocks'] ?? null), [], $this->classes($data['classes'] ?? null)),
            'pageBreak', 'columnBreak' => $rendering->element('div', $block, '', ['aria-hidden' => 'true']),
            'pageStyle' => $rendering->element('div', $block, '', ['aria-hidden' => 'true', 'data-variant' => \is_string($data['variant'] ?? null) ? $data['variant'] : null], $this->classes($data['classes'] ?? null)),
            'spacer' => $rendering->element('div', $block, '', ['aria-hidden' => 'true', 'data-lines' => \is_int($data['lines'] ?? null) ? $data['lines'] : 1]),
            'pageNumber' => $rendering->element('span', $block, 'fixed' === ($data['mode'] ?? null) ? Rendering::text($data['value'] ?? null) : '', ['data-mode' => Rendering::text($data['mode'] ?? 'auto')]),
            'decoration' => $rendering->element('div', $block, '', ['aria-hidden' => 'true', 'style' => \is_string($data['color'] ?? null) ? '--ujo-decoration-color:'.$data['color'] : null], ['ujo-decoration--'.Rendering::text($data['kind'] ?? 'stain').'-'.Rendering::text($data['variant'] ?? 1)]),
            'statblock' => $this->statblock($block, $rendering),
            default => throw UnknownBlockType::renderer($block->type, 'HTML'),
        };
    }

    private function columns(Block $block, Rendering $rendering): string
    {
        $columns = Rendering::rows($block->data['cols'] ?? null);
        $html = '';

        foreach ($columns as $column) {
            $html .= '<div class="ujo-column">'.$rendering->blocks($column['blocks'] ?? null).'</div>';
        }

        return $rendering->element('div', $block, $html, [], ['ujo-columns--'.\count($columns)]);
    }

    private function statblock(Block $block, Rendering $rendering): string
    {
        $data = $block->data;
        $html = '<h3 class="ujo-statblock__name">'.Rendering::text($data['name'] ?? null).'</h3>'
            .TextRenderer::optional('p', 'ujo-statblock__subtitle', $rendering->inline($data['subtitle'] ?? null));

        foreach (Rendering::rows($data['sections'] ?? null) as $section) {
            $html .= '<div class="ujo-statblock__section">'
                .TextRenderer::optional('h4', 'ujo-statblock__title', Rendering::text($section['title'] ?? null))
                .$this->properties(Rendering::rows($section['properties'] ?? null), $rendering)
                .$this->abilities(Rendering::rows($section['abilities'] ?? null))
                .$rendering->blocks($section['blocks'] ?? null)
                .'</div>';
        }

        return $rendering->element('section', $block, $html, ['data-record' => \is_string($data['record'] ?? null) ? $data['record'] : null], array_keys(array_filter([
            'ujo-statblock--'.Rendering::text($data['kind'] ?? 'monster') => true,
            'ujo-frame' => true === ($data['frame'] ?? false),
            'ujo-wide' => true === ($data['wide'] ?? false),
        ])));
    }

    /**
     * @param list<array<array-key, mixed>> $properties
     */
    private function properties(array $properties, Rendering $rendering): string
    {
        $html = '';

        foreach ($properties as $property) {
            $html .= '<dt>'.Rendering::text($property['label'] ?? null).'</dt><dd>'.$rendering->inline($property['value'] ?? null).'</dd>';
        }

        return '' === $html ? '' : '<dl class="ujo-statblock__properties">'.$html.'</dl>';
    }

    /**
     * @param list<array<array-key, mixed>> $abilities
     */
    private function abilities(array $abilities): string
    {
        $labels = '';
        $scores = '';

        foreach ($abilities as $ability) {
            $modifier = Rendering::text($ability['modifier'] ?? null);
            $labels .= '<th>'.Rendering::text($ability['label'] ?? null).'</th>';
            $scores .= '<td>'.Rendering::text($ability['score'] ?? null).('' === $modifier ? '' : ' ('.$modifier.')').'</td>';
        }

        return '' === $labels ? '' : '<table class="ujo-statblock__abilities"><tr>'.$labels.'</tr><tr>'.$scores.'</tr></table>';
    }

    /**
     * @return list<string>
     */
    private function classes(mixed $classes): array
    {
        $kept = [];

        foreach (Rendering::array($classes) as $class) {
            if (\is_string($class)) {
                $kept[] = $class;
            }
        }

        return $kept;
    }
}

<?php

declare(strict_types=1);

namespace Phil\Ujo\Html\Renderer;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * The blocks of text: paragraphs, headings, quotes, warnings, alerts, raw HTML, delimiters, formulas — their MathML
 * when they have one, else their LaTeX as text —, diagrams, running footers, footnotes, watermarks, banners and
 * credits.
 */
final readonly class TextRenderer implements BlockRenderer
{
    public const array TYPES = ['paragraph', 'header', 'quote', 'warning', 'alert', 'raw', 'delimiter', 'math', 'mermaid', 'runningFooter', 'footnote', 'watermark', 'banner', 'credit'];

    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        $data = $block->data;

        return match ($block->type) {
            'paragraph' => $rendering->element('p', $block, $rendering->inline($data['text'] ?? null)),
            'header' => $rendering->element('h'.$this->level($data['level'] ?? null), $block, $rendering->inline($data['text'] ?? null), ['id' => $block->id]),
            'quote' => $rendering->element('figure', $block, '<blockquote>'.$rendering->inline($data['text'] ?? null).'</blockquote>'.self::caption($rendering->inline($data['caption'] ?? null)), [], 'center' === ($data['alignment'] ?? null) ? ['ujo-align-center'] : []),
            'warning' => $rendering->element('aside', $block, self::optional('p', 'ujo-warning__title', $rendering->inline($data['title'] ?? null)).'<p class="ujo-warning__message">'.$rendering->inline($data['message'] ?? null).'</p>', ['role' => 'note']),
            'alert' => $rendering->element('div', $block, $rendering->inline($data['text'] ?? null), ['role' => 'alert'], ['ujo-alert--'.Rendering::text($data['type'] ?? 'info'), 'ujo-align-'.Rendering::text($data['align'] ?? 'left')]),
            'raw' => $rendering->element('div', $block, $rendering->inline($data['html'] ?? null, 'raw')),
            'delimiter' => $rendering->element('hr', $block, '', [], \is_string($data['style'] ?? null) ? ['ujo-delimiter--'.$data['style']] : []),
            'math' => $rendering->element('div', $block, \is_string($data['mathml'] ?? null) ? $rendering->inline($data['mathml'], 'mathml') : Rendering::text($data['latex'] ?? null)),
            'mermaid' => $rendering->element('figure', $block, '<pre class="mermaid">'.Rendering::text($data['code'] ?? null).'</pre>'.self::caption($rendering->inline($data['caption'] ?? null))),
            'runningFooter' => $rendering->element('div', $block, $rendering->inline($data['text'] ?? null)),
            'banner' => $rendering->element('div', $block, Rendering::text($data['text'] ?? null)),
            'footnote' => $rendering->element('aside', $block, $rendering->inline($data['text'] ?? null), ['id' => 'footnote-'.Rendering::text($data['id'] ?? null), 'role' => 'note']),
            'watermark' => $rendering->element('div', $block, Rendering::text($data['text'] ?? null), ['aria-hidden' => 'true']),
            'credit' => $rendering->element('p', $block, ltrim(self::optional('strong', 'ujo-credit__title', Rendering::text($data['title'] ?? null)).' '.$rendering->inline($data['text'] ?? null))),
            default => throw UnknownBlockType::renderer($block->type, 'HTML'),
        };
    }

    /**
     * A figure's caption, left out when empty.
     */
    public static function caption(string $html): string
    {
        return '' === $html ? '' : '<figcaption>'.$html.'</figcaption>';
    }

    /**
     * An element with a class, left out when its content is empty.
     */
    public static function optional(string $tag, string $class, string $html): string
    {
        return '' === $html ? '' : '<'.$tag.' class="'.$class.'">'.$html.'</'.$tag.'>';
    }

    private function level(mixed $level): int
    {
        return \is_int($level) ? max(1, min(6, $level)) : 2;
    }
}

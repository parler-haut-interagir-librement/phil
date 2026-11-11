<?php

declare(strict_types=1);

namespace Phil\TextDiff\Ujo;

use Phil\TextDiff\TextDiff;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;

/**
 * A difference of documents as HTML, one `<div class="text-diff-block text-diff-<kind>">` per top-level block: an
 * added block rendered in `<ins>`, a removed one in `<del>`, and any other as the difference of its old and new HTML,
 * word by word, tags kept whole — a kept block as it is. Each block is normalized before it is rendered: the renderer
 * trusts the addresses, classes and styles the normalizer checks.
 *
 * @internal
 */
final readonly class DocumentFormatter
{
    public function __construct(
        private TextDiff $text,
        private HtmlRenderer $renderer,
        private Normalizer $normalizer,
    ) {
    }

    /**
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    public function format(DocumentDiff $diff): string
    {
        $html = '';

        foreach ($diff->topLevel() as $block) {
            $html .= '<div class="text-diff-block text-diff-'.$block->kind->value.'">'.$this->content($block).'</div>'."\n";
        }

        return $html;
    }

    /**
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    private function content(BlockChange $block): string
    {
        return match ($block->kind) {
            BlockChangeKind::Added => '<ins>'.$this->render($block->after()).'</ins>',
            BlockChangeKind::Removed => '<del>'.$this->render($block->before()).'</del>',
            default => $this->text->toHtml($this->text->html($this->render($block->before()), $this->render($block->after()))),
        };
    }

    /**
     * @param list<Block> $blocks
     *
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    private function render(array $blocks): string
    {
        return $this->renderer->render($this->normalizer->normalize(new Document($blocks)));
    }
}

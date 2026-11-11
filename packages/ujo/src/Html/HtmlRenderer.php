<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

use Closure;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\Renderer\CodeRenderer;
use Phil\Ujo\Html\Renderer\LayoutRenderer;
use Phil\Ujo\Html\Renderer\ListRenderer;
use Phil\Ujo\Html\Renderer\MediaRenderer;
use Phil\Ujo\Html\Renderer\StoredMarkupRenderer;
use Phil\Ujo\Html\Renderer\TextRenderer;
use Phil\Ujo\Type\StoredMarkup;

/**
 * Renders a normalized document as HTML, block by block, with the renderer registered for each type. `with()` replaces
 * the renderer of one type, or adds one for a new type; `withCodeHighlighter()` colours code blocks on the server;
 * `withStoredMarkup()` draws the blocks that keep their SVG or MathML from it; `withFilter()` adds a last pass over the
 * whole HTML; `withDataAttributes()` writes the values of each block's `data` tune as `data-*` attributes.
 *
 * The output is purified by construction: renderers escape plain texts, and every HTML value goes through the
 * renderer's own inline sanitizer again, with the profile of its field. A document should be normalized first — the
 * values that become classes, styles and addresses are checked by `Normalizer`, not here —, which `Ujo::toHtml()` does.
 */
final readonly class HtmlRenderer
{
    private InlineSanitizer $sanitizer;

    /**
     * @param array<string, BlockRenderer> $renderers each type, to its renderer
     * @param list<HtmlFilter>             $filters   the passes over the rendered HTML, in order
     */
    public function __construct(private array $renderers = [], ?InlineSanitizer $sanitizer = null, private array $filters = [], private bool $dataAttributes = false)
    {
        $this->sanitizer = $sanitizer ?? new InlineSanitizer();
    }

    /**
     * A renderer for every type of the default registry, and for `raw`.
     */
    public static function default(?InlineSanitizer $sanitizer = null): self
    {
        $renderers = [];

        foreach ([new TextRenderer(), new CodeRenderer(), new ListRenderer(), new MediaRenderer(), new LayoutRenderer()] as $renderer) {
            foreach ($renderer::TYPES as $type) {
                $renderers[$type] = $renderer;
            }
        }

        return new self($renderers, $sanitizer);
    }

    public function with(string $type, BlockRenderer $renderer): self
    {
        return new self([...$this->renderers, $type => $renderer], $this->sanitizer, $this->filters, $this->dataAttributes);
    }

    /**
     * The same renderer, code blocks coloured by `$highlighter` — such as `phil/highlight`'s `Highlighter::highlight(...)`
     * —, whose HTML `CodeRenderer` keeps only when it is the escaped code inside spans.
     *
     * @param Closure(string, string): ?string $highlighter
     */
    public function withCodeHighlighter(Closure $highlighter): self
    {
        return $this->with('code', new CodeRenderer($highlighter));
    }

    /**
     * The same renderer, the blocks of `$types` drawn from the markup they keep under `rendered` once `$trusts` proves
     * its key for their current source — such as `phil/svg-blocks`' `Signer::trusts(...)` —, by their former renderer
     * otherwise.
     *
     * @param Closure(Block, string, string): bool $trusts
     * @param list<string>                         $types
     *
     * @throws UnknownBlockType when one of the types has no renderer to fall back to
     */
    public function withStoredMarkup(Closure $trusts, array $types = StoredMarkup::TYPES): self
    {
        $renderer = $this;

        foreach ($types as $type) {
            $renderer = $renderer->with($type, new StoredMarkupRenderer($this->renderer($type), $trusts));
        }

        return $renderer;
    }

    /**
     * The same renderer, with `$filter` run on the HTML of each document after the filters it already has.
     */
    public function withFilter(HtmlFilter $filter): self
    {
        return new self($this->renderers, $this->sanitizer, [...$this->filters, $filter], $this->dataAttributes);
    }

    /**
     * The same renderer, writing the values a block keeps in its `data` tune as its `data-*` attributes; they are kept
     * out of the HTML otherwise.
     */
    public function withDataAttributes(bool $written = true): self
    {
        return new self($this->renderers, $this->sanitizer, $this->filters, $written);
    }

    /**
     * @throws UnknownBlockType when no renderer is registered for the type
     */
    public function renderer(string $type): BlockRenderer
    {
        return $this->renderers[$type] ?? throw UnknownBlockType::renderer($type, 'HTML');
    }

    /**
     * The HTML of the document, one line per top-level block, passed through each filter.
     *
     * @throws UnknownBlockType
     */
    public function render(Document $document): string
    {
        $html = new Rendering($document, $this, $this->sanitizer, $this->dataAttributes)->blocks($document->blocks);

        foreach ($this->filters as $filter) {
            $html = $filter->filter($html);
        }

        return $html;
    }
}

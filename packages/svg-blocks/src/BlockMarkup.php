<?php

declare(strict_types=1);

namespace Phil\SvgBlocks;

use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Type\StoredMarkup;

/**
 * Draws the blocks of a document once, when it is saved, and keeps each drawing with its block under `rendered`,
 * signed: `{"markup": svg or mathml, "key": text}`. A drawing whose key still matches the block's source is kept as
 * it is, so a document saved again costs nothing; any other is dropped and drawn anew, so a drawing a client sent,
 * or one left from an older source, never survives a save.
 *
 * The renderers of `$deferred` types — the diagrams, drawn by a service — run only when asked, so a save does not
 * wait on them: their blocks are listed as pending, for a pass in the background.
 */
final readonly class BlockMarkup
{
    /**
     * @param array<string, MarkupRenderer> $renderers each type, to its renderer
     * @param list<string>                  $deferred  the types drawn only in a later pass
     */
    public function __construct(
        private Signer $signer,
        private array $renderers,
        private array $deferred = ['mermaid'],
    ) {
    }

    /**
     * The document with every block of a known type drawn, nested blocks included; `$withDeferred` draws the
     * deferred types too.
     */
    public function render(Document $document, bool $withDeferred = false): Rendered
    {
        $pending = [];
        $failures = [];
        $changed = false;
        $blocks = [];

        foreach ($document->blocks as $block) {
            $blocks[] = $this->block($block, $withDeferred, $pending, $failures, $changed);
        }

        return new Rendered($changed ? $document->withBlocks($blocks) : $document, $changed, $pending, $failures);
    }

    /**
     * Whether a type is drawn by this instance.
     */
    public function draws(string $type): bool
    {
        return isset($this->renderers[$type]);
    }

    /**
     * The drawing of one block, for a preview, without keeping it.
     *
     * @throws RenderingFailed when the type is not drawn, or its source cannot be
     */
    public function preview(Block $block): string
    {
        $renderer = $this->renderers[$block->type] ?? throw RenderingFailed::because(\sprintf('Blocks of type "%s" are not drawn.', $block->type));

        return $this->bounded($renderer->render($block));
    }

    /**
     * @param list<string>          $pending
     * @param array<string, string> $failures
     */
    private function block(Block $block, bool $withDeferred, array &$pending, array &$failures, bool &$changed): Block
    {
        $data = $this->nested($block->data, $withDeferred, $pending, $failures, $changed);
        $block = $data === $block->data ? $block : $block->withData($data);
        $renderer = $this->renderers[$block->type] ?? null;

        if (null === $renderer || $this->isCurrent($block)) {
            return $block;
        }

        $data = $block->data;
        unset($data['rendered']);

        if (!$withDeferred && \in_array($block->type, $this->deferred, true)) {
            $pending[] = $block->id;
        } else {
            try {
                $markup = $this->bounded($renderer->render($block));
                $data['rendered'] = ['markup' => $markup, 'key' => $this->signer->sign($block->type, Source::of($block), $markup)];
            } catch (RenderingFailed $failure) {
                $failures[$block->id] = $failure->getMessage();
            }
        }

        if ($data === $block->data) {
            return $block;
        }

        $changed = true;

        return $block->withData($data);
    }

    /**
     * The data with the blocks nested in it drawn.
     *
     * @param array<array-key, mixed> $data
     * @param list<string>            $pending
     * @param array<string, string>   $failures
     *
     * @return array<array-key, mixed>
     */
    private function nested(array $data, bool $withDeferred, array &$pending, array &$failures, bool &$changed): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof Block) {
                $data[$key] = $this->block($value, $withDeferred, $pending, $failures, $changed);
            } elseif (\is_array($value)) {
                $data[$key] = $this->nested($value, $withDeferred, $pending, $failures, $changed);
            }
        }

        return $data;
    }

    private function isCurrent(Block $block): bool
    {
        $stored = $block->data['rendered'] ?? null;

        return \is_array($stored) && \is_string($stored['markup'] ?? null) && \is_string($stored['key'] ?? null)
            && $this->signer->trusts($block, $stored['markup'], $stored['key']);
    }

    private function bounded(string $markup): string
    {
        if (mb_strlen($markup) > StoredMarkup::MAX_MARKUP) {
            throw RenderingFailed::because(\sprintf('The drawing is longer than the %d characters a block may keep.', StoredMarkup::MAX_MARKUP));
        }

        return $markup;
    }
}

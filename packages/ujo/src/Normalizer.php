<?php

declare(strict_types=1);

namespace Phil\Ujo;

use Closure;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\TuneRegistry;
use WeakMap;

/**
 * Checks every block of a document against its type and returns the document in canonical form: each type and tune
 * under its canonical name, flat children folded into their block, older forms upgraded, values in the order of their
 * shape with their defaults, HTML sanitized, identifiers unique, nesting under a depth limit.
 *
 * The limits bound the work of rendering a document as much as its size: `maxBlocks` blocks in all, nested ones
 * included, as many blocks of a type as its `maxPerDocument` allows, and HTML fields of at most `maxHtmlLength`
 * characters, `maxRawLength` for those of the `raw` profile.
 *
 * A document is checked at a `Validation` level: its shape by default, and, at `Validation::Publication`, also the
 * values its types require filled in and the bounds they set for publication. The limits above hold at both levels.
 *
 * A document the normalizer returned is already canonical: given again — to be rendered or encoded after it was
 * decoded —, it is returned as it is, without being checked a second time, unless it is asked at a level it was not
 * checked at.
 */
final readonly class Normalizer
{
    public const int DEFAULT_MAX_DEPTH = 8;

    public const int DEFAULT_MAX_BLOCKS = 10_000;

    public const int DEFAULT_MAX_HTML_LENGTH = 100_000;

    public const int DEFAULT_MAX_RAW_LENGTH = 50_000;

    private BlockTypeRegistry $types;

    private TuneRegistry $tunes;

    private InlineSanitizer $sanitizer;

    /** @var WeakMap<Document, Validation> the documents this normalizer returned, to the level they were checked at */
    private WeakMap $normalized;

    public function __construct(
        ?BlockTypeRegistry $types = null,
        ?TuneRegistry $tunes = null,
        ?InlineSanitizer $sanitizer = null,
        private int $maxDepth = self::DEFAULT_MAX_DEPTH,
        private int $maxBlocks = self::DEFAULT_MAX_BLOCKS,
        private int $maxHtmlLength = self::DEFAULT_MAX_HTML_LENGTH,
        private int $maxRawLength = self::DEFAULT_MAX_RAW_LENGTH,
    ) {
        $this->types = $types ?? BlockTypeRegistry::default();
        $this->tunes = $tunes ?? TuneRegistry::default();
        $this->sanitizer = $sanitizer ?? new InlineSanitizer();
        $this->normalized = new WeakMap();
    }

    /**
     * @throws InvalidDocument  when a block's structure or data breaks its type, or the document a limit
     * @throws UnknownBlockType when a block or a tune is of no registered type
     */
    public function normalize(Document $document, Validation $level = Validation::Shape): Document
    {
        $checked = $this->normalized[$document] ?? null;

        if ($checked instanceof Validation && $checked->covers($level)) {
            return $document;
        }

        $context = new Context($this, $this->sanitizer, $this->maxDepth, $this->maxBlocks, $this->maxHtmlLength, $this->maxRawLength, $level);
        $blocks = array_map(static fn (Block $block): array => $block->toArray(), $document->blocks);
        $normalized = $document->withBlocks($this->blocks($blocks, 'blocks', $context));
        $this->normalized[$normalized] = $level;

        return $normalized;
    }

    /**
     * A list of blocks in their JSON form, normalized; the work of `normalize()` at each level of nesting, called back
     * by `Context`.
     *
     * @param list<mixed> $blocks
     *
     * @return list<Block>
     *
     * @internal
     */
    public function blocks(array $blocks, string $path, Context $context): array
    {
        $normalized = [];
        $count = \count($blocks);

        for ($index = 0; $index < $count; ++$index) {
            $at = $path.'['.$index.']';
            $block = Block::fromArray($blocks[$index], $at);
            $type = $this->types->get($block->type, $at);
            $context->count($type->name, $type->maxPerDocument, $at);
            $take = ($type->fold ?? static fn (): int => 0)($block->data);

            if ($take < 0) {
                throw InvalidDocument::at($at, \sprintf('the block cannot hold %d blocks after it', $take));
            }

            if ($take > 0) {
                if ($index + $take >= $count) {
                    throw InvalidDocument::at($at, \sprintf('the block holds the %d blocks after it, and only %d follow', $take, $count - $index - 1));
                }

                $block = $block->withData([...$block->data, 'blocks' => \array_slice($blocks, $index + 1, $take)]);
                $index += $take;
            }

            $context->claim($block->id, $at);

            if ($type->upgrade instanceof Closure) {
                $block = ($type->upgrade)($block);
            }

            $data = $type->data->normalize($block->data, $at.'.data', $context);

            if ($type->check instanceof Closure) {
                ($type->check)($data, $at.'.data');
            }

            if ($type->publication instanceof Closure && Validation::Publication === $context->level()) {
                ($type->publication)($data, $at.'.data');
            }

            $normalized[] = new Block($block->id, $type->name, $data, $this->tunes($block->tunes, $at.'.tunes', $context));
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $tunes
     *
     * @return array<string, mixed>
     */
    private function tunes(array $tunes, string $path, Context $context): array
    {
        $normalized = [];

        foreach ($tunes as $name => $value) {
            $tune = $this->tunes->get($name, $path.'.'.$name);
            $normalized[$tune->name] = $tune->value->normalize($value, $path.'.'.$name, $context);
        }

        return $normalized;
    }
}

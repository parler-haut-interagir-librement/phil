<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Ujo;

use Phil\AccessibilityCheck\Exception\InvalidContent;
use Phil\AccessibilityCheck\Exception\LimitExceeded;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Policy;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * A UJO document as the rules read it: each block in document order, nested blocks after their parent, with where it
 * is and its language, and each field of their data that holds HTML, parsed once.
 *
 * Nested blocks are read whether the document was normalized or not. The blocks are counted against the policy's
 * maximum number of elements, and the nesting of blocks and data against its maximum depth. A target belongs to one
 * check and is never shared.
 */
final readonly class UjoTarget
{
    /**
     * @var array<string, list<Fragment>>
     */
    private array $fragmentsOfBlock;

    /**
     * @param list<BlockVisit> $blocks
     * @param list<Fragment>   $fragments
     */
    private function __construct(
        public Document $document,
        public Policy $policy,
        public ?string $language,
        private array $blocks,
        private array $fragments,
    ) {
        $byBlock = [];

        foreach ($fragments as $fragment) {
            $byBlock[$fragment->visit->pointer][] = $fragment;
        }

        $this->fragmentsOfBlock = $byBlock;
    }

    /**
     * @throws LimitExceeded  when the document holds more blocks, or nests deeper, than the policy allows
     * @throws InvalidContent when a field holds HTML that is not UTF-8
     */
    public static function read(Document $document, Policy $policy, ?string $language = null, PlainFields $plain = new PlainFields()): self
    {
        $reader = new UjoReader($policy, $plain);

        foreach ($document->blocks as $index => $block) {
            $reader->visit($block, 'blocks['.$index.']', $language, 1);
        }

        return new self($document, $policy, $language, $reader->blocks, $reader->fragments);
    }

    /**
     * Every block, in document order, nested blocks after their parent.
     *
     * @return list<BlockVisit>
     */
    public function blocks(string ...$types): array
    {
        if ([] === $types) {
            return $this->blocks;
        }

        return array_values(array_filter(
            $this->blocks,
            static fn (BlockVisit $visit): bool => \in_array($visit->block->type, $types, true),
        ));
    }

    /**
     * Every field that holds HTML, in document order; those of one block when it is given.
     *
     * @return list<Fragment>
     */
    public function fragments(?BlockVisit $of = null): array
    {
        if (!$of instanceof BlockVisit) {
            return $this->fragments;
        }

        return $this->fragmentsOfBlock[$of->pointer] ?? [];
    }

    /**
     * The text of an HTML field, its tags left out and its entities decoded, spaces collapsed; `''` for a value that is
     * not a string.
     */
    public static function text(mixed $html): string
    {
        if (!\is_string($html)) {
            return '';
        }

        return HtmlTarget::collapse(html_entity_decode(strip_tags(str_replace('<', ' <', $html)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }

    /**
     * A field of the block's data, `null` when it is missing: `field('file', 'url')` is `data.file.url`.
     */
    public static function field(Block $block, string|int ...$keys): mixed
    {
        $value = $block->data;

        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * A location in the document without a block, for what concerns it whole.
     */
    public static function whole(): Location
    {
        return new Location();
    }
}

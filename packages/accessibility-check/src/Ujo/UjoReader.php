<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Ujo;

use Phil\AccessibilityCheck\Exception\LimitExceeded;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Policy;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * Walks the blocks of a document and their data for `UjoTarget`, once.
 */
final class UjoReader
{
    /** @var list<BlockVisit> */
    public array $blocks = [];

    /** @var list<Fragment> */
    public array $fragments = [];

    public function __construct(
        private readonly Policy $policy,
        private readonly PlainFields $plain,
    ) {
    }

    public function visit(Block $block, string $pointer, ?string $parentLanguage, int $depth): void
    {
        $tune = $block->tune('language');
        $language = \is_array($tune) && \is_string($tune['language'] ?? null) ? $tune['language'] : $parentLanguage;
        $visit = new BlockVisit($block, $pointer, $language);
        $this->blocks[] = $visit;

        if (\count($this->blocks) > $this->policy->maxElements) {
            throw LimitExceeded::count($this->policy->maxElements);
        }

        $this->data($visit, $block->data, $pointer.'.data', '', $depth + 1);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function data(BlockVisit $visit, array $data, string $pointer, string $field, int $depth): void
    {
        if ($depth > $this->policy->maxDepth) {
            throw LimitExceeded::depth($this->policy->maxDepth);
        }

        foreach ($data as $key => $value) {
            $here = \is_int($key) ? $pointer.'['.$key.']' : $pointer.'.'.$key;
            $name = \is_int($key) ? $field : $key;
            $block = $value instanceof Block ? $value : $this->blockOf($value);

            if ($block instanceof Block) {
                $this->visit($block, $here, $visit->language, $depth + 1);
            } elseif (\is_array($value)) {
                $this->data($visit, $value, $here, $name, $depth + 1);
            } elseif (\is_string($value) && str_contains($value, '<') && !$this->plain->isPlain($visit->block->type, $name)) {
                $origin = new Location(block: $visit->block->id, blockType: $visit->block->type, pointer: $here);
                $this->fragments[] = new Fragment($visit, $here, HtmlTarget::parse($value, $this->policy, $visit->language, $origin));
            }
        }
    }

    /**
     * A nested block of a document that was not normalized, still an array: `{id, type, data}`.
     */
    private function blockOf(mixed $value): ?Block
    {
        if (!\is_array($value) || !\is_string($value['type'] ?? null) || !\is_array($value['data'] ?? null)) {
            return null;
        }

        try {
            return Block::fromArray($value, '');
        } catch (InvalidDocument) {
            return null;
        }
    }
}

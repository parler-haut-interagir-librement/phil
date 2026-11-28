<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

use InvalidArgumentException;

/**
 * The cut of a UJO (Universal JSON Output) document: a block of its own type, `access-cut` by default, after which the
 * content is reserved. Gives the blocks a decision lets the viewer read, so that the reserved part never leaves the
 * server for a viewer without the right — it is not hidden in the page, it is not sent.
 *
 * A document without a cut has no public part: its preview is empty, never the whole content.
 */
final readonly class PreviewCut
{
    public const string DEFAULT_TYPE = 'access-cut';

    public function __construct(public string $cutType = self::DEFAULT_TYPE)
    {
        if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $cutType)) {
            throw new InvalidArgumentException('The type of the cut block is a letter, then letters, digits, "_" or "-", 64 bytes at most.');
        }
    }

    /**
     * The blocks the viewer reads: every block but the cuts for the whole content, the blocks before the first cut for a
     * preview, none for a locked or hidden content.
     *
     * @param list<array<array-key, mixed>> $blocks the `blocks` of the document
     *
     * @return list<array<array-key, mixed>>
     */
    public function visibleBlocks(Decision $decision, array $blocks): array
    {
        return match ($decision->access) {
            Access::Full => array_values(array_filter($blocks, fn (array $block): bool => !$this->isCut($block))),
            Access::Preview => $this->publicPart($blocks),
            Access::Locked, Access::Hidden => [],
        };
    }

    /**
     * The blocks before the first cut; none when the document holds no cut.
     *
     * @param list<array<array-key, mixed>> $blocks
     *
     * @return list<array<array-key, mixed>>
     */
    public function publicPart(array $blocks): array
    {
        $public = [];

        foreach ($blocks as $block) {
            if ($this->isCut($block)) {
                return $public;
            }

            $public[] = $block;
        }

        return [];
    }

    /**
     * @param list<array<array-key, mixed>> $blocks
     */
    public function hasCut(array $blocks): bool
    {
        return array_any($blocks, fn (array $block): bool => $this->isCut($block));
    }

    /**
     * @param array<array-key, mixed> $block
     */
    private function isCut(array $block): bool
    {
        return ($block['type'] ?? null) === $this->cutType;
    }
}

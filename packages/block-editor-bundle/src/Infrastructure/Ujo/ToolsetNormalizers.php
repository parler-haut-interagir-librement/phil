<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\Ujo;

use Phil\BlockEditorBundle\Application\Toolset;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;

/**
 * The `phil/ujo` normalizer of a toolset: the registry of the application without the block types the toolset leaves
 * out — unless foreign blocks are kept —, and a `rich` profile without the inline tools it leaves out, so that their
 * elements are unwrapped and their text kept. One normalizer per toolset, built once.
 *
 * The document has already been read and checked by the application's `Ujo`, its size and depth included; this pass
 * only narrows it, so it sets no depth of its own.
 */
final class ToolsetNormalizers
{
    /** @var array<string, Normalizer> the normalizers narrowing the block types, by toolset */
    private array $strict = [];

    /** @var array<string, Normalizer> the normalizers keeping every block type, by toolset */
    private array $foreign = [];

    public function __construct(
        private readonly BlockTypeRegistry $registry,
    ) {
    }

    public function normalizer(Toolset $toolset, bool $keepForeignBlocks = false): Normalizer
    {
        if ($keepForeignBlocks) {
            return $this->foreign[$toolset->key()] ??= $this->build($this->registry, $toolset);
        }

        return $this->strict[$toolset->key()] ??= $this->build($this->registry->without(...array_diff($this->registry->names(), $toolset->blockTools)), $toolset);
    }

    private function build(BlockTypeRegistry $registry, Toolset $toolset): Normalizer
    {
        return new Normalizer($registry, null, new InlineSanitizer(['rich' => $this->profile($toolset)]), \PHP_INT_MAX);
    }

    private function profile(Toolset $toolset): InlineProfile
    {
        $rich = InlineProfile::rich();
        $left = [];

        foreach ($rich->tools as $tool) {
            if (!$toolset->hasInline($tool->name)) {
                $left[] = $tool->name;
            }
        }

        return $rich->without(...$left);
    }
}

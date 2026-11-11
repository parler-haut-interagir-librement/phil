<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A toolset names a tool the editor does not have, a preset that does not exist, or leaves out the paragraph.
 */
final class InvalidToolset extends InvalidArgumentException implements BlockEditorException
{
    /**
     * @param array<string> $unknown
     * @param list<string>  $known
     */
    public static function unknownTools(string $kind, array $unknown, array $known): self
    {
        return new self(\sprintf('The block editor has no %s tool named "%s"; the %s tools are "%s".', $kind, implode('", "', $unknown), $kind, implode('", "', $known)));
    }

    public static function unknownPreset(string $preset): self
    {
        return new self(\sprintf('The block editor has no preset named "%s"; the presets are "full" and "reduced".', $preset));
    }

    public static function withoutParagraph(): self
    {
        return new self('A toolset of the block editor needs the "paragraph" tool: it is the block every new line starts as.');
    }
}

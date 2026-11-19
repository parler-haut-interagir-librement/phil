<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Rtf;

use Phil\DocumentImport\Internal\Mark;

/**
 * What a group of an RTF file holds for the groups inside it: the character formatting, hidden text, the count of
 * fallback characters after `\uN`, the link of a field result, the destination its text goes to, and whether it is
 * skipped. A group starts as a copy of the group around it.
 *
 * @internal
 */
final class RtfState
{
    /** @var array<string, Mark> */
    public array $marks = [];

    public bool $hidden = false;

    public bool $skip = false;

    public int $fallback = 1;

    public ?string $link = null;

    public ?RtfCapture $capture = null;

    public ?RtfField $field = null;

    /** Whether no control word nor text has been met in the group yet, so that a control word may name its destination. */
    public bool $opening = true;

    /** Whether the group started with `\*`: a destination the reader may skip when it does not know it. */
    public bool $ignorable = false;

    /** Whether the group holds a footnote, which closes with it. */
    public bool $note = false;

    public function child(): self
    {
        $child = clone $this;
        $child->opening = true;
        $child->ignorable = false;
        $child->note = false;

        return $child;
    }

    /**
     * @return list<Mark>
     */
    public function marks(): array
    {
        return array_values($this->marks);
    }

    public function set(Mark $mark, bool $on): void
    {
        if ($on) {
            $this->marks[$mark->name] = $mark;

            return;
        }

        unset($this->marks[$mark->name]);
    }
}

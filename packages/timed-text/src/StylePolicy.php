<?php

declare(strict_types=1);

namespace Phil\TimedText;

/**
 * What a WebVTT reader or writer does with the `STYLE` blocks: their CSS is never interpreted, only kept as text for
 * the next WebVTT file, or dropped.
 */
enum StylePolicy
{
    /**
     * The CSS of each block is kept as written, and written back.
     */
    case Keep;

    /**
     * The blocks are left out, for a track whose styling the application decides.
     */
    case Drop;
}

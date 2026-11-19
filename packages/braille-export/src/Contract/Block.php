<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Contract;

/**
 * One block of a {@see \Phil\BrailleExport\Document\Document}: a heading, a paragraph, a list, a table, or a marker that moves the layout.
 *
 * The layout knows the blocks of the `Document` namespace; a block of another class is left out.
 */
interface Block
{
}

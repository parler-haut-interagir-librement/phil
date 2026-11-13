<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Exception;

use RuntimeException;
use Throwable;

/**
 * A block could not be drawn: its source is refused by the converter — a formula, a chart configuration, a text too
 * long for a QR code —, the diagram service did not answer or answered something else than an image, or the drawing
 * is larger than a block may keep. The message is meant for the author of the block.
 */
final class RenderingFailed extends RuntimeException implements SvgBlocksException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}

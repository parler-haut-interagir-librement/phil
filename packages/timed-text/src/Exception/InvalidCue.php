<?php

declare(strict_types=1);

namespace Phil\TimedText\Exception;

use InvalidArgumentException;

/**
 * A cue, chapter, region, note or style built with values its formats cannot carry.
 */
final class InvalidCue extends InvalidArgumentException implements TimedTextException
{
}

<?php

declare(strict_types=1);

namespace Phil\Audio\Exception;

use RuntimeException;

/**
 * No tool to run: the null adapter is configured, or the binary cannot be started.
 */
final class AudioToolUnavailable extends RuntimeException implements AudioException
{
}

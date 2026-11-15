<?php

declare(strict_types=1);

namespace Phil\Audio\Exception;

use RuntimeException;

/**
 * A tool that ran and refused the file: its first line of error, at most 500 bytes, is the message.
 */
final class AudioToolFailed extends RuntimeException implements AudioException
{
}

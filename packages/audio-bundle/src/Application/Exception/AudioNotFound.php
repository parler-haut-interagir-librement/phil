<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Exception;

use RuntimeException;

/**
 * No audio file has the identifier a job names: it was deleted after the job was dispatched.
 */
final class AudioNotFound extends RuntimeException
{
}

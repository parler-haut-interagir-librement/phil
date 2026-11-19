<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Exception;

use RuntimeException;

/**
 * Every exception of the bundle's own; the library's are `VirtualClassroomException`. Its message names no
 * participant.
 */
abstract class ClassroomException extends RuntimeException
{
}

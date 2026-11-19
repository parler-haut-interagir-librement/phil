<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Exception;

use Throwable;

/**
 * Every exception the library throws. Its message names no participant and holds no address of a call: a log may
 * keep it.
 */
interface VirtualClassroomException extends Throwable
{
}

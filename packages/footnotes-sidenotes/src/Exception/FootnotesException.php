<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Exception;

use Throwable;

/**
 * Every exception of the library: a catch of this interface catches them all.
 */
interface FootnotesException extends Throwable
{
}

<?php

declare(strict_types=1);

namespace Phil\BounceParser\Exception;

use Throwable;

/**
 * Every exception of phil/bounce-parser: one `catch` covers them all. Parsing a message never throws; only a
 * configuration does.
 */
interface BounceParserException extends Throwable
{
}

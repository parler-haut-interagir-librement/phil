<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit\Fixture\Exception;

use Phil\Outbox\Exception\OutboxException;
use RuntimeException;

/**
 * An exception of the application that claims to be one of the outbox: its message is the application's text.
 */
final class OutboxShaped extends RuntimeException implements OutboxException
{
}

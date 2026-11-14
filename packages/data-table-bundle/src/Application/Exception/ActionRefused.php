<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Exception;

use RuntimeException;

/**
 * Thrown by an action or by an edit that cannot go through. The message is a translation key of the table's domain,
 * shown to the person who asked.
 */
final class ActionRefused extends RuntimeException implements DataTableException
{
}

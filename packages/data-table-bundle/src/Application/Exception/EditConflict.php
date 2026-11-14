<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Exception;

use RuntimeException;

/**
 * Thrown by an edit that finds the row changed since it was read: `OptimisticLockException` caught around a flush,
 * for instance.
 */
final class EditConflict extends RuntimeException implements DataTableException
{
}

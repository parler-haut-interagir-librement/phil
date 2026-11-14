<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

use Override;
use Phil\DataTableBundle\Application\Contract\TableAccess;

/**
 * The access without Symfony Security: an attribute that nobody can answer is refused.
 */
final readonly class DenyingTableAccess implements TableAccess
{
    #[Override]
    public function isGranted(string $attribute, array|object|null $subject = null): bool
    {
        return false;
    }
}

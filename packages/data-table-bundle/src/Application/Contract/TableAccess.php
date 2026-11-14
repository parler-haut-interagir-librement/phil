<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Contract;

/**
 * Whether the current viewer holds a voter attribute on a subject.
 */
interface TableAccess
{
    /**
     * @param array<string, mixed>|object|null $subject
     */
    public function isGranted(string $attribute, array|object|null $subject = null): bool;
}

<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Fixture;

/**
 * The subject of a workflow that is not audited.
 */
final class PlainOrder
{
    public string $state = 'cart';
}

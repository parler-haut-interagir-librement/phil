<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Fixtures;

use Override;
use Phil\ResourcePermissions\Contract\TupleScope;
use Phil\ResourcePermissions\Exception\UnscopedTuples;
use Phil\ResourcePermissions\Partition;

/**
 * The partition a test enters, the default one at first, or none: a store that asks then fails closed.
 */
final class SwitchableScope implements TupleScope
{
    public ?string $partition = Partition::DEFAULT;

    public int $asked = 0;

    #[Override]
    public function partition(): string
    {
        ++$this->asked;

        return $this->partition ?? throw UnscopedTuples::because('No tenant is active.');
    }
}

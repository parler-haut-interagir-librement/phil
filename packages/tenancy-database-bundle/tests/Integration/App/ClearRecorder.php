<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App;

use Phil\TenancyBundle\Application\TenantContext;

/**
 * Records each clear of an entity manager, with the tenant active when it happens.
 */
final class ClearRecorder
{
    /** @var list<?string> */
    public array $clears = [];

    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    public function onClear(): void
    {
        $this->clears[] = $this->context->current()?->value;
    }
}
